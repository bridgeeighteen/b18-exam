<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <style>
        .form-signin {
            width: 100%;
            max-width: 330px;
            padding: 15px;
            margin: auto;
        }

        .form-signin .form-control {
            position: relative;
            box-sizing: border-box;
            height: auto;
            padding: 10px;
            font-size: 16px;
        }

        .form-signin .form-control:focus {
            z-index: 2;
        }
    </style>
</head>
<?php include './views/nav.php'; ?>
<form class="form-signin">
    <h1 class="h3 mb-3 font-weight-normal">请登录</h1>
    <a class="btn btn-lg btn-primary btn-block" type="submit" href="oauth.php">使用社区 OAuth 登录</a>
</form>
<?php include './views/footer.php'; ?>