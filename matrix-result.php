<?php

require_once './includes/db.php';
require_once './includes/version.php';
require_once './includes/security.php';
require_once './includes/exam-service.php';
require_once './includes/oauth.php';

initSecurity();

$timeCheatDetected = false;
$tokenError = false;
$resultError = null;
$score = null;
$registrationToken = null;
$userId = null;
$oauthExempt = false;

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('CSRF 验证失败。请刷新页面后重试。');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $paperId = (int)($_POST['paper_id'] ?? 0);

    // 免考礼仪测试：仅当数据库注解（matrix_users.forum_oauth_user_id）与会话中的论坛用户一致时才可信
    if (isset($_POST['oauth_exempt']) && $_POST['oauth_exempt'] === '1') {
        $forumOauth = forumOAuthVerified();
        $stmt = getPDO()->prepare('SELECT forum_oauth_user_id FROM matrix_users WHERE id = ?');
        $stmt->execute([$userId]);
        $matrixUser = $stmt->fetch();
        $storedForumUserId = isset($matrixUser['forum_oauth_user_id']) ? (int)$matrixUser['forum_oauth_user_id'] : null;
        if ($forumOauth !== null && $storedForumUserId !== null && $storedForumUserId === (int)$forumOauth['user_id']) {
            $oauthExempt = true;
        } else {
            http_response_code(403);
            $resultError = '免考验证失败。请返回信息登记页面重新进行论坛账号登录，然后重试。';
        }
    }

    if ($resultError === null) {
        $submission = scoreSubmission('matrix', $userId, extractAnswerMap($_POST), $paperId > 0 ? $paperId : null);

        if (isset($submission['error'])) {
            if ($submission['error']['code'] === 'blacklisted' && !empty($submission['blacklist'])) {
                redirectToBlacklistVerification($submission['blacklist']);
            }
            if ($submission['error']['code'] === 'time_violation') {
                $timeCheatDetected = true;
            } else {
                $resultError = '错误：' . $submission['error']['message'];
            }
        } else {
            $score = $submission['score'];
            $registrationToken = $submission['credential']['value'];
            $oauthExempt = $submission['etiquette_exempt'];
            $tokenError = $submission['passed'] && $submission['credential']['error'] !== null;
        }
    }
}

sendSecurityHeaders();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>结果 - <?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>礼仪测试 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./views/assets/css/noto-face.css">
    <link rel="stylesheet" href="./views/assets/css/tokens.css">
</head>

<?php require './views/nav.php'; ?>
                <div class="page">
                <?php if ($resultError !== null) : ?>
                    <div class="card form-card mx-auto">
                        <div class="card-body">
                            <h1 class="page-title">无法获取结果</h1>
                            <div class="alert alert-danger mt-4" role="alert"><?php echo nl2br(htmlspecialchars($resultError)); ?></div>
                            <a class="btn btn-primary" href="info.php" role="button">返回信息登记</a>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="card result-card">
                        <div class="card-body">
                            <h1 class="page-title">测试结果</h1>
                            <?php if (isset($timeCheatDetected) && $timeCheatDetected) : ?>
                                <div class="alert alert-danger mt-4" role="alert">作弊检测：你违反了测试的时间限制。你的信息已被删除，请重新开始测试。</div>
                                <h5 class="section-head mt-4">测试失败。</h5>
                                <p class="card-text">系统检测到你在测试过程中有作弊行为。</p>
                                <p class="card-text">如果对此结果有任何问题，请截屏此页面然后向<a
                                href="javascript:location.href = 'mailto:' + ['<?php echo htmlspecialchars(ADMIN_EMAIL_NAME); ?>','<?php echo htmlspecialchars(ADMIN_EMAIL_DOMAIN); ?>'].join('@')">管理邮箱</a>发送电子邮件。</p>
                            <?php elseif (isset($tokenError) && $tokenError) : ?>
                                <h5 class="section-head mt-4">测试已完成，但 Token 生成失败。</h5>
                                <p class="card-text">你的分数是：<strong class="fs-2"><?php echo htmlspecialchars($score); ?></strong></p>
                                <p class="card-text">已达到通过分数阈值，但系统在向 Matrix Authentication Service 申请注册 Token 时遇到了问题，没有发放任何 Token。</p>
                                <p class="card-text">如果对此结果有任何问题，请截屏此页面然后向<a
                                        href="javascript:location.href = 'mailto:' + ['<?php echo htmlspecialchars(ADMIN_EMAIL_NAME); ?>','<?php echo htmlspecialchars(ADMIN_EMAIL_DOMAIN); ?>'].join('@')">管理邮箱</a>发送电子邮件（被测试者 ID：<strong><?php echo htmlspecialchars(isset($userId) ? $userId : '', ENT_QUOTES, 'UTF-8'); ?></strong>）。</p>
                            <?php else: ?>
                                <?php if ($oauthExempt) : ?>
                                    <div class="alert alert-success mt-4" role="alert">礼仪测试免考：你已通过论坛账号验证，获得满分。</div>
                                <?php endif; ?>
                                <h5 class="section-head mt-4">测试已完成。</h5>
                                <p class="card-text">你的分数是：<strong class="fs-2"><?php echo htmlspecialchars($score); ?></strong></p>
                                <p class="result-meta mb-1">你的注册 Token 是：</p>
                                <div class="code-box"><?php echo htmlspecialchars(is_string($registrationToken) ? $registrationToken : '错误：返回内容的类型不是字符串。这有可能是 Token 生成 API 出现了错误，请截屏并联系管理邮箱获取 Token。', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="alert alert-warning mt-4" role="alert">注册 Token 只会显示一次，请立即写下（或安全地保存）它。注册 Token 转让给他人是绝对禁止的，这会导致账号被封禁。</div>
                                <p class="card-text">如果对此结果有任何问题，请截屏此页面然后向<a
                                        href="javascript:location.href = 'mailto:' + ['<?php echo htmlspecialchars(ADMIN_EMAIL_NAME); ?>','<?php echo htmlspecialchars(ADMIN_EMAIL_DOMAIN); ?>'].join('@')">管理邮箱</a>发送电子邮件（被测试者 ID：<strong><?php echo htmlspecialchars(isset($userId) ? $userId : '', ENT_QUOTES, 'UTF-8'); ?></strong>）。</p>
                                <a href="<?php echo htmlspecialchars(MATRIX_REGISTER_URL); ?>" class="btn btn-primary btn-lg mt-2">去<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>注册</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
<?php require './views/footer.php'; ?>
