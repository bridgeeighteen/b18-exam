<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/oauth.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

if (currentAdmin() !== null) {
    header('Location: dashboard.php');
    exit;
}

$masLoginAvailable = defined('ADMIN_MAS_OAUTH_ENABLED') && ADMIN_MAS_OAUTH_ENABLED && masOAuthEnabled();
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <div class="card form-card form-card-narrow mx-auto">
        <div class="card-body">
            <h1 class="page-title">管理员登录</h1>
            <p class="page-subtitle">管理面板仅限管理员访问。使用社区论坛登录时，需同时拥有社区论坛与千万桥的管理员权限；使用千万桥登录时，需拥有千万桥的管理员权限。</p>
            <div class="form-section">
                <a class="btn btn-primary btn-lg w-100" href="oauth.php">使用社区论坛 OAuth 登录</a>
            </div>
            <?php if ($masLoginAvailable) : ?>
                <div class="form-section">
                    <a class="btn btn-outline-secondary btn-lg w-100" href="oauth-matrix.php">使用千万桥 OAuth 登录</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require './views/footer.php'; ?>
