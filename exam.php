<?php
// 包含数据库配置文件
require_once 'config.php';
require_once './vendor/autoload.php';

use FluxSoft\Turnstile\Turnstile;

if (CLOSED) {
} else {

$turnstileResponse = $_POST['cf-turnstile-response'];

if (empty($turnstileResponse)) {
    echo "入站测试系统使用 Cloudflare Turnstile 验证码，而你提交的信息表单中缺少用于服务器端验证的值。";
    echo "这表明你在填写基本信息时 Turnstile 验证框未正常加载，或者浏览器因不支持 JavaScript 或版本太过老旧而不支持 Turnstile。";
    echo "为了防止账号滥用，请尝试重新填写基本信息，或者更换设备/浏览器。";
    exit;
}

$secretKey = CF_TURNSTILE_SECRET;
$turnstile = new Turnstile($secretKey);
$verifyResponse = $turnstile->verify($turnstileResponse, $_SERVER['REMOTE_ADDR']);

try {
  $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("" . $e->getMessage());
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
    // 如果电子邮件已存在，则使用现有用户的 ID
    $userId = $existingUser['id'];
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

  // 更新用户开始时间
  $stmt = $db->prepare("UPDATE `users` SET `start_time` = NOW() WHERE `id` = ?");
  $stmt->execute([$userId]);

  // 仅执行一次
  $stmt->closeCursor();
}

// 从 users 表中获取用户信息
$stmt = $db->prepare("SELECT * FROM `users` WHERE `id` = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 确保至少有一条记录
if (!$user) {
  die('错误：用户信息未找到。请立即通过管理邮箱报告此问题。');
}

// 从题库中随机抽取题目
$baseQuestions = [];
foreach ($selectedCategories as $category) {
    $stmt = $db->prepare("SELECT * FROM `questions` WHERE `category` = ? AND `category` != 'Etiquette' LIMIT 15");
    $stmt->execute([$category]);
    $baseQuestions = array_merge($baseQuestions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 获取基本礼仪题
$stmt = $db->prepare("SELECT * FROM `questions` WHERE `category` = 'Etiquette' LIMIT 10");
$stmt->execute();
$etiquetteQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 合并题目
$questions = array_merge($etiquetteQuestions, $baseQuestions);

if (count($questions) < 25) {
  die('错误：题库中的题目数量不足。请立即通过管理邮箱报告此问题。');
}


// 开始HTML输出
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
      var duration = 70 * 60, // 70 minutes
        display = document.querySelector('#timer');
      startTimer(duration, display);
    };
  </script>
</head>

<?php 
include './views/nav.php'; 
if (CLOSED) {
  echo '<div class="alert alert-warning" role="alert">测试通道已关闭。更多详情请查看社区论坛和联邦宇宙官宣账号。</div>';
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
<?php include './views/footer.php'; ?>