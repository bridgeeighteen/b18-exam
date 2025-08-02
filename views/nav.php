<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="./views/assets/logo_text.svg" height="25" class="d-inline-block align-text-bottom"
                    alt="十八桥社区">
                论坛入站测试系统
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(ABOUT_URL); ?>">关于</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://github.com/bridgeeighteen/b18-exam">源代码</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">管理（开发中）</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col">
<?php
if (isset($_GET["old_domain"]) && $_GET["old_domain"] == "1") {
    echo '                <div class="alert alert-primary" role="alert">"';
    echo '                    欢迎来到新的入站测试系统！我们很高兴地告诉你，你现在可以自选试题的考查方向，这或许会使你获得邀请码更容易。更多信息，请点击<a href="https://www.bridge18.us.kg/d/74">这里</a>。';
    echo '                </div>';
} else {
}
?>
