<?php 
require 'config.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>信息登记 - 十八桥社区论坛入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit"></script>
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
                <div class="alert alert-danger" role="alert" id="turnstile-error" style="display:none;">Cloudflare Turnstile 的 JavaScript 脚本似乎未正确加载。建议刷新页面重新填写。</div>
                <h2>信息登记</h2>
                <h5>在正式开始测试前，请填写这些基本信息以便我们核查邀请码使用情况。请记住，将邀请码转让给他人是绝对禁止的，这会导致账号被封禁。</h5>
                <form action="exam.php" method="post">
                    <div class="form-group">
                      <label for="InputUsername">用户名</label>
                      <input type="username" class="form-control" id="InputUsername" aria-describedby="usernameHelp" name="username" required>
                      <small id="usernameHelp" class="form-text text-muted">如果测试通过后想更换，可以在注册时填写。</small>
                    </div>
                    <div class="form-group">
                      <label for="InputEmail">电子邮件地址</label>
                      <input type="email" class="form-control" id="InputEmail" aria-describedby="emailHelp" name="email" required>
                      <small id="emailHelp" class="form-text text-muted">请一定确保这里的地址与注册的地址完全一致，否则账号将因被视为“邀请码滥用”遭到封禁。</small>
                    </div>
                    <div class="form-group">
                      <label for="categories">选择基类</label>
                      <div>
                          <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="categories[]" value="IT" id="category_IT" required>
                              <label class="form-check-label" for="category_IT">
                                  IT
                              </label>
                          </div>
                          <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="categories[]" value="ACGN" id="category_ACGN" required>
                              <label class="form-check-label" for="category_ACGN">
                                  ACGN
                              </label>
                          </div>
                          <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="categories[]" value="Virtual_Singer" id="category_Virtual_Singer" required>
                              <label class="form-check-label" for="category_Virtual_Singer">
                                  虚拟歌手
                              </label>
                          </div>
                          <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="categories[]" value="Broadcasting" id="category_Broadcasting" required>
                              <label class="form-check-label" for="category_Broadcasting">
                                  广播电视
                              </label>
                          </div>
                      </div>
                      <small id="categoriesHelp" class="form-text text-muted">社区将论坛目前规划的板块划分为以上四个基本类型，请从中选择两类作为自选试题的考查方向。</small>
                    </div>
                    <div class="form-group form-check">
                      <input type="checkbox" class="form-check-input" id="ruleCheck" required>
                      <label class="form-check-label" for="ruleCheck">我已阅读<a href="https://www.bridge18.us.kg/p/3-code-of-user-conduct">用户行为准则</a>，并确认完全理解其内容。</label>
                    </div>
                    <div class="form-group" id="turnstile"></div>
                    <button type="submit" class="btn btn-primary">开始测试</button>
                    <script>
                      document.addEventListener('DOMContentLoaded', function() {
                          const checkboxes = document.querySelectorAll('input[name="categories[]"]');
                          checkboxes.forEach(checkbox => {
                              checkbox.addEventListener('change', function() {
                                  const checkedCount = document.querySelectorAll('input[name="categories[]"]:checked').length;
                                  if (checkedCount > 2) {
                                      this.checked = false;
                                      alert('只能选择两个基类。');
                                  }
                              });
                          });
                      });
                      if (typeof turnstile !== 'undefined') {
                        turnstile.ready(function () {
                            turnstile.render('#turnstile', {
                                sitekey: <?php echo "'" . htmlspecialchars(CF_TURNSTILE_SITEKEY) . "'" ?>,
                                callback: function (token) {
                                    console.log(`Turnstile 成功通过，已获取 Token。`);
                                    },
                            });
                        });
                      } else {
                        document.getElementById('turnstile-error').style.display = 'block';
                        console.error('Turnstile 未定义。');
                      }
                    </script>
                 </form>
<?php require './views/footer.php'; ?>
