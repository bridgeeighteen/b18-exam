<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/markdown-import.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/../includes/blacklist.php';
require_once __DIR__ . '/../includes/exam-service.php';
require_once __DIR__ . '/../includes/matrix-api.php';
require_once __DIR__ . '/../includes/oauth.php';

// 系统 REST API 网关。调用方式：/api/v1/<资源>[/<标识>][?<筛选与分页>]
// 认证：
// - Authorization: Bearer <API 密钥>（外部工具）
// - 管理员会话（管理面板页面），写操作需携带 X-CSRF-Token 请求头
// 路径解析支持两种方式：
// - 重写规则（.htaccess）：/api/v1/... → api/index.php?path=/v1/...
// - PATH_INFO：api/index.php/v1/...（PHP 内置服务器 / Nginx 亦可）

// API 请求上下文标记：getPDO() 等数据库失败时抛异常交由下方统一处理，输出 JSON 错误而不是纯文本
define('API_REQUEST', true);

apiInit();

// 路由表：[HTTP 方法, 路径模式, 所需作用域（public 表示免认证）, 处理器, 审计动作（null 表示只读不审计）]
const API_V1_ROUTES = [
    ['GET', '/v1/health', 'public', 'handleHealth', null],
    ['GET', '/v1/meta', 'public', 'handleMeta', null],
    ['GET', '/v1/stats', 'stats:read', 'handleStatsSummary', null],
    ['GET', '/v1/questions', 'questions:read', 'handleQuestionsList', null],
    ['POST', '/v1/questions', 'questions:write', 'handleQuestionsCreate', 'questions:create'],
    ['GET', '/v1/questions/{id}', 'questions:read', 'handleQuestionsGet', null],
    ['PUT', '/v1/questions/{id}', 'questions:write', 'handleQuestionsUpdate', 'questions:update'],
    ['DELETE', '/v1/questions/{id}', 'questions:write', 'handleQuestionsDelete', 'questions:delete'],
    ['POST', '/v1/questions/import', 'questions:write', 'handleQuestionsImport', 'questions:import'],
    ['GET', '/v1/questions/export', 'questions:read', 'handleQuestionsExport', null],
    ['GET', '/v1/results', 'results:read', 'handleResultsList', null],
    ['GET', '/v1/results/{id}', 'results:read', 'handleResultsGet', null],
    ['DELETE', '/v1/results/{id}', 'results:write', 'handleResultsDelete', 'results:delete'],
    ['GET', '/v1/users', 'users:read', 'handleUsersList', null],
    ['GET', '/v1/users/{id}', 'users:read', 'handleUsersGet', null],
    ['DELETE', '/v1/users/{id}', 'results:write', 'handleUsersDelete', 'users:delete'],
    ['GET', '/v1/keys', 'keys:admin', 'handleKeysList', null],
    ['POST', '/v1/keys', 'keys:admin', 'handleKeysCreate', 'keys:create'],
    ['PATCH', '/v1/keys/{id}', 'keys:admin', 'handleKeysUpdate', 'keys:update'],
    ['DELETE', '/v1/keys/{id}', 'keys:admin', 'handleKeysDelete', 'keys:delete'],
    ['GET', '/v1/audit', 'keys:admin', 'handleAuditList', null],
    ['GET', '/v1/system', 'system:read', 'handleSystemInfo', null],
    ['GET', '/v1/candidates', 'exam:read', 'handleCandidatesList', null],
    ['GET', '/v1/candidates/{id}', 'exam:read', 'handleCandidateGet', null],
    ['POST', '/v1/candidates', 'exam:write', 'handleCandidateCreate', 'candidates:create'],
    ['GET', '/v1/candidates/{id}/paper', 'exam:read', 'handleCandidatePaper', null],
    ['POST', '/v1/candidates/{id}/submissions', 'exam:write', 'handleCandidateSubmit', 'candidates:submit'],
    ['DELETE', '/v1/candidates/{id}', 'exam:write', 'handleCandidateDelete', 'candidates:delete'],
    ['GET', '/v1/matrix/usernames/{name}/availability', 'exam:read', 'handleMatrixUsernameAvailability', null],
    ['GET', '/v1/blacklist', 'blacklist:read', 'handleBlacklistList', null],
    ['POST', '/v1/blacklist', 'blacklist:write', 'handleBlacklistCreate', 'blacklist:create'],
    ['PUT', '/v1/blacklist/{id}', 'blacklist:write', 'handleBlacklistUpdate', 'blacklist:update'],
    ['DELETE', '/v1/blacklist/{id}', 'blacklist:write', 'handleBlacklistDelete', 'blacklist:delete'],
];

$path = apiRequestPath();
$segments = apiPathSegments($path);

if (!preg_match('#^/v1(/|$)#', $path)) {
    apiError(404, 'not_found', '接口版本不存在。');
}

// 先按路径收集允许的 HTTP 方法（用于 OPTIONS 与 405）
$allowedMethods = [];
foreach (API_V1_ROUTES as $route) {
    if (apiRoutePatternMatches($route[1], $segments)) {
        $allowedMethods[] = $route[0];
    }
}
$allowedMethods = array_values(array_unique($allowedMethods));

if ($allowedMethods === []) {
    apiError(404, 'not_found', '资源或路径不存在。');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    header('Allow: ' . implode(', ', $allowedMethods));
    apiNoContent();
}

if (!in_array($method, $allowedMethods, true)) {
    header('Allow: ' . implode(', ', $allowedMethods));
    apiError(405, 'method_not_allowed', '该路径不支持 ' . $method . ' 方法。');
}

$match = apiMatchRoute($method, $segments);
[$routeMethod, $pattern, $scope, $handler, $auditAction] = $match['route'];

try {
    if ($scope === 'public') {
        // 公共端点（健康检查 / 元数据）：免认证，不写审计日志
        call_user_func($handler, $match['params']);
    } else {
        $auth = apiRequireAuth();
        apiRequireScope($auth, $scope);

        if ($auditAction !== null) {
            auditLog(getPDO(), $auth['actor'], 'api:v1:' . $auditAction, apiAuditTarget($auditAction, $match['params']));
        }

        call_user_func($handler, $match['params']);
    }
} catch (Throwable $e) {
    error_log('API 内部错误：' . $e->getMessage());
    apiError(500, 'internal_error', '服务器内部错误，请稍后重试。');
}

// ---------- 路由辅助 ----------

// 解析请求路径：优先使用重写规则传入的 path 参数，其次使用 PATH_INFO
function apiRequestPath(): string
{
    if (isset($_GET['path']) && is_string($_GET['path']) && $_GET['path'] !== '') {
        $path = '/' . ltrim($_GET['path'], '/');
    } else {
        $path = (string)($_SERVER['PATH_INFO'] ?? '');
    }

    $path = preg_replace('/[?#].*$/', '', $path) ?? '';
    return '/' . ltrim($path, '/');
}

// 将路径拆分为分段数组
function apiPathSegments(string $path): array
{
    return array_values(array_filter(explode('/', $path), 'strlen'));
}

// 判断路径模式是否与分段匹配（{id} 视为通配）
function apiRoutePatternMatches(string $pattern, array $segments): bool
{
    $patternSegments = array_values(array_filter(explode('/', $pattern), 'strlen'));
    if (count($patternSegments) !== count($segments)) {
        return false;
    }
    foreach ($patternSegments as $i => $segment) {
        if ($segment[0] !== '{' && $segment !== $segments[$i]) {
            return false;
        }
    }
    return true;
}

// 匹配指定方法的路径，返回 ['route' => 路由行, 'params' => 路径参数（按占位符名作为键）]；静态路径优先于 {id} 占位
function apiMatchRoute(string $method, array $segments): ?array
{
    $candidates = [];
    foreach (API_V1_ROUTES as $route) {
        if ($route[0] !== $method || !apiRoutePatternMatches($route[1], $segments)) {
            continue;
        }

        $patternSegments = array_values(array_filter(explode('/', $route[1]), 'strlen'));
        $params = [];
        $literal = true;
        foreach ($patternSegments as $i => $segment) {
            if (preg_match('/^\{(\w+)\}$/', $segment, $matches)) {
                $literal = false;
                $params[$matches[1]] = $segments[$i];
            }
        }

        $candidates[] = ['route' => $route, 'params' => $params, 'literal' => $literal];
    }

    if ($candidates === []) {
        return null;
    }

    usort($candidates, static function ($a, $b) {
        return ($a['literal'] ? 0 : 1) <=> ($b['literal'] ? 0 : 1);
    });

    return $candidates[0];
}

// 构造审计日志的目标描述
function apiAuditTarget(string $auditAction, array $params): ?string
{
    $id = $params['id'] ?? null;
    if ($id === null) {
        return null;
    }
    if (strpos($auditAction, 'candidates') === 0) {
        return '候选人 #' . $id;
    }
    return '#' . $id;
}

// ---------- 统计 ----------

function handleStatsSummary(array $params): void
{
    apiRespond(getStatsSummary());
}

// ---------- 题目 ----------

function handleQuestionsList(array $params): void
{
    $filters = [
        'category' => apiEnum('category', QUESTION_CATEGORIES, ''),
        'type' => apiEnum('type', QUESTION_TYPES, ''),
        'q' => apiString('q', '', 100),
    ];
    $pagination = apiPaginationParams(20);
    $data = listQuestions($filters, $pagination['page'], $pagination['per_page']);
    apiPaginationLinkHeader($pagination['page'], $pagination['per_page'], $data['pages']);
    apiRespond($data);
}

function handleQuestionsGet(array $params): void
{
    $question = getQuestion((int)($params['id'] ?? 0));
    if ($question === null) {
        apiError(404, 'not_found', '题目不存在。');
    }
    apiRespond($question);
}

function handleQuestionsCreate(array $params): void
{
    $validated = validateQuestionInput(apiBody());
    if (isset($validated['errors'])) {
        apiError(422, 'validation_failed', implode(' ', $validated['errors']));
    }
    $result = createQuestion($validated['data']);
    if (isset($result['error'])) {
        apiError(409, 'duplicate', $result['error']);
    }
    header('Location: ' . apiBaseUrl() . '/v1/questions/' . $result['id']);
    apiRespond($result, 201);
}

function handleQuestionsUpdate(array $params): void
{
    $id = (int)($params['id'] ?? 0);
    $validated = validateQuestionInput(apiBody());
    if (isset($validated['errors'])) {
        apiError(422, 'validation_failed', implode(' ', $validated['errors']));
    }
    $result = updateQuestion($id, $validated['data']);
    if (isset($result['error'])) {
        apiError($result['error'] === '题目不存在。' ? 404 : 409, $result['error'] === '题目不存在。' ? 'not_found' : 'duplicate', $result['error']);
    }
    apiRespond($result);
}

function handleQuestionsDelete(array $params): void
{
    if (!deleteQuestion((int)($params['id'] ?? 0))) {
        apiError(404, 'not_found', '题目不存在。');
    }
    apiNoContent();
}

function handleQuestionsImport(array $params): void
{
    $markdown = apiString('markdown', '', 200000);
    $category = apiEnum('category', QUESTION_CATEGORIES, '');
    $skipDuplicates = apiBool('skip_duplicates', true);
    $dryRun = apiBool('dry_run', false);

    if ($category === '') {
        apiError(422, 'validation_failed', '请先选择分类。');
    }
    if ($markdown === '') {
        apiError(422, 'validation_failed', '请粘贴要导入的 Markdown 表格。');
    }

    $parsed = parseMarkdownQuestionTable($markdown);
    if ($parsed['rows'] === []) {
        apiError(422, 'validation_failed', '未能在粘贴内容中识别出表格行。请确认表头为 | 题干 | A | B | C | D | 答案 | 类型 | 命题人 |。');
    }

    // 逐行校验
    $rows = [];
    $validCount = 0;
    $validationErrors = [];
    foreach ($parsed['rows'] as $index => $parsedRow) {
        $cells = $parsedRow['cells'];
        if (isset($cells['category']) && trim($cells['category']) !== '') {
            $cells['category'] = trim($cells['category']);
        } else {
            $cells['category'] = $category;
        }
        $validated = validateImportedQuestionRow($cells, $category);
        if ($validated['valid']) {
            $validCount++;
            $rows[] = $validated['data'];
        } else {
            $validationErrors[] = ['row' => $index + 1, 'raw' => implode(' | ', $parsedRow['raw']), 'errors' => $validated['errors']];
        }
    }

    if ($dryRun) {
        apiRespond([
            'total' => count($parsed['rows']),
            'valid' => $validCount,
            'invalid' => count($validationErrors),
            'errors' => array_slice($validationErrors, 0, 50),
        ]);
    }

    if ($validCount === 0) {
        apiError(422, 'validation_failed', '没有可以导入的有效行。');
    }

    $result = importQuestions($rows, $skipDuplicates);
    apiRespond([
        'total' => count($parsed['rows']),
        'imported' => $result['inserted'],
        'skipped' => $result['skipped'],
        'failed' => $result['failed'],
        'errors' => $result['errors'],
    ]);
}

function handleQuestionsExport(array $params): void
{
    $filters = [
        'category' => apiEnum('category', QUESTION_CATEGORIES, ''),
        'type' => apiEnum('type', QUESTION_TYPES, ''),
    ];
    apiRespond(['markdown' => exportQuestionsMarkdown($filters)]);
}

// ---------- 测试记录 ----------

function handleResultsList(array $params): void
{
    $filters = [
        'q' => apiString('q', '', 100),
        'status' => apiEnum('status', ['pass', 'fail'], ''),
        'date_from' => apiString('date_from', '', 10),
        'date_to' => apiString('date_to', '', 10),
    ];
    $pagination = apiPaginationParams(20);
    $data = listResults(apiString('channel', 'forum'), $filters, $pagination['page'], $pagination['per_page']);
    apiPaginationLinkHeader($pagination['page'], $pagination['per_page'], $data['pages']);
    apiRespond($data);
}

function handleResultsGet(array $params): void
{
    $detail = getResultDetail(apiString('channel', 'forum'), (int)($params['id'] ?? 0));
    if ($detail === null) {
        apiError(404, 'not_found', '记录不存在。');
    }
    apiRespond($detail);
}

function handleResultsDelete(array $params): void
{
    if (!deleteResult(apiString('channel', 'forum'), (int)($params['id'] ?? 0))) {
        apiError(404, 'not_found', '记录不存在。');
    }
    apiNoContent();
}

// ---------- 用户 ----------

function handleUsersList(array $params): void
{
    $filters = [
        'q' => apiString('q', '', 100),
        'date_from' => apiString('date_from', '', 10),
        'date_to' => apiString('date_to', '', 10),
    ];
    $pagination = apiPaginationParams(20);
    $data = listUsers(apiString('channel', 'forum'), $filters, $pagination['page'], $pagination['per_page']);
    apiPaginationLinkHeader($pagination['page'], $pagination['per_page'], $data['pages']);
    apiRespond($data);
}

function handleUsersGet(array $params): void
{
    $detail = getUserDetail(apiString('channel', 'forum'), (int)($params['id'] ?? 0));
    if ($detail === null) {
        apiError(404, 'not_found', '用户不存在。');
    }
    apiRespond($detail);
}

function handleUsersDelete(array $params): void
{
    if (!deleteUserRecord(apiString('channel', 'forum'), (int)($params['id'] ?? 0))) {
        apiError(404, 'not_found', '用户不存在。');
    }
    apiNoContent();
}

// ---------- API 密钥 ----------

function handleKeysList(array $params): void
{
    apiRespond(['items' => listApiKeys()]);
}

function handleKeysCreate(array $params): void
{
    $name = apiString('name', '', 50);
    if ($name === '') {
        apiError(422, 'validation_failed', '密钥名称不能为空。');
    }
    $scopes = array_values(array_filter((array)(apiBody()['scopes'] ?? []), 'is_string'));
    if ($scopes === []) {
        apiError(422, 'validation_failed', '请至少选择一个作用域。');
    }
    $result = createApiKey($name, $scopes, apiInt('expiry_days', 0, 0, 3650));
    if (isset($result['error'])) {
        apiError(422, 'validation_failed', $result['error']);
    }
    header('Location: ' . apiBaseUrl() . '/v1/keys/' . $result['row']['id']);
    apiRespond(['key' => $result['plain'], 'row' => $result['row']], 201);
}

function handleKeysUpdate(array $params): void
{
    $id = (int)($params['id'] ?? 0);
    $enabled = apiBool('enabled', false);
    if (!updateApiKeyEnabled($id, $enabled)) {
        apiError(404, 'not_found', '密钥不存在。');
    }
    apiRespond(['id' => $id, 'enabled' => $enabled]);
}

function handleKeysDelete(array $params): void
{
    if (!deleteApiKey((int)($params['id'] ?? 0))) {
        apiError(404, 'not_found', '密钥不存在。');
    }
    apiNoContent();
}

// ---------- 审计日志 ----------

function handleAuditList(array $params): void
{
    $filters = [
        'q' => apiString('q', '', 100),
        'action' => apiString('action', '', 50),
        'date_from' => apiString('date_from', '', 10),
        'date_to' => apiString('date_to', '', 10),
    ];
    $pagination = apiPaginationParams(20);
    $data = listAuditLogs($filters, $pagination['page'], $pagination['per_page']);
    apiPaginationLinkHeader($pagination['page'], $pagination['per_page'], $data['pages']);
    apiRespond($data);
}

// ---------- 系统信息 ----------

function handleSystemInfo(array $params): void
{
    apiRespond(getSystemInfo());
}

// ---------- 健康检查 / 元数据（公共，免认证） ----------

function handleHealth(array $params): void
{
    try {
        getPDO(true)->query('SELECT 1');
        $db = 'ok';
    } catch (Throwable $e) {
        $db = 'error';
    }

    $payload = ['status' => $db === 'ok' ? 'ok' : 'degraded', 'version' => VERSION, 'db' => $db, 'time' => date('Y-m-d H:i:s')];
    apiRespond($payload, $db === 'ok' ? 200 : 503);
}

function handleMeta(array $params): void
{
    apiRespond([
        'version' => VERSION,
        'channels' => [
            'forum' => [
                'enabled' => !(bool)FORUM_CLOSED,
                'duration_minutes' => (int)EXAM_REMAIN_TIME,
                'score_threshold' => (int)SCORE_THRESHOLD,
                'score_correct' => (int)SCORE_CORRECT_QUESTION,
                'score_partial' => (int)SCORE_PARTIAL_MULTIPLE_QUESTION,
            ],
            'matrix' => [
                'enabled' => (bool)MATRIX_ENABLED && !(bool)MATRIX_CLOSED,
                'instance_name' => MATRIX_INSTANCE_NAME,
                'duration_minutes' => (int)MATRIX_EXAM_REMAIN_TIME,
                'score_threshold' => (int)MATRIX_SCORE_THRESHOLD,
                'score_correct' => (int)MATRIX_SCORE_CORRECT_QUESTION,
                'score_partial' => (int)MATRIX_SCORE_PARTIAL_MULTIPLE_QUESTION,
            ],
        ],
        'question_categories' => QUESTION_CATEGORIES,
        'question_types' => QUESTION_TYPES,
    ]);
}

// ---------- 考试流程（候选人 / 试卷 / 交卷） ----------

function handleCandidatesList(array $params): void
{
    $channel = apiEnum('channel', ['forum', 'matrix'], '');
    if ($channel === '') {
        apiError(422, 'validation_failed', '请选择测试通道（channel：forum 或 matrix）。');
    }

    $filters = [
        'q' => apiString('q', '', 100),
        'status' => apiEnum('status', ['registered', 'paper_generated', 'submitted', 'not_started', 'in_progress', 'abandoned', 'pass', 'fail'], ''),
        'date_from' => apiString('date_from', '', 10),
        'date_to' => apiString('date_to', '', 10),
    ];
    $pagination = apiPaginationParams(20);
    $data = listCandidates($channel, $filters, $pagination['page'], $pagination['per_page']);
    apiPaginationLinkHeader($pagination['page'], $pagination['per_page'], $data['pages']);
    apiRespond($data);
}

function handleCandidateGet(array $params): void
{
    $channel = apiEnum('channel', ['forum', 'matrix'], '');
    if ($channel === '') {
        apiError(422, 'validation_failed', '请选择测试通道（channel：forum 或 matrix）。');
    }

    $candidateId = (int)($params['id'] ?? 0);
    $detail = getCandidateDetail($channel, $candidateId);
    if ($detail === null) {
        apiError(404, 'not_found', '候选人不存在。');
    }

    $detail['paper'] = getExamPaper($channel, $candidateId);

    apiRespond($detail);
}

function handleCandidateDelete(array $params): void
{
    $channel = apiEnum('channel', ['forum', 'matrix'], '');
    if ($channel === '') {
        apiError(422, 'validation_failed', '请选择测试通道（channel：forum 或 matrix）。');
    }
    if (!deleteUserRecord($channel, (int)($params['id'] ?? 0))) {
        apiError(404, 'not_found', '候选人不存在。');
    }
    apiNoContent();
}

function handleCandidateCreate(array $params): void
{
    $channel = apiEnum('channel', ['forum', 'matrix'], '');
    if ($channel === '') {
        apiError(422, 'validation_failed', '请选择测试通道（channel：forum 或 matrix）。');
    }

    $username = apiString('username', '', 254);
    $email = apiString('email', '', 254);

    if ($channel === 'forum') {
        $categories = array_values(array_filter((array)(apiBody()['categories'] ?? []), 'is_string'));
        $mxid = apiString('matrix_oauth_mxid', '', 255);
        if ($mxid !== '' && !isValidMxid($mxid)) {
            apiError(422, 'validation_failed', 'matrix_oauth_mxid 不是合法的 Matrix 账号 ID。');
        }
        $result = registerForumCandidate($username, $email, $categories, $mxid !== '' ? $mxid : null);
    } else {
        $forumOauthUserId = apiInt('forum_oauth_user_id', 0, 1);
        $result = registerMatrixCandidate($username, $email, $forumOauthUserId > 0 ? $forumOauthUserId : null);
    }

    if (isset($result['error'])) {
        if ($result['error']['code'] === 'blacklisted' && !empty($result['blacklist'])) {
            // API 请求无法展示安全验证页：直接以请求头中的 UA 记录设备信息后拦截
            recordBlacklistDevice((int)$result['blacklist']['entry_id'], (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), null, null);
        }
        $statusMap = ['username_in_use' => 409, 'mas_unavailable' => 503, 'not_found' => 404, 'blacklisted' => 403];
        apiError($statusMap[$result['error']['code']] ?? 422, $result['error']['code'], $result['error']['message']);
    }
    if (!empty($result['errors'])) {
        apiError(422, 'validation_failed', implode(' ', $result['errors']));
    }

    $paperResult = buildExamPaper($channel, $result['candidate']['id']);
    if (isset($paperResult['error'])) {
        apiError(422, $paperResult['error']['code'], $paperResult['error']['message']);
    }

    header('Location: ' . apiBaseUrl() . '/v1/candidates/' . $result['candidate']['id'] . '/paper');
    apiRespond([
        'candidate' => $result['candidate'],
        'paper' => $paperResult['paper'],
        'restarted' => !empty($result['restarted']),
        'matrix_oauth_mxid' => $result['matrix_oauth_mxid'] ?? null,
        'forum_oauth_exempt' => $result['forum_oauth_exempt'] ?? null,
        'username_notice' => $result['username_notice'] ?? null,
    ], 201);
}

function handleCandidatePaper(array $params): void
{
    $channel = apiEnum('channel', ['forum', 'matrix'], 'forum');
    $paper = getExamPaper($channel, (int)($params['id'] ?? 0));
    if ($paper === null) {
        apiError(404, 'not_found', '候选人不存在或尚未开始测试。');
    }
    apiRespond($paper);
}

function handleCandidateSubmit(array $params): void
{
    $channel = apiEnum('channel', ['forum', 'matrix'], 'forum');

    $answers = [];
    foreach ((array)(apiBody()['answers'] ?? []) as $qid => $value) {
        $id = (int)$qid;
        if ($id <= 0) {
            continue;
        }
        $answers[$id] = array_slice(
            array_values(array_filter(is_array($value) ? array_map('strval', $value) : [(string)$value], 'strlen')),
            0,
            4
        );
    }

    $paperId = apiInt('paper_id', 0, 1);

    $submission = scoreSubmission($channel, (int)($params['id'] ?? 0), $answers, $paperId > 0 ? $paperId : null);
    if (isset($submission['error'])) {
        if ($submission['error']['code'] === 'blacklisted' && !empty($submission['blacklist'])) {
            // API 请求无法展示安全验证页：直接以请求头中的 UA 记录设备信息后拦截
            recordBlacklistDevice((int)$submission['blacklist']['entry_id'], (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), null, null);
        }
        $statusMap = ['not_found' => 404, 'time_violation' => 409, 'no_paper' => 409, 'stale_paper' => 409, 'blacklisted' => 403];
        apiError($statusMap[$submission['error']['code']] ?? 422, $submission['error']['code'], $submission['error']['message']);
    }

    apiRespond($submission, 201);
}

// ---------- Matrix 用户名可用性 ----------

function handleMatrixUsernameAvailability(array $params): void
{
    $username = normalizeMatrixUsername((string)($params['name'] ?? ''));

    if (!isValidMatrixUsername($username)) {
        apiRespond(['username' => $username, 'available' => false, 'reason' => 'invalid_username']);
        return;
    }

    $inUse = masUsernameInUse($username);
    if ($inUse === null) {
        apiRespond(['username' => $username, 'available' => false, 'reason' => 'mas_unavailable']);
        return;
    }

    apiRespond(['username' => $username, 'available' => !$inUse, 'reason' => $inUse ? 'in_use' : 'available']);
}

// ---------- 黑名单 ----------

function handleBlacklistList(array $params): void
{
    $filters = [
        'q' => apiString('q', '', 100),
    ];
    $pagination = apiPaginationParams(20);
    $data = listBlacklistEntries($filters, $pagination['page'], $pagination['per_page']);
    apiPaginationLinkHeader($pagination['page'], $pagination['per_page'], $data['pages']);
    apiRespond($data);
}

function handleBlacklistCreate(array $params): void
{
    $email = apiString('email', '', 254);
    $ips = array_values(array_filter((array)(apiBody()['ips'] ?? []), 'is_string'));
    $reason = apiString('reason', '', 255);

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        apiError(422, 'validation_failed', '电子邮箱地址格式不正确。');
    }
    if (normalizeBlacklistIps($ips) === []) {
        apiError(422, 'validation_failed', 'IP 地址列表为空或均不合法。');
    }

    $result = createBlacklistEntry($email, $ips, $reason);
    if (isset($result['error'])) {
        apiError(409, 'duplicate', $result['error']);
    }

    header('Location: ' . apiBaseUrl() . '/v1/blacklist/' . $result['id']);
    apiRespond(getBlacklistEntry($result['id']), 201);
}

function handleBlacklistUpdate(array $params): void
{
    $id = (int)($params['id'] ?? 0);
    $ips = array_values(array_filter((array)(apiBody()['ips'] ?? []), 'is_string'));
    $reason = apiString('reason', '', 255);

    if (getBlacklistEntry($id) === null) {
        apiError(404, 'not_found', '黑名单条目不存在。');
    }
    if (normalizeBlacklistIps($ips) === []) {
        apiError(422, 'validation_failed', 'IP 地址列表为空或均不合法。');
    }

    updateBlacklistEntry($id, $ips, $reason);
    apiRespond(getBlacklistEntry($id));
}

function handleBlacklistDelete(array $params): void
{
    if (!deleteBlacklistEntry((int)($params['id'] ?? 0))) {
        apiError(404, 'not_found', '黑名单条目不存在。');
    }
    apiNoContent();
}
