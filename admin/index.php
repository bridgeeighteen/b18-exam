<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';

initSecurity();
sendSecurityHeaders();
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
    <div class="card form-card mx-auto">
        <div class="card-body text-center">
            <h1 class="page-title">请登录</h1>
            <a class="btn btn-primary btn-lg w-100" href="oauth.php">使用社区 OAuth 登录</a>
        </div>
    </div>
</div>
<?php require './views/footer.php'; ?>
