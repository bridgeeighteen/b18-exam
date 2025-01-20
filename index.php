<?php require_once 'config.php'; ?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>十八桥社区论坛入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
</head>

<?php include './views/nav.php'; ?>
                <div class="jumbotron">
                    <h1 class="display-4">你好！</h1>
                    <p class="lead">欢迎来到十八桥社区的论坛入站测试系统。你只需要让测试总分数达到 <?php echo htmlspecialchars(SCORE_THRESHOLD); ?> 分及以上，就可以获得邀请码用于注册账号。</p>
                    <hr class="my-4">
                    <p>请先阅读<a href="https://www.bridge18.rr.nu/p/3-code-of-user-conduct">用户行为准则</a>，确认完全理解其内容后再开始测试。</p>
                    <?php
                    if (CLOSED) {
                      echo '<button type="button" class="btn btn-lg btn-primary" disabled>测试通道已关闭</button>';
                    } else {
                      echo '<a class="btn btn-primary btn-lg" href="info.php" role="button">立即测试</a>';
                    }
                    ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">在本系统上线之前已经通过表单提交公测申请？</h5>
                      <p class="card-text">如果你的邮箱收到了申请通过邮件，可以使用邮件里的邀请码注册账号，一般情况下无需通过入站测试再次获得邀请码。</p>
                      <a href="https://www.bridge18.rr.nu/" class="btn btn-primary">去注册</a>
                    </div>
                  </div>
                </div>
                <div class="col-sm-6">
                  <div class="card">
                    <div class="card-body">
                      <h5 class="card-title">帮助我们不断改进</h5>
                      <p class="card-text">如果你对系统、测试试题或计分标准有任何意见和建议，欢迎通过管理邮箱或源代码仓库的 Issues、PRs 告诉我们。</p>
                      <a href="mailto:admin@bridge18.rr.nu" class="btn btn-primary">去发信</a>
                    </div>
                  </div>
                </div>
              </div>
        </div>
<?php include './views/footer.php'; ?>