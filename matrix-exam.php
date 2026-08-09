<?php
require_once 'config.php';
require_once 'includes/security.php';
require_once 'includes/exam-service.php';
require_once 'includes/oauth.php';
require_once './vendor/autoload.php';

initSecurity();

if (DB_TIMEZONE_LOCK) {
} else {
    date_default_timezone_set(PHP_TIMEZONE);
}

$matrixError = null;
$usernameNotice = null;
$user = null;
$questions = [];
$forumOauthExempt = false;

if (MATRIX_CLOSED) {
} elseif (!MATRIX_ENABLED) {
    $matrixError = '注册' . MATRIX_INSTANCE_NAME . '的测试通道尚未开启。更多详情请查看社区论坛和联邦宇宙官宣账号。';
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $turnstile = verifyTurnstileToken((string)($_POST['cf-turnstile-response'] ?? ''));

    if ($turnstile['error'] !== null) {
        $matrixError = $turnstile['error'];
    } elseif ($turnstile['warning'] !== null) {
        $matrixError = $turnstile['warning'];
    }

    if ($matrixError === null) {
        $email = trim((string)($_POST['email'] ?? ''));

        // 已通过论坛账号 OAuth 验证且邮箱一致的用户，免考礼仪测试
        $forumOauth = forumOAuthVerified();
        $forumOauthUserId = null;
        if ($forumOauth !== null && oauthEmailsMatch($forumOauth['email'], $email)) {
            $forumOauthUserId = (int)$forumOauth['user_id'];
        }

        $result = registerMatrixCandidate(
            (string)($_POST['username'] ?? ''),
            $email,
            $forumOauthUserId
        );

        if (isset($result['error'])) {
            $matrixError = $result['error']['message'];
        } elseif (!empty($result['errors'])) {
            $matrixError = implode(' ', $result['errors']);
        } else {
            $user = $result['candidate'];
            $usernameNotice = $result['username_notice'];
            $forumOauthExempt = $result['forum_oauth_exempt'];

            $paperResult = buildExamPaper('matrix', (int)$user['id']);
            if (isset($paperResult['error'])) {
                $matrixError = $paperResult['error']['message'];
            } else {
                $questions = $paperResult['paper']['questions'];
            }
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
    <title>答卷 - <?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>礼仪测试</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./views/assets/css/noto-face.css">
    <link rel="stylesheet" href="./views/assets/css/tokens.css">
    <script>
        function startTimer(duration, display) {
            var timer = duration,
                minutes, seconds;
            setInterval(function() {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = minutes + ":" + seconds;

                if (--timer < 0) {
                    document.getElementById("matrixExamForm").submit();
                }
            }, 1000);
        }

        window.onload = function() {
            var duration = <?php echo htmlspecialchars(MATRIX_EXAM_REMAIN_TIME); ?> * 60,
                display = document.querySelector('#timer');
            startTimer(duration, display);
        };
    </script>
</head>

<?php
require './views/nav.php';
if (MATRIX_CLOSED) {
    echo '<div class="alert alert-warning" role="alert">测试通道已关闭，原因：' . MATRIX_CLOSED_REASON . '更多详情请查看社区论坛和联邦宇宙官宣账号。</div>';
    include './views/footer.php';
    exit;
} elseif ($forumOauthExempt && $user !== null && empty($questions)) {
?>
    <div class="page page-narrow mx-auto">
        <div class="card form-card mt-4">
            <div class="card-body">
                <h1 class="page-title">礼仪测试免考确认</h1>
                <p class="card-text">你已通过论坛账号验证，将免考<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>礼仪测试并直接获得满分，随后系统会为你发放注册 Token。</p>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th scope="col">用户 ID</th>
                            <th scope="col">登记用户名</th>
                            <th scope="col">电子邮件地址</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?></th>
                            <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <form action="matrix-result.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="oauth_exempt" value="1">
                    <button type="submit" class="btn btn-primary btn-lg w-100">确认并获取注册 Token</button>
                </form>
                <a class="btn btn-outline-secondary mt-2 w-100" href="info.php" role="button">返回信息登记</a>
            </div>
        </div>
    </div>
<?php
    include './views/footer.php';
    exit;
} elseif (!MATRIX_ENABLED || $matrixError !== null || empty($questions)) {
?>
    <div class="page page-narrow mx-auto">
        <div class="card form-card mt-4">
            <div class="card-body">
                <h1 class="page-title"><?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>礼仪测试</h1>
                <?php if ($matrixError !== null) : ?>
                    <div class="alert alert-danger" role="alert"><?php echo nl2br(htmlspecialchars($matrixError)); ?></div>
                <?php else : ?>
                    <div class="alert alert-warning" role="alert">请先前往信息登记页面选择注册<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>并填写信息，再开始礼仪测试。</div>
                <?php endif; ?>
                <a class="btn btn-primary" href="info.php" role="button">返回信息登记</a>
            </div>
        </div>
    </div>
<?php
    include './views/footer.php';
    exit;
}
?>
<div class="exam-bar">
    <div class="container">
        <div class="exam-bar-inner">
            <h1 class="exam-title">答卷</h1>
            <span class="badge bg-secondary" id="timer" role="timer" aria-label="剩余时间"></span>
        </div>
    </div>
</div>
<div class="page page-tight mx-auto">
    <?php if ($usernameNotice !== null) : ?>
        <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($usernameNotice); ?></div>
    <?php endif; ?>
    <div class="card mb-4">
        <div class="card-body">
            <h3 class="section-head">测试信息</h3>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th scope="col">用户 ID</th>
                        <th scope="col">登记用户名</th>
                        <th scope="col">电子邮件地址</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row"><?php echo htmlspecialchars(isset($user['id']) ? $user['id'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></th>
                        <td><?php echo htmlspecialchars(isset($user['username']) ? $user['username'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(isset($user['email']) ? $user['email'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<form id="matrixExamForm" action="matrix-result.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <?php
    $questionNumber = 1; // 初始化题号
    foreach ($questions as $question) {
        echo '
                    <div class="card question-card">
                        <div class="card-body">
                            <h5 class="question-text">' . htmlspecialchars($questionNumber) . '. ' . htmlspecialchars($question['question_text']) . '</h5>';
        echo '
                            <input type="hidden" name="user_id" value="' . htmlspecialchars(isset($user['id']) ? $user['id'] : '', ENT_QUOTES, 'UTF-8') . '">';
        echo '
                            <input type="hidden" name="question_' . htmlspecialchars($question['id']) . '" value="' . htmlspecialchars($question['id']) . '">';

        if ($question['type'] === 'single') {
            for ($i = 'A'; $i <= 'D'; $i++) {
                echo '
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answer_' . htmlspecialchars($question['id']) . '" value="' . htmlspecialchars($i) . '" id="singleCheck' . htmlspecialchars($question['id']) . '_' . htmlspecialchars($i) . '">
                                <label class="form-check-label" for="singleCheck' . htmlspecialchars($question['id']) . '_' . htmlspecialchars($i) . '">
                                    ' . htmlspecialchars($i) . '. ' . htmlspecialchars($question['option_' . strtolower($i)]) . '
                                </label>
                            </div>';
            }
        } else { // multiple
            for ($i = 'A'; $i <= 'D'; $i++) {
                echo '
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="answer_' . htmlspecialchars($question['id']) . '[]" value="' . htmlspecialchars($i) . '" id="multipleCheck' . htmlspecialchars($question['id']) . '_' . htmlspecialchars($i) . '">
                                <label class="form-check-label" for="multipleCheck' . htmlspecialchars($question['id']) . '_' . htmlspecialchars($i) . '">
                                    ' . htmlspecialchars($i) . '. ' . htmlspecialchars($question['option_' . strtolower($i)]) . '
                                </label>
                            </div>';
            }
        }

        echo '
                        </div>
                    </div>';
        $questionNumber++; // 增加题号
    }
    ?>
    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg">提交</button>
    </div>
</form>
</div>
<?php require './views/footer.php'; ?>
