<?php
require_once 'config.php';
require_once 'includes/security.php';
require_once 'includes/oauth.php';
require_once 'includes/exam-service.php';
require_once './vendor/autoload.php';

initSecurity();

if (DB_TIMEZONE_LOCK) {
} else {
    date_default_timezone_set(PHP_TIMEZONE);
}

$matrixMxid = null;
$user = null;
$questions = [];
$examError = null;
$restarted = false;
$paperId = null;

if (FORUM_CLOSED) {
} else {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $turnstile = verifyTurnstileToken((string)($_POST['cf-turnstile-response'] ?? ''));

        if ($turnstile['error'] !== null) {
            $examError = $turnstile['error'];
        } elseif ($turnstile['warning'] !== null) {
            $examError = $turnstile['warning'];
        }

        // 已通过 Matrix 账号 OAuth 验证且登记邮箱为账号绑定邮箱的用户，免考基本礼仪题
        $matrixOauth = matrixOAuthVerified();
        $email = trim((string)($_POST['email'] ?? ''));
        if ($matrixOauth !== null && in_array(strtolower($email), $matrixOauth['emails'], true)) {
            $matrixMxid = $matrixOauth['mxid'];
        }

        if ($examError === null) {
            $result = registerForumCandidate(
                (string)($_POST['username'] ?? ''),
                $email,
                (array)($_POST['categories'] ?? []),
                $matrixMxid
            );

            if (isset($result['error'])) {
                $examError = '错误：' . $result['error']['message'];
            } elseif (!empty($result['errors'])) {
                $examError = '错误：' . implode(' ', $result['errors']);
            } else {
                $user = $result['candidate'];
                $restarted = !empty($result['restarted']);

                $paperResult = buildExamPaper('forum', (int)$user['id']);
                if (isset($paperResult['error'])) {
                    $examError = $paperResult['error']['message'];
                } else {
                    $questions = $paperResult['paper']['questions'];
                    $paperId = (int)$paperResult['paper']['id'];
                }
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
    <title>答卷 - 十八桥社区入站测试系统</title>
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
                    document.getElementById("examForm").submit();
                }
            }, 1000);
        }

        window.onload = function() {
            var duration = <?php echo htmlspecialchars(EXAM_REMAIN_TIME); ?> * 60,
                display = document.querySelector('#timer');
            startTimer(duration, display);
        };
    </script>
</head>

<?php
require './views/nav.php';
if (FORUM_CLOSED) {
    echo '<div class="alert alert-warning" role="alert">测试通道已关闭，原因：' . FORUM_CLOSED_REASON . ' 更多详情请查看社区论坛和联邦宇宙官宣账号。</div>';
    include './views/footer.php';
    exit;
} else {
}
if ($examError !== null) {
?>
<div class="page page-narrow mx-auto">
    <div class="card form-card mt-4">
        <div class="card-body">
            <h1 class="page-title">无法开始测试</h1>
            <div class="alert alert-danger" role="alert"><?php echo nl2br(htmlspecialchars($examError)); ?></div>
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
    <?php if ($restarted) : ?>
        <div class="alert alert-info" role="alert">检测到该邮箱此前已登记，测试进度已重置并重新计时。</div>
    <?php endif; ?>
    <?php if ($matrixMxid !== null) : ?>
        <div class="alert alert-info" role="alert">礼仪测试免考：你已通过 Matrix 账号（<?php echo htmlspecialchars($matrixMxid); ?>）验证，本测试不包含基本礼仪题，该部分将直接获得满分。</div>
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
                        <th scope="col">基类组合</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th scope="row"><?php echo htmlspecialchars(isset($user['id']) ? $user['id'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></th>
                        <td><?php echo htmlspecialchars(isset($user['username']) ? $user['username'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(isset($user['email']) ? $user['email'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(isset($user['selected_categories']) ? $user['selected_categories'] : '错误：数据库返回了空值。请立即停止测试并通过管理邮箱报告此问题。', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

<form id="examForm" action="result.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
    <input type="hidden" name="paper_id" value="<?php echo htmlspecialchars($paperId ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars(isset($user['id']) ? $user['id'] : '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $questionNumber = 1; // 初始化题号
    foreach ($questions as $question) {
        echo '
                    <div class="card question-card">
                        <div class="card-body">
                            <h5 class="question-text">' . htmlspecialchars($questionNumber) . '. ' . htmlspecialchars($question['question_text']) . '</h5>';
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
