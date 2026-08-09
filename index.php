<?php
require_once 'config.php';
require_once 'includes/security.php';

initSecurity();
sendSecurityHeaders();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="./vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./views/assets/css/noto-face.css">
    <link rel="stylesheet" href="./views/assets/css/tokens.css">
</head>

<?php require './views/nav.php'; ?>
                <div class="hero">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-7">
                            <h1 class="hero-title">你好！</h1>
                            <p class="hero-lead">欢迎来到十八桥社区的入站测试系统。你只需要让测试总分数达到 <?php echo htmlspecialchars(SCORE_THRESHOLD); ?> 分及以上，就可以获得邀请码用于注册账号。<?php if (MATRIX_ENABLED) : ?>你还可以在信息登记页面选择注册<?php echo htmlspecialchars(MATRIX_INSTANCE_NAME); ?>，完成礼仪测试后即可获得注册 Token。<?php endif; ?></p>
                            <p class="hero-note">请先阅读<a href="<?php echo htmlspecialchars(TOS_URL); ?>">使用条款</a>，确认完全理解其内容后再开始测试。</p>
                            <div class="hero-actions">
                                <?php
                                if (FORUM_CLOSED && (!MATRIX_ENABLED || MATRIX_CLOSED)) {
                                    echo '<button type="button" class="btn btn-lg btn-primary" disabled>测试通道已关闭</button>';
                                } else {
                                    echo '<a class="btn btn-primary btn-lg" href="info.php" role="button">立即测试</a>';
                                }
                                ?>
                                <a class="btn btn-outline-primary btn-lg" href="<?php echo htmlspecialchars(TOS_URL); ?>" role="button">阅读使用条款</a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card support-card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars(ANNOUCEMENT_TITLE); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars(ANNOUCEMENT_CONTENT); ?></p>
                                    <a href="<?php echo htmlspecialchars(ANNOUCEMENT_LINK); ?>" class="btn btn-outline-primary">了解详情</a>
                                </div>
                            </div>
                            <div class="card support-card">
                                <div class="card-body">
                                    <h5 class="card-title">帮助我们不断改进</h5>
                                    <p class="card-text">如果你对系统、测试试题或计分标准有任何意见和建议，欢迎通过管理邮箱或源代码仓库的 Issues、PRs 告诉我们。</p>
                                    <a href="javascript:location.href = 'mailto:' + ['<?php echo htmlspecialchars(ADMIN_EMAIL_NAME); ?>','<?php echo htmlspecialchars(ADMIN_EMAIL_DOMAIN); ?>'].join('@')" class="btn btn-outline-primary">去发信</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
<?php require './views/footer.php'; ?>
