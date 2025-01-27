<?php
require_once 'config.php';
require_once './vendor/autoload.php';

if (DB_TIMEZONE_LOCK) {
} else {
    date_default_timezone_set(PHP_TIMEZONE);
}

use FluxSoft\Turnstile\Turnstile;

if (CLOSED) {
} else {

    if (empty($_POST['cf-turnstile-response'])) {
        echo "入站测试系统使用 Cloudflare Turnstile 验证码，而你提交的信息表单中缺少用于服务器端验证的值。";
        echo "\n这表明你在填写基本信息时 Turnstile 验证框未正常加载，或者浏览器因不支持 JavaScript 或版本太过老旧而不支持 Turnstile。";
        echo "\n为了防止账号滥用，请尝试重新填写基本信息，或者更换设备/浏览器。";
        exit;
    } else {
        $secretKey = CF_TURNSTILE_SECRET;
        $turnstile = new Turnstile($secretKey);
        $verifyResponse = $turnstile->verify($_POST['cf-turnstile-response'], $_SERVER['REMOTE_ADDR']);
    }

    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("数据库连接失败：" . $e->getMessage() . "\n如果问题依旧存在，你可能需要通过管理邮箱联系我们或者向源代码仓库创建 Issues 以报告此问题。");
    }

    if ($verifyResponse->success) {
        // Turnstile 验证成功
    } else {
        if ($verifyResponse->hasErrors()) {
            foreach ($verifyResponse->errorCodes as $errorCode) {
                echo 'Turnstile 服务器端验证失败：' . $errorCode;
                echo '如果问题依旧存在，你可能需要通过管理邮箱联系我们或者向源代码仓库创建 Issues 以报告此问题。';
                exit;
            }
        } elseif (CF_TURNSTILE_SITEKEY == "1x00000000000000000000AA" && CF_TURNSTILE_SECRET == "1x0000000000000000000000000000000AA") {
            echo '警告：你正在使用配置模板提供的测试用 Turnstile 密钥。如果你决定将系统用于生产环境，请前往 Cloudflare 仪表板创建一对新密钥。';
        } else {
            echo 'Turnstile 服务器端验证失败，但类型未知。';
            echo '如果问题依旧存在，你可能需要通过管理邮箱联系我们或者向源代码仓库创建 Issues 以报告此问题。';
            exit;
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $selectedCategories = $_POST['categories'];

        if (count($selectedCategories) !== 2) {
            die('错误：选择的基类只能为两个。你的信息未被上传至数据库，请返回并重新填写。');
        }

        // 检查电子邮件是否已存在于 users 表中
        $stmt = $db->prepare("SELECT id FROM `users` WHERE `email` = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            // 如果电子邮件已存在，则使用现有用户的 ID，并更新选择的基类
            $userId = $existingUser['id'];
            $stmt = $db->prepare("UPDATE `users` SET `selected_categories` = ? WHERE `id` = ?");
            $stmt->execute([implode(',', $selectedCategories), $userId]);
        } else {
            // 如果电子邮件不存在，则将用户加入 users 表
            $stmt = $db->prepare("INSERT INTO `users` (`username`, `email`, `selected_categories`) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, implode(',', $selectedCategories)]);

            // 获取最新用户的 ID
            $stmt = $db->prepare("SELECT id FROM `users` WHERE `email` = ?");
            $stmt->execute([$email]);
            $newUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$newUser) {
                die('错误：用户信息未找到。请立即通过管理邮箱报告此问题。');
            }

            $userId = $newUser['id'];
        }

        // 从 users 表中获取用户信息
        $stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 确保至少有一条记录
        if (!$user) {
            die('错误：用户信息未找到。请立即通过管理邮箱报告此问题。');
        }

        // 获取所有基本礼仪题
        $stmt = $db->prepare("SELECT * FROM `questions` WHERE `category` = 'Etiquette'");
        $stmt->execute();
        $etiquetteQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 确保至少有 10 道基本礼仪题
        if (count($etiquetteQuestions) < 10) {
            die('题库中的基本礼仪题数量不足。请立即通过管理邮箱报告此问题。');
        }

        // 随机抽取 10 道基本礼仪题
        shuffle($etiquetteQuestions);
        $etiquetteQuestions = array_slice($etiquetteQuestions, 0, 10);

        // 获取所有自选组合题
        $baseQuestions = [];
        foreach ($selectedCategories as $category) {
            $stmt = $db->prepare("SELECT * FROM `questions` WHERE `category` = ? AND `category` != 'Etiquette'");
            $stmt->execute([$category]);
            $baseQuestions = array_merge($baseQuestions, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // 确保至少有 15 道自选组合题
        if (count($baseQuestions) < 15) {
            die('题库中的自选组合题数量不足。请立即通过管理邮箱报告此问题。');
        }

        // 随机抽取 15 道自选组合题
        shuffle($baseQuestions);
        $baseQuestions = array_slice($baseQuestions, 0, 15);

        // 合并题目，确保基本礼仪题在前
        $questions = array_merge($etiquetteQuestions, $baseQuestions);

        // 更新用户开始时间
        $stmt = $db->prepare("UPDATE `users` SET `start_time` = NOW() WHERE `id` = ?");
        $stmt->execute([$userId]);

        // 仅执行一次
        $stmt->closeCursor();

        // 开始HTML输出
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <title>答卷 - 十八桥社区论坛入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
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
if (CLOSED) {
    echo '<div class="alert alert-warning" role="alert">测试通道已关闭，原因：' . CLOSED_REASON . '更多详情请查看社区论坛和联邦宇宙官宣账号。</div>';
    include './views/footer.php';
    exit;
} else {
}
?>
<h2>答卷 <span class="badge badge-secondary" id="timer"></span></h2>
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

<form id="examForm" action="result.php" method="post">
    <?php
    $questionNumber = 1; // 初始化题号
    foreach ($questions as $question) {
        echo '
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">' . htmlspecialchars($questionNumber) . '. ' . htmlspecialchars($question['question_text']) . '</h5>';
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
    <button type="submit" class="btn btn-primary">提交</button>
</form>
<?php require './views/footer.php'; ?>