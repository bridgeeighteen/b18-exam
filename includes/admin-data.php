<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';

// 管理面板与 API 共用的数据访问层。

const QUESTION_CATEGORIES = ['IT', 'ACGN', 'Virtual_Singer', 'Broadcasting', 'Etiquette'];
const QUESTION_TYPES = ['single', 'multiple'];

// 数据统计摘要（仪表盘）
function getStatsSummary(): array
{
    $db = getPDO();

    $forum = [
        'users' => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'results' => (int)$db->query('SELECT COUNT(*) FROM results')->fetchColumn(),
    ];
    $stmt = $db->prepare('SELECT COUNT(*) FROM results WHERE score >= ?');
    $stmt->execute([(int)SCORE_THRESHOLD]);
    $forum['passed'] = (int)$stmt->fetchColumn();
    $stmt = $db->query('SELECT COALESCE(AVG(score), 0) FROM results');
    $forum['avg_score'] = round((float)$stmt->fetchColumn(), 1);
    $forum['pass_rate'] = $forum['results'] > 0 ? round($forum['passed'] / $forum['results'] * 100, 1) : 0.0;
    $forum['exempt_count'] = (int)$db->query('SELECT COUNT(*) FROM users WHERE matrix_oauth_mxid IS NOT NULL AND matrix_oauth_mxid != ""')->fetchColumn();

    $matrix = [
        'users' => (int)$db->query('SELECT COUNT(*) FROM matrix_users')->fetchColumn(),
        'results' => (int)$db->query('SELECT COUNT(*) FROM matrix_results')->fetchColumn(),
    ];
    $stmt = $db->prepare('SELECT COUNT(*) FROM matrix_results WHERE score >= ?');
    $stmt->execute([(int)MATRIX_SCORE_THRESHOLD]);
    $matrix['passed'] = (int)$stmt->fetchColumn();
    $stmt = $db->query('SELECT COALESCE(AVG(score), 0) FROM matrix_results');
    $matrix['avg_score'] = round((float)$stmt->fetchColumn(), 1);
    $matrix['pass_rate'] = $matrix['results'] > 0 ? round($matrix['passed'] / $matrix['results'] * 100, 1) : 0.0;
    $matrix['exempt_count'] = (int)$db->query('SELECT COUNT(*) FROM matrix_users WHERE forum_oauth_user_id IS NOT NULL')->fetchColumn();

    $questions = ['total' => 0, 'categories' => []];
    foreach ($db->query('SELECT category, COUNT(*) AS c FROM questions GROUP BY category') as $row) {
        $questions['categories'][$row['category']] = (int)$row['c'];
        $questions['total'] += (int)$row['c'];
    }

    $todayStart = date('Y-m-d 00:00:00');
    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE start_time >= ?');
    $stmt->execute([$todayStart]);
    $today = ['forum_registered' => (int)$stmt->fetchColumn()];
    $stmt = $db->prepare('SELECT COUNT(*) FROM matrix_users WHERE start_time >= ?');
    $stmt->execute([$todayStart]);
    $today['matrix_registered'] = (int)$stmt->fetchColumn();

    $recent = [
        'forum' => $db->query('SELECT r.id, r.score, r.end_time, u.username, u.email FROM results r JOIN users u ON u.id = r.user_id ORDER BY r.end_time DESC, r.id DESC LIMIT 8')->fetchAll(),
        'matrix' => $db->query('SELECT r.id, r.score, r.end_time, u.username, u.email FROM matrix_results r JOIN matrix_users u ON u.id = r.user_id ORDER BY r.end_time DESC, r.id DESC LIMIT 8')->fetchAll(),
    ];

    return [
        'forum' => $forum,
        'matrix' => $matrix,
        'questions' => $questions,
        'today' => $today,
        'recent' => $recent,
        'config' => [
            'forum_closed' => (bool)FORUM_CLOSED,
            'matrix_enabled' => (bool)MATRIX_ENABLED,
            'matrix_closed' => (bool)MATRIX_CLOSED,
            'forum_threshold' => (int)SCORE_THRESHOLD,
            'matrix_threshold' => (int)MATRIX_SCORE_THRESHOLD,
            'forum_duration' => (int)EXAM_REMAIN_TIME,
            'matrix_duration' => (int)MATRIX_EXAM_REMAIN_TIME,
        ],
    ];
}

// 题目列表
function listQuestions(array $filters, int $page, int $perPage): array
{
    $db = getPDO();

    $where = [];
    $params = [];

    if (!empty($filters['category']) && in_array($filters['category'], QUESTION_CATEGORIES, true)) {
        $where[] = 'category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['type']) && in_array($filters['type'], QUESTION_TYPES, true)) {
        $where[] = 'type = ?';
        $params[] = $filters['type'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(question_text LIKE ? OR author LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }

    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare('SELECT COUNT(*) FROM questions' . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT * FROM questions' . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->execute(array_merge($params, [$perPage, $offset = ($page - 1) * $perPage]));
    $items = array_map('formatQuestionRow', $stmt->fetchAll());

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)];
}

// 单题详情
function getQuestion(int $id): ?array
{
    $db = getPDO();
    $stmt = $db->prepare('SELECT * FROM questions WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : formatQuestionRow($row);
}

// 新建题目
function createQuestion(array $data): array
{
    $db = getPDO();

    if (questionExists($db, $data['question_text'], $data['category'])) {
        return ['error' => '相同分类下已存在相同题干的题目。'];
    }

    $stmt = $db->prepare('INSERT INTO questions (category, question_text, option_a, option_b, option_c, option_d, answer, type, author) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['category'],
        $data['question_text'],
        $data['option_a'],
        $data['option_b'],
        $data['option_c'],
        $data['option_d'],
        $data['answer'],
        $data['type'],
        $data['author'],
    ]);

    return ['id' => (int)$db->lastInsertId()];
}

// 更新题目
function updateQuestion(int $id, array $data): array
{
    $db = getPDO();

    if (getQuestion($id) === null) {
        return ['error' => '题目不存在。'];
    }

    if (questionExists($db, $data['question_text'], $data['category'], $id)) {
        return ['error' => '相同分类下已存在相同题干的题目。'];
    }

    $stmt = $db->prepare('UPDATE questions SET category = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, answer = ?, type = ?, author = ? WHERE id = ?');
    $stmt->execute([
        $data['category'],
        $data['question_text'],
        $data['option_a'],
        $data['option_b'],
        $data['option_c'],
        $data['option_d'],
        $data['answer'],
        $data['type'],
        $data['author'],
        $id,
    ]);

    return ['id' => $id];
}

// 删除题目
function deleteQuestion(int $id): bool
{
    $db = getPDO();
    $stmt = $db->prepare('DELETE FROM questions WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

// 批量导入题目（rows 为已校验数据），skipDuplicates 为 true 时跳过重复题
function importQuestions(array $rows, bool $skipDuplicates): array
{
    $db = getPDO();
    $inserted = 0;
    $skipped = 0;
    $failed = 0;
    $errors = [];

    foreach ($rows as $index => $row) {
        $validated = validateImportedQuestionRow($row, $row['category'] ?? '');
        // validateImportedQuestionRow 已在外部执行，此处仅处理重复检查
        if ($skipDuplicates && questionExists($db, $row['question_text'] ?? '', $row['category'] ?? '')) {
            $skipped++;
            continue;
        }
        if ($validated['valid'] !== true) {
            $failed++;
            $errors[] = '第 ' . ($index + 1) . ' 行：' . implode(' ', $validated['errors']);
            continue;
        }
        $result = createQuestion($validated['data']);
        if (isset($result['error'])) {
            $failed++;
            $errors[] = '第 ' . ($index + 1) . ' 行：' . $result['error'];
        } else {
            $inserted++;
        }
    }

    return ['inserted' => $inserted, 'skipped' => $skipped, 'failed' => $failed, 'errors' => array_slice($errors, 0, 50)];
}

// 导出题目为 Markdown 表格
function exportQuestionsMarkdown(array $filters): string
{
    $db = getPDO();

    $where = [];
    $params = [];

    if (!empty($filters['category']) && in_array($filters['category'], QUESTION_CATEGORIES, true)) {
        $where[] = 'category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['type']) && in_array($filters['type'], QUESTION_TYPES, true)) {
        $where[] = 'type = ?';
        $params[] = $filters['type'];
    }

    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare('SELECT * FROM questions' . $whereSql . ' ORDER BY category, id');
    $stmt->execute($params);
    $questions = $stmt->fetchAll();

    $lines = ['| 分类 | 题干 | A | B | C | D | 答案 | 类型 | 命题人 |', '| --- | --- | --- | --- | --- | --- | --- | --- | --- |'];
    foreach ($questions as $question) {
        $answer = str_replace(',', '', (string)$question['answer']);
        $lines[] = sprintf(
            '| %s | %s | %s | %s | %s | %s | %s | %s | %s |',
            str_replace('|', '\\|', (string)$question['category']),
            str_replace('|', '\\|', (string)$question['question_text']),
            str_replace('|', '\\|', (string)$question['option_a']),
            str_replace('|', '\\|', (string)$question['option_b']),
            str_replace('|', '\\|', (string)$question['option_c']),
            str_replace('|', '\\|', (string)$question['option_d']),
            $answer,
            $question['type'],
            str_replace('|', '\\|', (string)$question['author'])
        );
    }

    return implode("\n", $lines);
}

// 测试记录列表（channel: forum | matrix）
function listResults(string $channel, array $filters, int $page, int $perPage): array
{
    $db = getPDO();

    if ($channel === 'matrix') {
        $userTable = 'matrix_users';
        $resultTable = 'matrix_results';
        $codeField = 'registration_token';
        $threshold = (int)MATRIX_SCORE_THRESHOLD;
    } else {
        $channel = 'forum';
        $userTable = 'users';
        $resultTable = 'results';
        $codeField = 'invitation_code';
        $threshold = (int)SCORE_THRESHOLD;
    }

    $where = [];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(u.username LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['status']) && in_array($filters['status'], ['pass', 'fail'], true)) {
        $where[] = 'r.score ' . ($filters['status'] === 'pass' ? '>=' : '<') . ' ?';
        $params[] = $threshold;
    }
    if (!empty($filters['date_from']) && isValidDateFilter($filters['date_from'])) {
        $where[] = 'r.end_time >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to']) && isValidDateFilter($filters['date_to'])) {
        $where[] = 'r.end_time <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("SELECT COUNT(*) FROM $resultTable r JOIN $userTable u ON u.id = r.user_id" . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $stmt = $db->prepare(
        "SELECT r.id, r.score, r.end_time, r.$codeField AS code, u.id AS user_id, u.username, u.email "
        . "FROM $resultTable r JOIN $userTable u ON u.id = r.user_id" . $whereSql
        . ' ORDER BY r.end_time DESC, r.id DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute(array_merge($params, [$perPage, $offset]));

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['passed'] = (int)$row['score'] >= $threshold;
        $items[] = $row;
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)];
}

// 单个测试记录详情（含用户信息与全部历史记录）
function getResultDetail(string $channel, int $resultId): ?array
{
    $db = getPDO();

    if ($channel === 'matrix') {
        $userTable = 'matrix_users';
        $resultTable = 'matrix_results';
        $codeField = 'registration_token';
        $threshold = (int)MATRIX_SCORE_THRESHOLD;
    } else {
        $channel = 'forum';
        $userTable = 'users';
        $resultTable = 'results';
        $codeField = 'invitation_code';
        $threshold = (int)SCORE_THRESHOLD;
    }

    $stmt = $db->prepare(
        "SELECT r.id, r.score, r.end_time, r.$codeField AS code, u.* FROM $resultTable r JOIN $userTable u ON u.id = r.user_id WHERE r.id = ?"
    );
    $stmt->execute([$resultId]);
    $result = $stmt->fetch();

    if ($result === false) {
        return null;
    }

    $stmt = $db->prepare("SELECT id, score, end_time, $codeField AS code FROM $resultTable WHERE user_id = ? ORDER BY end_time DESC");
    $stmt->execute([$result['user_id']]);
    $history = $stmt->fetchAll();

    return [
        'channel' => $channel,
        'passed' => (int)$result['score'] >= $threshold,
        'user' => $result,
        'history' => $history,
    ];
}

// 删除测试记录
function deleteResult(string $channel, int $resultId): bool
{
    $db = getPDO();
    $table = $channel === 'matrix' ? 'matrix_results' : 'results';
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$resultId]);
    return $stmt->rowCount() > 0;
}

// 删除用户（其测试记录通过外键级联删除）
function deleteUserRecord(string $channel, int $userId): bool
{
    $db = getPDO();
    $table = $channel === 'matrix' ? 'matrix_users' : 'users';
    $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->rowCount() > 0;
}

// 用户列表（channel: forum | matrix）
function listUsers(string $channel, array $filters, int $page, int $perPage): array
{
    $db = getPDO();
    $channel = $channel === 'matrix' ? 'matrix' : 'forum';
    $table = $channel === 'matrix' ? 'matrix_users' : 'users';

    $where = [];
    $params = [];
    if (!empty($filters['q'])) {
        $where[] = '(username LIKE ? OR email LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['date_from']) && isValidDateFilter($filters['date_from'])) {
        $where[] = 'start_time >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to']) && isValidDateFilter($filters['date_to'])) {
        $where[] = 'start_time <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("SELECT COUNT(*) FROM $table" . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM $table" . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));

    return ['channel' => $channel, 'items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)];
}

// 单个用户详情（含其全部测试记录）
function getUserDetail(string $channel, int $userId): ?array
{
    $db = getPDO();

    if ($channel === 'matrix') {
        $userTable = 'matrix_users';
        $resultTable = 'matrix_results';
        $codeField = 'registration_token';
        $threshold = (int)MATRIX_SCORE_THRESHOLD;
    } else {
        $channel = 'forum';
        $userTable = 'users';
        $resultTable = 'results';
        $codeField = 'invitation_code';
        $threshold = (int)SCORE_THRESHOLD;
    }

    $stmt = $db->prepare("SELECT * FROM $userTable WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user === false) {
        return null;
    }

    $stmt = $db->prepare("SELECT id, score, end_time, $codeField AS code FROM $resultTable WHERE user_id = ? ORDER BY end_time DESC");
    $stmt->execute([$userId]);

    $history = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['passed'] = (int)$row['score'] >= $threshold;
        $history[] = $row;
    }

    return ['channel' => $channel, 'user' => $user, 'history' => $history];
}

// 候选人列表（channel: forum | matrix），status 为 registered / paper_generated / submitted
function listCandidates(string $channel, array $filters, int $page, int $perPage): array
{
    $db = getPDO();
    $channel = $channel === 'matrix' ? 'matrix' : 'forum';

    if ($channel === 'matrix') {
        $userTable = 'matrix_users';
        $resultTable = 'matrix_results';
        $codeField = 'registration_token';
        $threshold = (int)MATRIX_SCORE_THRESHOLD;
        $durationSec = (int)MATRIX_EXAM_REMAIN_TIME * 60;
        $channelFields = 'u.forum_oauth_user_id';
    } else {
        $userTable = 'users';
        $resultTable = 'results';
        $codeField = 'invitation_code';
        $threshold = (int)SCORE_THRESHOLD;
        $durationSec = (int)EXAM_REMAIN_TIME * 60;
        $channelFields = 'u.selected_categories, u.matrix_oauth_mxid';
    }

    // 状态判定 SQL 片段（SELECT 与 WHERE 共用），依据 start_time 与测试时长计算，
    // 使用数据库 UNIX_TIMESTAMP 保证时区一致
    $statusSql = "CASE WHEN r.id IS NOT NULL THEN 'submitted'"
        . " WHEN u.start_time IS NULL THEN 'not_started'"
        . " WHEN UNIX_TIMESTAMP(u.start_time) + $durationSec <= UNIX_TIMESTAMP(NOW()) THEN 'abandoned'"
        . " ELSE 'in_progress' END";

    $where = [];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(u.username LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['status']) && in_array($filters['status'], ['submitted', 'pass', 'fail', 'in_progress', 'abandoned', 'not_started', 'registered', 'paper_generated'], true)) {
        if ($filters['status'] === 'pass') {
            $where[] = 'r.id IS NOT NULL AND r.score >= ?';
            $params[] = $threshold;
        } elseif ($filters['status'] === 'fail') {
            $where[] = 'r.id IS NOT NULL AND r.score < ?';
            $params[] = $threshold;
        } elseif ($filters['status'] === 'submitted') {
            $where[] = 'r.id IS NOT NULL';
        } elseif ($filters['status'] === 'in_progress') {
            $where[] = "r.id IS NULL AND u.start_time IS NOT NULL AND UNIX_TIMESTAMP(u.start_time) + $durationSec > UNIX_TIMESTAMP(NOW())";
        } elseif ($filters['status'] === 'abandoned') {
            $where[] = "r.id IS NULL AND u.start_time IS NOT NULL AND UNIX_TIMESTAMP(u.start_time) + $durationSec <= UNIX_TIMESTAMP(NOW())";
        } elseif ($filters['status'] === 'paper_generated') {
            // 旧值兼容：已出卷未交卷（含进行中与中途退出）
            $where[] = 'r.id IS NULL AND u.start_time IS NOT NULL';
        } else {
            // not_started / registered：仅登记未开始
            $where[] = 'r.id IS NULL AND u.start_time IS NULL';
        }
    }
    if (!empty($filters['date_from']) && isValidDateFilter($filters['date_from'])) {
        $where[] = 'COALESCE(r.end_time, u.start_time) >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to']) && isValidDateFilter($filters['date_to'])) {
        $where[] = 'COALESCE(r.end_time, u.start_time) <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $latestResult = "SELECT id FROM $resultTable WHERE user_id = u.id ORDER BY end_time DESC, id DESC LIMIT 1";
    $latestPaper = "SELECT id FROM exam_papers WHERE channel = '$channel' AND candidate_id = u.id ORDER BY id DESC LIMIT 1";

    $fromSql = "$userTable u"
        . " LEFT JOIN $resultTable r ON r.id = ($latestResult)"
        . " LEFT JOIN exam_papers p ON p.id = ($latestPaper)";

    $stmt = $db->prepare("SELECT COUNT(*) FROM $fromSql" . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT u.id, u.username, u.email, u.start_time, $channelFields, "
        . "$statusSql AS exam_status, "
        . "r.id AS result_id, r.score AS latest_score, r.end_time, r.$codeField AS code, "
        . "(SELECT COUNT(*) FROM $resultTable WHERE user_id = u.id) AS attempts, "
        . "p.id AS paper_id "
        . "FROM $fromSql" . $whereSql . ' ORDER BY u.id DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $hasResult = $row['latest_score'] !== null;

        $item = [
            'id' => (int)$row['id'],
            'channel' => $channel,
            'username' => (string)$row['username'],
            'email' => (string)$row['email'],
            'start_time' => $row['start_time'],
            'status' => (string)$row['exam_status'],
            'attempts' => (int)$row['attempts'],
            'result_id' => $hasResult ? (int)$row['result_id'] : null,
            'exam_paper_id' => $row['paper_id'] !== null ? (int)$row['paper_id'] : null,
            'latest_score' => $hasResult ? (int)$row['latest_score'] : null,
            'passed' => $hasResult ? (int)$row['latest_score'] >= $threshold : null,
            'ended_at' => $hasResult ? $row['end_time'] : null,
            'code' => $hasResult ? (string)$row['code'] : null,
        ];

        if ($channel === 'forum') {
            $item['selected_categories'] = (string)$row['selected_categories'];
            $item['matrix_oauth_mxid'] = $row['matrix_oauth_mxid'] !== null ? (string)$row['matrix_oauth_mxid'] : null;
        } else {
            $item['forum_oauth_user_id'] = $row['forum_oauth_user_id'] !== null ? (int)$row['forum_oauth_user_id'] : null;
        }

        $items[] = $item;
    }

    return ['channel' => $channel, 'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)];
}

// 测试信息总览（admin/tests.php）的数据源：与 listCandidates 同一实现，
// 仅将条目键名 id 补充为 user_id，便于页面直接使用。
function listTestUsers(string $channel, array $filters, int $page, int $perPage): array
{
    $data = listCandidates($channel, $filters, $page, $perPage);
    foreach ($data['items'] as &$item) {
        $item['user_id'] = $item['id'];
    }
    unset($item);
    return $data;
}

// 单个候选人详情（基本信息 + 状态 + 最近成绩），候选人不存在返回 null。
// 试卷结构由调用方通过 getExamPaper() 补充。
function getCandidateDetail(string $channel, int $candidateId): ?array
{
    $db = getPDO();
    $channel = $channel === 'matrix' ? 'matrix' : 'forum';

    if ($channel === 'matrix') {
        $userTable = 'matrix_users';
        $resultTable = 'matrix_results';
        $codeField = 'registration_token';
        $threshold = (int)MATRIX_SCORE_THRESHOLD;
        $durationSec = (int)MATRIX_EXAM_REMAIN_TIME * 60;
    } else {
        $userTable = 'users';
        $resultTable = 'results';
        $codeField = 'invitation_code';
        $threshold = (int)SCORE_THRESHOLD;
        $durationSec = (int)EXAM_REMAIN_TIME * 60;
    }

    $stmt = $db->prepare("SELECT * FROM $userTable WHERE id = ?");
    $stmt->execute([$candidateId]);
    $user = $stmt->fetch();
    if ($user === false) {
        return null;
    }

    // 与 listCandidates 相同的状态判定：submitted / in_progress / abandoned / not_started
    $statusSql = "CASE WHEN r.id IS NOT NULL THEN 'submitted'"
        . " WHEN u.start_time IS NULL THEN 'not_started'"
        . " WHEN UNIX_TIMESTAMP(u.start_time) + $durationSec <= UNIX_TIMESTAMP(NOW()) THEN 'abandoned'"
        . " ELSE 'in_progress' END";
    $stmt = $db->prepare(
        "SELECT r.id, r.score, r.end_time, r.$codeField AS code, $statusSql AS exam_status "
        . "FROM $userTable u "
        . "LEFT JOIN $resultTable r ON r.id = (SELECT id FROM $resultTable WHERE user_id = u.id ORDER BY end_time DESC, id DESC LIMIT 1) "
        . "WHERE u.id = ?"
    );
    $stmt->execute([$candidateId]);
    $row = $stmt->fetch();

    $result = ($row['score'] !== null) ? $row : false;
    $status = (string)$row['exam_status'];

    $stmt = $db->prepare("SELECT COUNT(*) FROM $resultTable WHERE user_id = ?");
    $stmt->execute([$candidateId]);
    $attempts = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT id FROM exam_papers WHERE channel = ? AND candidate_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$channel, $candidateId]);
    $paperId = $stmt->fetchColumn();

    $detail = [
        'channel' => $channel,
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'start_time' => $user['start_time'],
        'status' => $status,
        'attempts' => $attempts,
        'exam_paper_id' => $paperId !== false ? (int)$paperId : null,
        'result' => null,
    ];

    if ($result !== false) {
        $detail['result'] = [
            'id' => (int)$result['id'],
            'score' => (int)$result['score'],
            'passed' => (int)$result['score'] >= $threshold,
            'end_time' => $result['end_time'],
            'code' => (string)$result['code'],
        ];
    }

    if ($channel === 'forum') {
        $detail['selected_categories'] = (string)$user['selected_categories'];
        $detail['matrix_oauth_mxid'] = $user['matrix_oauth_mxid'] !== null ? (string)$user['matrix_oauth_mxid'] : null;
    } else {
        $detail['forum_oauth_user_id'] = $user['forum_oauth_user_id'] !== null ? (int)$user['forum_oauth_user_id'] : null;
    }

    return $detail;
}

// API 密钥列表
function listApiKeys(): array
{
    $db = getPDO();
    $rows = $db->query('SELECT id, name, scopes, created_at, expires_at, last_used_at, enabled FROM api_keys ORDER BY id DESC')->fetchAll();
    foreach ($rows as &$row) {
        $row['enabled'] = (int)$row['enabled'] === 1;
        $row['expired'] = $row['expires_at'] !== null && strtotime($row['expires_at']) < time();
    }
    return $rows;
}

// 创建 API 密钥，返回 ['plain' => 明文密钥（仅此一次）, 'row' => 密钥记录] 或 ['error' => 原因]
function createApiKey(string $name, array $scopes, int $expiryDays): array
{
    $db = getPDO();
    $validScopes = array_values(array_intersect($scopes, API_ALLOWED_SCOPES));

    if ($validScopes === []) {
        return ['error' => '所选作用域均无效。'];
    }

    $plain = 'b18k_' . bin2hex(random_bytes(24));

    $stmt = $db->prepare('INSERT INTO api_keys (name, key_hash, scopes, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        mb_substr($name, 0, 50),
        hash('sha256', $plain),
        implode(',', $validScopes),
        $expiryDays > 0 ? date('Y-m-d H:i:s', time() + $expiryDays * 86400) : null,
    ]);

    $id = (int)$db->lastInsertId();
    $stmt = $db->prepare('SELECT id, name, scopes, created_at, expires_at, last_used_at, enabled FROM api_keys WHERE id = ?');
    $stmt->execute([$id]);

    return ['plain' => $plain, 'row' => $stmt->fetch()];
}

// 停用 / 启用 API 密钥
function updateApiKeyEnabled(int $id, bool $enabled): bool
{
    $db = getPDO();
    $stmt = $db->prepare('UPDATE api_keys SET enabled = ? WHERE id = ?');
    $stmt->execute([$enabled ? 1 : 0, $id]);
    return $stmt->rowCount() > 0;
}

// 删除 API 密钥
function deleteApiKey(int $id): bool
{
    $db = getPDO();
    $stmt = $db->prepare('DELETE FROM api_keys WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

// 审计日志列表
function listAuditLogs(array $filters, int $page, int $perPage): array
{
    $db = getPDO();

    $where = [];
    $params = [];

    if (!empty($filters['q'])) {
        $where[] = '(actor LIKE ? OR action LIKE ? OR target LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['action']) && preg_match('/^[a-zA-Z0-9:_-]+$/', $filters['action'])) {
        $where[] = 'action = ?';
        $params[] = $filters['action'];
    }
    if (!empty($filters['date_from']) && isValidDateFilter($filters['date_from'])) {
        $where[] = 'created_at >= ?';
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to']) && isValidDateFilter($filters['date_to'])) {
        $where[] = 'created_at <= ?';
        $params[] = $filters['date_to'] . ' 23:59:59';
    }

    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare('SELECT COUNT(*) FROM audit_log' . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT * FROM audit_log' . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));

    return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)];
}

// 系统信息（只读，不含任何敏感配置）
function getSystemInfo(): array
{
    return [
        'version' => VERSION,
        'php_version' => PHP_VERSION,
        'forum_closed' => (bool)FORUM_CLOSED,
        'matrix_enabled' => (bool)MATRIX_ENABLED,
        'matrix_closed' => (bool)MATRIX_CLOSED,
        'forum' => [
            'duration_minutes' => (int)EXAM_REMAIN_TIME,
            'score_threshold' => (int)SCORE_THRESHOLD,
            'score_correct' => (int)SCORE_CORRECT_QUESTION,
            'score_partial' => (int)SCORE_PARTIAL_MULTIPLE_QUESTION,
        ],
        'matrix' => [
            'instance_name' => MATRIX_INSTANCE_NAME,
            'duration_minutes' => (int)MATRIX_EXAM_REMAIN_TIME,
            'score_threshold' => (int)MATRIX_SCORE_THRESHOLD,
            'score_correct' => (int)MATRIX_SCORE_CORRECT_QUESTION,
            'score_partial' => (int)MATRIX_SCORE_PARTIAL_MULTIPLE_QUESTION,
        ],
    ];
}
