<?php

require_once './includes/db.php';
require_once './includes/version.php';
require_once './includes/security.php';
require_once './includes/exam-service.php';

initSecurity();

$timeCheatDetected = false;
$etiquetteExempt = false;
$resultError = null;
$score = null;
$invitationCode = null;
$userId = null;

// Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('CSRF 验证失败。请刷新页面后重试。');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $paperId = (int)($_POST['paper_id'] ?? 0);

    $submission = scoreSubmission('forum', $userId, extractAnswerMap($_POST), $paperId > 0 ? $paperId : null);

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
        $invitationCode = $submission['credential']['value'];
        $etiquetteExempt = $submission['etiquette_exempt'];
    }
}

sendSecurityHeaders();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>结果 - 十八桥社区入站测试系统</title>
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
                            <?php else: ?>
                                <?php if ($etiquetteExempt) : ?>
                                    <div class="alert alert-success mt-4" role="alert">礼仪测试免考：你已通过 Matrix 账号验证，基本礼仪题部分获得满分。</div>
                                <?php endif; ?>
                                <h5 class="section-head mt-4">测试已完成。</h5>
                                <p class="card-text">你的分数是：<strong class="fs-2"><?php echo htmlspecialchars($score); ?></strong></p>
                                <p class="result-meta mb-1">你的邀请码是：</p>
                                <div class="code-box"><?php echo htmlspecialchars(is_string($invitationCode) ? $invitationCode : '错误：返回内容的类型不是字符串。这有可能是邀请码 API 出现了错误，请截屏并联系管理邮箱获取邀请码。', ENT_QUOTES, 'UTF-8'); ?></div>
                                <p class="card-text">如果对此结果有任何问题，请截屏此页面然后向<a
                                        href="javascript:location.href = 'mailto:' + ['<?php echo htmlspecialchars(ADMIN_EMAIL_NAME); ?>','<?php echo htmlspecialchars(ADMIN_EMAIL_DOMAIN); ?>'].join('@')">管理邮箱</a>发送电子邮件（被测试者 ID：<strong><?php echo htmlspecialchars(isset($userId) ? $userId : '', ENT_QUOTES, 'UTF-8'); ?></strong>）。</p>
                                <div class="alert alert-warning mt-4" role="alert">邀请码只会显示一次，请立即写下（或安全地保存）它。邀请码转让给他人是绝对禁止的，这会导致账号被封禁。</div>
                                <a href="https://www.bridge18.us.kg/" class="btn btn-primary btn-lg mt-2">去注册</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
<?php require './views/footer.php'; ?>
