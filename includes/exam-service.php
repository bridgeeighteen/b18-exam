<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/blacklist.php';
require_once __DIR__ . '/../includes/matrix-api.php';
require_once __DIR__ . '/../includes/version.php';

// 考试业务层：候选人登记、试卷生成与持久化、计分与凭据发放。
// 供面向用户的页面（exam.php / matrix-exam.php / result.php / matrix-result.php）
// 与系统 REST API（api/index.php）共用，保证网页与 API 行为一致。

const EXAM_FORUM_ETIQUETTE_COUNT = 10; // 论坛测试的基本礼仪题数量
const EXAM_FORUM_BASE_COUNT = 15; // 论坛测试的自选组合题数量
const EXAM_CATEGORY_LIST = ['IT', 'ACGN', 'Virtual_Singer', 'Broadcasting']; // 可选的基类

// ---------- Cloudflare Turnstile ----------

// 服务器端校验 Turnstile 验证码。
// 返回 ['ok' => bool, 'error' => ?string, 'warning' => ?string]：
// - error 非空表示验证失败，必须拒绝提交；
// - warning 非空（仅测试密钥场景）表示可以继续，但应提示管理员更换密钥。
function verifyTurnstileToken(string $token): array
{
    if ($token === '') {
        return [
            'ok' => false,
            'error' => "入站测试系统使用 Cloudflare Turnstile 验证码，而你提交的信息表单中缺少用于服务器端验证的值。\n"
                . '这表明你在填写基本信息时 Turnstile 验证框未正常加载，或者浏览器因不支持 JavaScript 或版本太过老旧而不支持 Turnstile。' . "\n"
                . '为了防止账号滥用，请尝试重新填写基本信息，或者更换设备/浏览器。',
            'warning' => null,
        ];
    }

    if (!class_exists('FluxSoft\Turnstile\Turnstile')) {
        return ['ok' => false, 'error' => 'Turnstile 验证组件未加载，请刷新页面后重试。', 'warning' => null];
    }

    $verifyResponse = (new FluxSoft\Turnstile\Turnstile(CF_TURNSTILE_SECRET))->verify($token, getClientIP());

    if ($verifyResponse->success) {
        return ['ok' => true, 'error' => null, 'warning' => null];
    }

    if ($verifyResponse->hasErrors()) {
        $messages = [];
        foreach ($verifyResponse->errorCodes as $errorCode) {
            $messages[] = 'Turnstile 服务器端验证失败：' . $errorCode . "\n如果问题依旧存在，你可能需要通过管理邮箱联系我们或者向源代码仓库创建 Issues 以报告此问题。";
        }
        return ['ok' => false, 'error' => implode("\n", $messages), 'warning' => null];
    }

    if (CF_TURNSTILE_SITEKEY == '1x00000000000000000000AA' && CF_TURNSTILE_SECRET == '1x0000000000000000000000000000000AA') {
        return ['ok' => true, 'error' => null, 'warning' => '警告：你正在使用配置模板提供的测试用 Turnstile 密钥。如果你决定将系统用于生产环境，请前往 Cloudflare 仪表板创建一对新密钥。'];
    }

    return [
        'ok' => false,
        'error' => 'Turnstile 服务器端验证失败，但类型未知。' . "\n如果问题依旧存在，你可能需要通过管理邮箱联系我们或者向源代码仓库创建 Issues 以报告此问题。",
        'warning' => null,
    ];
}

// ---------- 候选人登记 ----------

// 登记论坛测试候选人（信息登记页与 POST /v1/candidates 共用）。
// $matrixMxid 非空表示已通过 Matrix 账号 OAuth 验证，礼仪题免考（仅由会话或受信任的调用方传入）。
// 返回 ['candidate' => array] 或 ['errors' => array] / ['error' => ['code' =>, 'message' =>]]。
function registerForumCandidate(string $username, string $email, array $categories, ?string $matrixMxid = null): array
{
    $username = trim($username);
    $email = trim($email);
    $categories = array_values(array_unique(array_filter($categories, 'is_string')));

    $errors = [];
    if ($username === '') {
        $errors[] = '用户名不能为空。';
    } elseif (mb_strlen($username) > 254) {
        $errors[] = '用户名过长（最多 254 个字符）。';
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = '电子邮件地址格式不正确。请返回信息登记页面重新填写。';
    }
    if (count($categories) !== 2) {
        $errors[] = '选择的基类只能为两个。你的信息未被上传至数据库，请返回并重新填写。';
    }
    foreach ($categories as $category) {
        if (!in_array($category, EXAM_CATEGORY_LIST, true)) {
            $errors[] = '选择的基类无效。';
            break;
        }
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }

    if (checkBlacklist($email, getClientIP()) !== null) {
        return ['error' => ['code' => 'blacklisted', 'message' => '检测到该邮箱或 IP 地址已被列入黑名单，无法参加测试。如有疑问，请通过管理邮箱联系我们。']];
    }

    $db = getPDO();
    $selected = implode(',', $categories);

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = (int)$existing['id'];
        if ($matrixMxid !== null) {
            $stmt = $db->prepare('UPDATE users SET selected_categories = ?, matrix_oauth_mxid = ?, matrix_oauth_verified_at = NOW() WHERE id = ?');
            $stmt->execute([$selected, $matrixMxid, $userId]);
        } else {
            $stmt = $db->prepare('UPDATE users SET selected_categories = ? WHERE id = ?');
            $stmt->execute([$selected, $userId]);
        }
    } else {
        if ($matrixMxid !== null) {
            $stmt = $db->prepare('INSERT INTO users (username, email, selected_categories, matrix_oauth_mxid, matrix_oauth_verified_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$username, $email, $selected, $matrixMxid]);
        } else {
            $stmt = $db->prepare('INSERT INTO users (username, email, selected_categories) VALUES (?, ?, ?)');
            $stmt->execute([$username, $email, $selected]);
        }
        $userId = (int)$db->lastInsertId();
    }

    $stmt = $db->prepare('SELECT id, username, email, selected_categories, matrix_oauth_mxid, start_time FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['error' => ['code' => 'not_found', 'message' => '错误：用户信息未找到。请立即通过管理邮箱报告此问题。']];
    }

    return [
        'candidate' => [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'email' => (string)$row['email'],
            'selected_categories' => (string)$row['selected_categories'],
            'matrix_oauth_mxid' => $row['matrix_oauth_mxid'] !== null ? (string)$row['matrix_oauth_mxid'] : null,
        ],
        'matrix_oauth_mxid' => $matrixMxid,
    ];
}

// 登记 Matrix（千万桥）测试候选人（matrix-exam.php 与 POST /v1/candidates 共用）。
// $forumOauthUserId 非空表示已通过论坛账号 OAuth 验证，礼仪测试免考（仅由会话或受信任的调用方传入）。
// 返回 ['candidate' => array, 'forum_oauth_exempt' => bool, 'username_notice' => ?string]
// 或 ['errors' => array] / ['error' => ['code' =>, 'message' =>]]。
function registerMatrixCandidate(string $username, string $email, ?int $forumOauthUserId = null): array
{
    $username = normalizeMatrixUsername($username);
    $email = trim($email);

    $errors = [];
    if (!isValidMatrixUsername($username)) {
        $errors[] = '用户名仅能包含小写字母、数字以及 . _ = - 符号，且长度不能超过 254 个字符。请返回信息登记页面重新填写。';
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = '电子邮件地址格式不正确。请返回信息登记页面重新填写。';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }

    if (checkBlacklist($email, getClientIP()) !== null) {
        return ['error' => ['code' => 'blacklisted', 'message' => '检测到该邮箱或 IP 地址已被列入黑名单，无法参加测试。如有疑问，请通过管理邮箱联系我们。']];
    }

    $db = getPDO();

    $inUse = masUsernameInUse($username);
    if ($inUse === true) {
        return ['error' => ['code' => 'username_in_use', 'message' => '该用户名已在' . MATRIX_INSTANCE_NAME . '上实际注册使用，无法继续使用。请返回信息登记页面更换一个用户名。']];
    }
    if ($inUse === null) {
        return ['error' => ['code' => 'mas_unavailable', 'message' => '无法连接 Matrix Authentication Service 以核验用户名是否可用。请稍后重试，或者通过管理邮箱联系我们并向源代码仓库创建 Issues 以报告此问题。']];
    }

    $usernameNotice = null;
    $stmt = $db->prepare('SELECT id FROM matrix_users WHERE username = ?');
    $stmt->execute([$username]);
    $existing = $stmt->fetch();

    if ($existing) {
        $userId = (int)$existing['id'];
        $usernameNotice = '该用户名此前曾在本系统登记过。你可以继续使用该用户名，也可以在后续实际注册时稍作调整。';
        if ($forumOauthUserId !== null) {
            $stmt = $db->prepare('UPDATE matrix_users SET forum_oauth_user_id = ?, forum_oauth_verified_at = NOW() WHERE id = ?');
            $stmt->execute([$forumOauthUserId, $userId]);
        }
    } else {
        if ($forumOauthUserId !== null) {
            $stmt = $db->prepare('INSERT INTO matrix_users (username, email, forum_oauth_user_id, forum_oauth_verified_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$username, $email, $forumOauthUserId]);
        } else {
            $stmt = $db->prepare('INSERT INTO matrix_users (username, email) VALUES (?, ?)');
            $stmt->execute([$username, $email]);
        }
        $userId = (int)$db->lastInsertId();
    }

    $stmt = $db->prepare('SELECT id, username, email, forum_oauth_user_id, start_time FROM matrix_users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['error' => ['code' => 'not_found', 'message' => '错误：用户信息未找到。请立即通过管理邮箱报告此问题。']];
    }

    return [
        'candidate' => [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'email' => (string)$row['email'],
            'forum_oauth_user_id' => $row['forum_oauth_user_id'] !== null ? (int)$row['forum_oauth_user_id'] : null,
        ],
        'forum_oauth_exempt' => $forumOauthUserId !== null,
        'username_notice' => $usernameNotice,
    ];
}

// ---------- 试卷 ----------

// 按通道与候选人生成并持久化试卷，同时记录开始时间。
// 返回 ['paper' => array] 或 ['error' => ['code' =>, 'message' =>]]。
function buildExamPaper(string $channel, int $candidateId): array
{
    $db = getPDO();
    $channel = $channel === 'matrix' ? 'matrix' : 'forum';

    $candidate = getCandidate($channel, $candidateId);
    if ($candidate === null) {
        return ['error' => ['code' => 'not_found', 'message' => '错误：用户信息未找到。请立即通过管理邮箱报告此问题。']];
    }

    $questions = [];
    $exempt = false;
    $exemptIds = [];

    if ($channel === 'forum') {
        $selectedCategories = array_filter(array_map('trim', explode(',', (string)($candidate['selected_categories'] ?? ''))));
        $exempt = !empty($candidate['matrix_oauth_mxid']);

        if ($exempt) {
            $exemptIds = randomQuestionIdsByCategory('Etiquette', EXAM_FORUM_ETIQUETTE_COUNT);
        } else {
            $etiquette = fetchQuestionsByCategory('Etiquette');
            if (count($etiquette) < EXAM_FORUM_ETIQUETTE_COUNT) {
                return ['error' => ['code' => 'insufficient_questions', 'message' => '题库中的基本礼仪题数量不足。请立即通过管理邮箱报告此问题。']];
            }
            shuffle($etiquette);
            $questions = array_merge($questions, array_slice($etiquette, 0, EXAM_FORUM_ETIQUETTE_COUNT));
        }

        $base = [];
        foreach ($selectedCategories as $category) {
            if (!in_array($category, EXAM_CATEGORY_LIST, true)) {
                continue;
            }
            $base = array_merge($base, fetchQuestionsByCategory($category));
        }
        if (count($base) < EXAM_FORUM_BASE_COUNT) {
            return ['error' => ['code' => 'insufficient_questions', 'message' => '题库中的自选组合题数量不足。请立即通过管理邮箱报告此问题。']];
        }
        shuffle($base);
        $questions = array_merge($questions, array_slice($base, 0, EXAM_FORUM_BASE_COUNT));
    } else {
        $exempt = (int)($candidate['forum_oauth_user_id'] ?? 0) > 0;
        if (!$exempt) {
            $etiquette = fetchQuestionsByCategory('Etiquette');
            if (count($etiquette) < (int)MATRIX_QUESTION_COUNT) {
                return ['error' => ['code' => 'insufficient_questions', 'message' => '题库中的基本礼仪题数量不足。请立即通过管理邮箱报告此问题。']];
            }
            shuffle($etiquette);
            $questions = array_slice($etiquette, 0, (int)MATRIX_QUESTION_COUNT);
        }
    }

    $questionIds = array_map('intval', array_column($questions, 'id'));
    $paperId = saveExamPaper($channel, $candidateId, $questionIds, $exempt, $exemptIds);
    startExamTimer($channel, $candidateId);

    return ['paper' => formatExamPaper($channel, $candidate, $paperId, $questions, $exempt, $exemptIds)];
}

// 读取候选人最近一份试卷（无试卷时返回 null）
function getExamPaper(string $channel, int $candidateId): ?array
{
    $channel = $channel === 'matrix' ? 'matrix' : 'forum';
    $db = getPDO();

    $stmt = $db->prepare('SELECT * FROM exam_papers WHERE channel = ? AND candidate_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$channel, $candidateId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    $candidate = getCandidate($channel, $candidateId);
    if ($candidate === null) {
        return null;
    }

    $exempt = (int)$row['etiquette_exempt'] === 1;
    $exemptIds = decodeIdList((string)$row['etiquette_exempt_ids']);
    $questions = fetchQuestionsByIds(decodeIdList((string)$row['question_ids']));

    return formatExamPaper($channel, $candidate, (int)$row['id'], $questions, $exempt, $exemptIds);
}

// 格式化试卷的公开结构（不含答案，避免泄露）
function formatExamPaper(string $channel, array $candidate, int $paperId, array $questions, bool $exempt, array $exemptIds): array
{
    $duration = $channel === 'matrix' ? (int)MATRIX_EXAM_REMAIN_TIME : (int)EXAM_REMAIN_TIME;

    return [
        'id' => $paperId,
        'channel' => $channel,
        'candidate' => [
            'id' => (int)$candidate['id'],
            'username' => (string)$candidate['username'],
            'email' => (string)$candidate['email'],
        ],
        'questions' => array_map('formatPublicQuestion', $questions),
        'etiquette_exempt' => $exempt,
        'etiquette_exempt_ids' => array_values(array_map('intval', $exemptIds)),
        'started_at' => date('Y-m-d H:i:s'),
        'duration_minutes' => $duration,
    ];
}

// 公开的题目结构（不含答案字段）
function formatPublicQuestion(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'category' => (string)$row['category'],
        'type' => (string)$row['type'],
        'question_text' => (string)$row['question_text'],
        'option_a' => (string)$row['option_a'],
        'option_b' => (string)$row['option_b'],
        'option_c' => (string)$row['option_c'],
        'option_d' => (string)$row['option_d'],
    ];
}

// ---------- 计分与提交 ----------

// 提交答卷并计分：返回 ['score', 'passed', 'etiquette_exempt', 'credential']，
// 或 ['error' => ['code' =>, 'message' =>]]（如超时作弊 / 未生成试卷 / 用户不存在）。
function scoreSubmission(string $channel, int $candidateId, array $answers): array
{
    $channel = $channel === 'matrix' ? 'matrix' : 'forum';

    $candidate = getCandidate($channel, $candidateId);
    if ($candidate === null) {
        return ['error' => ['code' => 'not_found', 'message' => '错误：用户信息未找到。请立即通过管理邮箱报告此问题。']];
    }

    if (checkBlacklist((string)$candidate['email'], getClientIP()) !== null) {
        return ['error' => ['code' => 'blacklisted', 'message' => '检测到该邮箱或 IP 地址已被列入黑名单，无法提交测试。如有疑问，请通过管理邮箱联系我们。']];
    }

    $stmt = getPDO()->prepare('SELECT * FROM exam_papers WHERE channel = ? AND candidate_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$channel, $candidateId]);
    $paperRow = $stmt->fetch();
    if ($paperRow === false) {
        return ['error' => ['code' => 'no_paper', 'message' => '尚未生成试卷，请先在信息登记页面登记并开始测试。']];
    }

    $etiquetteExempt = (int)$paperRow['etiquette_exempt'] === 1;
    $questionIds = decodeIdList((string)$paperRow['question_ids']);
    $exemptIds = decodeIdList((string)$paperRow['etiquette_exempt_ids']);

    if ($channel === 'matrix' && $etiquetteExempt) {
        // 免考流程不进行时间作弊检测，直接获得满分
        $score = (int)MATRIX_QUESTION_COUNT * (int)MATRIX_SCORE_CORRECT_QUESTION;
    } else {
        if (checkTimeViolation($channel, $candidateId)) {
            return ['error' => ['code' => 'time_violation', 'message' => '你违反了测试的时间限制。你的信息已被删除，请重新开始测试。']];
        }
        $score = gradeAnswers($channel, $questionIds, $exemptIds, $answers);
    }

    $passed = $score >= ($channel === 'matrix' ? (int)MATRIX_SCORE_THRESHOLD : (int)SCORE_THRESHOLD);
    $credential = $channel === 'matrix' ? issueRegistrationToken($score) : issueInvitationCode($score);

    storeSubmission($channel, $candidateId, $score, $credential['value']);

    return [
        'score' => $score,
        'passed' => $passed,
        'etiquette_exempt' => $etiquetteExempt,
        'credential' => $credential,
    ];
}

// 按试卷题目计分：免考礼仪题直接满分，其余题目依据提交的答案评分
// 规则：
// - 单选题：提交内容与标准答案完全一致得满分；
// - 多选题：含错选得 0 分；所选与正确答案完全一致得满分；所选均为正确选项但不全得部分分。
function gradeAnswers(string $channel, array $questionIds, array $exemptIds, array $answers): int
{
    $fullScore = $channel === 'matrix' ? (int)MATRIX_SCORE_CORRECT_QUESTION : (int)SCORE_CORRECT_QUESTION;
    $partialScore = $channel === 'matrix' ? (int)MATRIX_SCORE_PARTIAL_MULTIPLE_QUESTION : (int)SCORE_PARTIAL_MULTIPLE_QUESTION;

    $total = 0;

    foreach (array_map('intval', $exemptIds) as $qid) {
        $total += $fullScore;
    }

    foreach (fetchQuestionsByIds($questionIds) as $question) {
        $qid = (int)$question['id'];

        $submitted = array_values(array_unique(array_map('strtoupper', array_map('trim', array_values(array_filter($answers[$qid] ?? [], 'is_string'))))));
        $submitted = array_values(array_filter($submitted, 'strlen'));
        if ($submitted === []) {
            continue;
        }

        // 规范化标准答案为大写字母集合，兼容 "A"、"AB"、"A,B"、"A, B"、"（A）" 等写法
        $normalizedAnswer = str_replace(['(', ')', '（', '）', '，', ',', ' '], '', strtoupper((string)$question['answer']));
        $correctLetters = array_values(array_unique(preg_split('//u', $normalizedAnswer, -1, PREG_SPLIT_NO_EMPTY)));

        if ($question['type'] === 'multiple') {
            // 多选题：含错选得 0 分；所选与正确答案一致得满分；所选均为正确选项但不全得部分分
            $numIncorrect = count(array_diff($submitted, $correctLetters));
            if ($numIncorrect > 0) {
                continue;
            }
            $complete = count($submitted) === count($correctLetters) && count(array_intersect($submitted, $correctLetters)) === count($correctLetters);
            $total += $complete ? $fullScore : $partialScore;
        } else {
            // 单选题：提交内容与标准答案完全一致得满分
            $correct = str_replace(['(', ')', '（', '）', '，', ',', ' '], '', strtoupper((string)$question['answer']));
            if (implode(',', $submitted) === $correct) {
                $total += $fullScore;
            }
        }
    }

    return $total;
}

// 检查是否超时（超过测试时长），超时则删除该候选人及其测试记录并返回 true。
// 通过 UNIX_TIMESTAMP 在数据库会话时区下取开始时间的绝对时间戳，
// 避免 PHP 与数据库时区不一致或 strtotime 解析失败导致的误判。
function checkTimeViolation(string $channel, int $candidateId): bool
{
    $candidate = getCandidate($channel, $candidateId);
    if ($candidate === null || empty($candidate['start_time'])) {
        return false;
    }

    $table = $channel === 'matrix' ? 'matrix_users' : 'users';
    $stmt = getPDO()->prepare("SELECT UNIX_TIMESTAMP(start_time) FROM $table WHERE id = ?");
    $stmt->execute([$candidateId]);
    $startTs = $stmt->fetchColumn();
    if ($startTs === false || $startTs === null) {
        return false;
    }

    $duration = $channel === 'matrix' ? (int)MATRIX_EXAM_REMAIN_TIME : (int)EXAM_REMAIN_TIME;
    $expectedEnd = (int)$startTs + $duration * 60;
    if (time() <= $expectedEnd) {
        return false;
    }

    $db = getPDO();
    if ($channel === 'matrix') {
        $db->prepare('DELETE FROM matrix_results WHERE user_id = ?')->execute([$candidateId]);
        $db->prepare('DELETE FROM matrix_users WHERE id = ?')->execute([$candidateId]);
    } else {
        $db->prepare('DELETE FROM results WHERE user_id = ?')->execute([$candidateId]);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$candidateId]);
    }

    return true;
}

// 保存测试结果
function storeSubmission(string $channel, int $candidateId, int $score, string $credential): void
{
    $db = getPDO();
    if ($channel === 'matrix') {
        $stmt = $db->prepare('INSERT INTO matrix_results (user_id, score, end_time, registration_token) VALUES (?, ?, NOW(), ?)');
        $stmt->execute([$candidateId, $score, $credential]);
    } else {
        $stmt = $db->prepare('INSERT INTO results (user_id, score, end_time, invitation_code) VALUES (?, ?, NOW(), ?)');
        $stmt->execute([$candidateId, $score, $credential]);
    }
}

// ---------- 凭据发放 ----------

// 发放论坛邀请码：达到分数线时调用 Flarum 邀请码 API
function issueInvitationCode(int $score): array
{
    if ($score < (int)SCORE_THRESHOLD) {
        return ['type' => 'invitation_code', 'issued' => false, 'value' => '无（未达到条件）', 'error' => null];
    }

    $key = fetchDoorKey(generateRandomString());
    if ($key === null) {
        return [
            'type' => 'invitation_code',
            'issued' => false,
            'value' => '',
            'error' => '邀请码生成失败：外部邀请码 API 未返回有效结果。请截屏结果页并联系管理邮箱获取邀请码。',
        ];
    }

    return ['type' => 'invitation_code', 'issued' => true, 'value' => $key, 'error' => null];
}

// 发放 Matrix 注册 Token：达到分数线时调用 MAS 管理 API
function issueRegistrationToken(int $score): array
{
    if ($score < (int)MATRIX_SCORE_THRESHOLD) {
        return ['type' => 'registration_token', 'issued' => false, 'value' => '无（未达到条件）', 'error' => null];
    }

    $issued = masCreateRegistrationToken(generateMatrixToken());
    if ($issued === null) {
        return [
            'type' => 'registration_token',
            'issued' => false,
            'value' => '错误：Token 生成失败',
            'error' => '注册 Token 生成失败：Matrix Authentication Service 未返回有效结果。请截屏结果页并联系管理邮箱获取 Token。',
        ];
    }

    return ['type' => 'registration_token', 'issued' => true, 'value' => $issued, 'error' => null];
}

// 生成邀请码前缀+随机串（如 B18R@XXXX1234）
function generateRandomString(int $length = 8): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charsArray = [];
    for ($i = 0; $i < $length; $i++) {
        $charsArray[] = $characters[random_int(0, strlen($characters) - 1)];
    }
    return CODE_TYPE . '@' . implode('', $charsArray);
}

// 调用 Flarum 的 FoF Doorman 邀请码 API，成功返回邀请码字符串，失败返回 null
function fetchDoorKey(string $key): ?string
{
    $url = 'https://' . API_SITE . '/api/fof/doorkeys';
    $headers = [
        'Content-Type: application/json; charset=UTF-8',
        'Authorization: Token ' . API_X_CSRF_TOKEN,
        'User-Agent: b18-exam/' . VERSION . ' b18-codeget-php/1.0.0',
    ];

    $payload = [
        'data' => [
            'type' => 'doorkeys',
            'attributes' => [
                'key' => $key,
                'groupId' => GROUP_ID,
                'maxUses' => MAX_USES,
                'activates' => ACTIVATES,
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log('cURL 错误：' . $error);
        return null;
    }

    if ($statusCode !== 201) {
        error_log('API 返回异常的 HTTP 状态码：' . $statusCode);
        return null;
    }

    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('收到了未知的 JSON 回应：' . $response);
        return null;
    }

    if (!isset($responseData['data']['attributes']['key'])) {
        error_log('Unexpected response structure: ' . print_r($responseData, true));
        return null;
    }

    return $responseData['data']['attributes']['key'];
}

// 从表单 / JSON 请求体中提取答题映射：['qid' => ['A', 'B']]
function extractAnswerMap(array $post): array
{
    $answers = [];
    foreach ($post as $key => $value) {
        if (preg_match('/^answer_(\d+)$/', (string)$key, $matches) !== 1) {
            continue;
        }
        $qid = (int)$matches[1];
        if (!is_array($value)) {
            $value = [$value];
        }
        $answers[$qid] = array_values(array_filter(array_map('strval', $value), 'strlen'));
    }
    return $answers;
}

// ---------- 数据访问辅助 ----------

// 读取候选人原始行（channel: forum | matrix），不存在返回 null
function getCandidate(string $channel, int $candidateId): ?array
{
    $table = $channel === 'matrix' ? 'matrix_users' : 'users';
    $stmt = getPDO()->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$candidateId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

// 持久化试卷，返回试卷 ID
function saveExamPaper(string $channel, int $candidateId, array $questionIds, bool $exempt, array $exemptIds): int
{
    $db = getPDO();
    $stmt = $db->prepare('INSERT INTO exam_papers (channel, candidate_id, question_ids, etiquette_exempt, etiquette_exempt_ids) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $channel,
        $candidateId,
        json_encode(array_values(array_map('intval', $questionIds)), JSON_UNESCAPED_UNICODE),
        $exempt ? 1 : 0,
        json_encode(array_values(array_map('intval', $exemptIds)), JSON_UNESCAPED_UNICODE),
    ]);
    return (int)$db->lastInsertId();
}

// 记录候选人开始时间
function startExamTimer(string $channel, int $candidateId): void
{
    $table = $channel === 'matrix' ? 'matrix_users' : 'users';
    getPDO()->prepare("UPDATE $table SET start_time = NOW() WHERE id = ?")->execute([$candidateId]);
}

// 按分类随机抽取指定数量的题目 ID
function randomQuestionIdsByCategory(string $category, int $count): array
{
    $stmt = getPDO()->prepare('SELECT id FROM questions WHERE category = ?');
    $stmt->execute([$category]);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    shuffle($ids);
    return array_slice($ids, 0, $count);
}

// 按分类取出全部题目行
function fetchQuestionsByCategory(string $category): array
{
    $stmt = getPDO()->prepare('SELECT * FROM questions WHERE category = ?');
    $stmt->execute([$category]);
    return $stmt->fetchAll();
}

// 按 ID 集合取出题目行并保持传入顺序
function fetchQuestionsByIds(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = getPDO()->prepare("SELECT * FROM questions WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int)$row['id']] = $row;
    }

    $ordered = [];
    foreach ($ids as $id) {
        if (isset($rows[$id])) {
            $ordered[] = $rows[$id];
        }
    }
    return $ordered;
}

// 解析以 JSON 数组形式存储的 ID 列表
function decodeIdList(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_map('intval', $decoded) : [];
}
