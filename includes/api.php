<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/version.php';

// 系统 API 基础层：认证、限流、审计、JSON 输出与参数校验。
// 认证方式：
// - Bearer API 密钥（外部工具）：Authorization: Bearer <key>，密钥只以 SHA-256 哈希形式存库；
// - 管理员会话（管理面板）：已登录的管理员会话，写操作还需携带 X-CSRF-Token 请求头。

const API_ALLOWED_SCOPES = [
    'stats:read',
    'questions:read',
    'questions:write',
    'results:read',
    'results:write',
    'users:read',
    'keys:admin',
    'system:read',
    'exam:read',
    'exam:write',
    'blacklist:read',
    'blacklist:write',
];

// 输出 JSON 并终止脚本
function apiRespond($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => $status >= 200 && $status < 300, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// 输出 204 No Content（无响应体）并终止脚本
function apiNoContent(): void
{
    http_response_code(204);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    exit;
}

// 输出错误响应并终止脚本
function apiError(int $status, string $code, string $message): void
{
    apiRespond(['error' => ['code' => $code, 'message' => $message]], $status);
}

// API 入口初始化：仅允许 HTTPS（本机回环地址除外），并发送安全响应头
function apiInit(): void
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $isLoopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$https && !$isLoopback) {
        apiError(403, 'https_required', 'API 仅允许通过 HTTPS 访问。');
    }

    sendSecurityHeaders();
}

// 读取请求体：JSON 优先，其次为表单编码
function apiBody(): array
{
    static $body = null;

    if ($body !== null) {
        return $body;
    }

    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);

    if (is_array($json)) {
        $body = $json;
    } else {
        $body = $_POST;
    }

    return $body;
}

// 从请求中读取字符串参数（请求体优先，其次为查询参数）
function apiString(string $name, string $default = '', int $maxLength = 0): string
{
    $value = apiBody()[$name] ?? $_GET[$name] ?? $default;
    if (!is_scalar($value)) {
        $value = $default;
    }
    $value = trim((string)$value);
    if ($maxLength > 0) {
        $value = mb_substr($value, 0, $maxLength);
    }
    return $value;
}

// 从请求中读取整数参数
function apiInt(string $name, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $value = apiBody()[$name] ?? $_GET[$name] ?? $default;
    if (!is_numeric($value)) {
        return $default;
    }
    return max($min, min($max, (int)$value));
}

// 从请求中读取布尔参数
function apiBool(string $name, bool $default = false): bool
{
    $value = apiBody()[$name] ?? $_GET[$name] ?? null;
    if ($value === null) {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

// 从请求中读取枚举参数，非法时返回默认值
function apiEnum(string $name, array $allowed, string $default): string
{
    $value = (string)(apiBody()[$name] ?? $_GET[$name] ?? $default);
    return in_array($value, $allowed, true) ? $value : $default;
}

// 校验并返回分页参数
function apiPaginationParams(int $defaultPerPage = 20): array
{
    $page = apiInt('page', 1, 1);
    $perPage = apiInt('per_page', $defaultPerPage, 1, 100);
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
}

// 校验日期筛选参数（YYYY-MM-DD），格式非法时返回 null
function isValidDateFilter(string $date): bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

// 计算 API 的外部基础地址（用于 Location 响应头）
function apiBaseUrl(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api')), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

// 为分页列表响应发送 RFC 5988 Link 头（first / prev / next / last）
function apiPaginationLinkHeader(int $page, int $perPage, int $pages): void
{
    if ($pages <= 1) {
        return;
    }

    $base = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    unset($query['page']);

    $rels = [
        'first' => 1,
        'prev' => max(1, $page - 1),
        'next' => min($pages, $page + 1),
        'last' => max(1, $pages),
    ];

    $links = [];
    foreach ($rels as $rel => $p) {
        $links[] = '<' . $base . '?' . http_build_query(array_merge($query, ['page' => $p])) . '>; rel="' . $rel . '"';
    }

    header('Link: ' . implode(', ', $links));
}

// 写入审计日志
function auditLog(PDO $db, string $actor, string $action, ?string $target = null, $detail = null): void
{
    $stmt = $db->prepare('INSERT INTO audit_log (actor, action, target, detail, ip) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        mb_substr($actor, 0, 255),
        mb_substr($action, 0, 50),
        $target !== null ? mb_substr($target, 0, 255) : null,
        $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
        mb_substr(getClientIP(), 0, 64),
    ]);
}

// API 限流：每个桶（IP / 密钥）每分钟最大请求数，超出返回 true
function apiRateLimit(string $bucket, int $maxPerMinute): bool
{
    if ($maxPerMinute <= 0) {
        return false;
    }

    $db = getPDO();
    $bucket = mb_substr($bucket, 0, 128);
    $window = (int)floor(time() / 60) * 60;

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT count FROM api_rate_limits WHERE bucket = ? FOR UPDATE');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();

        if ($row === false) {
            $stmt = $db->prepare('INSERT INTO api_rate_limits (bucket, window_start, count) VALUES (?, ?, 1)');
            $stmt->execute([$bucket, $window]);
            $exceeded = false;
        } elseif ((int)$row['window_start'] !== $window) {
            $stmt = $db->prepare('UPDATE api_rate_limits SET window_start = ?, count = 1 WHERE bucket = ?');
            $stmt->execute([$window, $bucket]);
            $exceeded = false;
        } else {
            $count = (int)$row['count'] + 1;
            $stmt = $db->prepare('UPDATE api_rate_limits SET count = ? WHERE bucket = ?');
            $stmt->execute([$count, $bucket]);
            $exceeded = $count > $maxPerMinute;
        }
        $db->commit();

        if (random_int(1, 100) === 1) {
            $db->prepare('DELETE FROM api_rate_limits WHERE window_start < ?')->execute([time() - 600]);
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $exceeded = false;
    }

    return $exceeded;
}

// API 认证：返回 ['type' => 'key'|'session', 'actor' => 字符串, 'scopes' => array]，
// 认证失败时直接输出错误并终止脚本
function apiRequireAuth(): array
{
    $pdo = getPDO();

    $header = '';
    $allHeaders = function_exists('getallheaders') ? getallheaders() : [];
    if (!is_array($allHeaders)) {
        $allHeaders = [];
    }
    foreach ($allHeaders as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $header = $value;
            break;
        }
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
        if (apiRateLimit('ip:' . getClientIP(), API_RATE_LIMIT_IP_PER_MINUTE)) {
            apiError(429, 'rate_limited', '请求过于频繁，请稍后再试。');
        }

        $keyHash = hash('sha256', $matches[1]);
        $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE key_hash = ?');
        $stmt->execute([$keyHash]);
        $key = $stmt->fetch();

        if ($key === false) {
            apiError(401, 'invalid_key', 'API 密钥无效。');
        }
        if ((int)$key['enabled'] !== 1) {
            apiError(403, 'key_disabled', 'API 密钥已被停用。');
        }
        if ($key['expires_at'] !== null && strtotime((string)$key['expires_at']) < time()) {
            apiError(403, 'key_expired', 'API 密钥已过期。');
        }
        if (apiRateLimit('key:' . $key['id'], API_RATE_LIMIT_PER_MINUTE)) {
            apiError(429, 'rate_limited', '请求过于频繁，请稍后再试。');
        }

        $pdo->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$key['id']]);

        $scopes = array_values(array_filter(explode(',', (string)$key['scopes'])));
        return ['type' => 'key', 'actor' => 'API 密钥：' . $key['name'], 'scopes' => $scopes];
    }

    // 会话认证：管理面板调用
    require_once __DIR__ . '/../admin/auth.php';
    $admin = currentAdmin();
    if ($admin === null) {
        apiError(401, 'unauthenticated', '未登录或会话已过期，请先登录管理面板。');
    }

    if (apiRateLimit('ip:' . getClientIP(), API_RATE_LIMIT_IP_PER_MINUTE)) {
        apiError(429, 'rate_limited', '请求过于频繁，请稍后再试。');
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCSRFToken($csrf)) {
            apiError(403, 'csrf_failed', 'CSRF 校验失败，请刷新页面后重试。');
        }
    }

    return ['type' => 'session', 'actor' => (string)$admin['identity_label'], 'scopes' => array_values(API_ALLOWED_SCOPES)];
}

// 校验当前认证是否具备指定作用域，不具备时输出 403 并终止脚本
function apiRequireScope(array $auth, string $scope): void
{
    if (!in_array($scope, $auth['scopes'], true)) {
        apiError(403, 'forbidden', '当前凭据不具备所需权限（' . $scope . '）。');
    }
}

// 规范化题目答案：接受 "A"、"AB"、"A,B" 等写法，返回大写字母逗号分隔形式
function normalizeAnswer(string $answer, string $type): ?string
{
    $answer = strtoupper(str_replace(['(', ')', '，', ',', ' '], '', $answer));
    $letters = str_split($answer);

    if ($letters === [] || count($letters) > 4) {
        return null;
    }

    foreach ($letters as $letter) {
        if (!in_array($letter, ['A', 'B', 'C', 'D'], true)) {
            return null;
        }
    }

    $letters = array_values(array_unique($letters));

    if ($type === 'single' && count($letters) !== 1) {
        return null;
    }
    if ($type === 'multiple' && count($letters) < 2) {
        return null;
    }

    return implode(',', $letters);
}

// 校验并规范化一道题目数据，返回 ['data' => array] 或 ['errors' => array]
function validateQuestionInput(array $input): array
{
    $categories = ['IT', 'ACGN', 'Virtual_Singer', 'Broadcasting', 'Etiquette'];
    $types = ['single', 'multiple'];

    $errors = [];
    $data = [];

    $category = $input['category'] ?? '';
    if (!in_array($category, $categories, true)) {
        $errors[] = '分类无效。';
    } else {
        $data['category'] = $category;
    }

    $questionText = trim((string)($input['question_text'] ?? ''));
    if ($questionText === '') {
        $errors[] = '题干不能为空。';
    } elseif (mb_strlen($questionText) > 5000) {
        $errors[] = '题干过长（最多 5000 字）。';
    } else {
        $data['question_text'] = $questionText;
    }

    foreach (['A', 'B', 'C', 'D'] as $letter) {
        $option = trim((string)($input['option_' . strtolower($letter)] ?? ''));
        if ($option === '') {
            $errors[] = '选项 ' . $letter . ' 不能为空。';
        } elseif (mb_strlen($option) > 255) {
            $errors[] = '选项 ' . $letter . ' 过长（最多 255 字）。';
        } else {
            $data['option_' . strtolower($letter)] = $option;
        }
    }

    $type = $input['type'] ?? '';
    if (!in_array($type, $types, true)) {
        $errors[] = '题型无效。';
    } else {
        $data['type'] = $type;
    }

    if (isset($data['type'])) {
        $answer = normalizeAnswer((string)($input['answer'] ?? ''), $data['type']);
        if ($answer === null) {
            $errors[] = $data['type'] === 'single' ? '单选题答案只能是一个选项（A/B/C/D）。' : '多选题答案至少包含两个选项（A/B/C/D 中的字母组合）。';
        } else {
            $data['answer'] = $answer;
        }
    }

    $author = mb_substr(trim((string)($input['author'] ?? '')), 0, 100);
    $data['author'] = $author;

    if ($errors !== []) {
        return ['errors' => $errors];
    }

    return ['data' => $data];
}

// 查询题目是否存在（按题干精确匹配，可选限定分类）
function questionExists(PDO $db, string $questionText, string $category, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM questions WHERE question_text = ? AND category = ? AND id != ?');
        $stmt->execute([$questionText, $category, $excludeId]);
    } else {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM questions WHERE question_text = ? AND category = ?');
        $stmt->execute([$questionText, $category]);
    }
    return (int)$stmt->fetch()['c'] > 0;
}

// 格式化题目数据行（供列表/详情输出）
function formatQuestionRow(array $question): array
{
    return [
        'id' => (int)$question['id'],
        'category' => $question['category'],
        'question_text' => $question['question_text'],
        'option_a' => $question['option_a'],
        'option_b' => $question['option_b'],
        'option_c' => $question['option_c'],
        'option_d' => $question['option_d'],
        'answer' => str_replace(',', '', (string)$question['answer']),
        'type' => $question['type'],
        'author' => (string)$question['author'],
    ];
}
