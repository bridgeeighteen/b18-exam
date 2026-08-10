<?php
$adminCurrent = currentAdmin();
$adminPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../views/assets/logo_text.svg" height="25" class="d-inline-block align-text-bottom"
                    alt="十八桥社区">
                入站测试系统
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars(ABOUT_URL); ?>">关于</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://codeberg.org/bridgeeighteen/b18-exam">源代码</a>
                    </li>
                    <?php if ($adminCurrent !== null) : ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $adminPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">数据统计</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $adminPage === 'tests.php' ? 'active' : ''; ?>" href="tests.php">测试信息</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $adminPage === 'questions.php' ? 'active' : ''; ?>" href="questions.php">题目管理</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $adminPage === 'keys.php' ? 'active' : ''; ?>" href="keys.php">API 密钥</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $adminPage === 'blacklist.php' ? 'active' : ''; ?>" href="blacklist.php">黑名单</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $adminPage === 'audit.php' ? 'active' : ''; ?>" href="audit.php">审计日志</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <?php if ($adminCurrent !== null) : ?>
                    <span class="navbar-text me-3 d-none d-md-inline"><?php echo htmlspecialchars($adminCurrent['identity_label']); ?></span>
                    <a class="btn btn-outline-primary btn-sm" href="logout.php">退出</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col">
