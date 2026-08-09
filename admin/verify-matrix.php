<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

// 论坛 OAuth 路径的第二步：填写千万桥（MAS）用户名以完成双平台管理员校验。

if (currentAdmin() !== null) {
    header('Location: dashboard.php');
    exit;
}

$pending = $_SESSION['admin_pending_forum'] ?? null;
if (!is_array($pending) || !isset($pending['user_id'], $pending['username'])) {
    header('Location: oauth.php');
    exit;
}

$error = null;
$matrixUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $matrixUsername = trim((string)($_POST['matrix_username'] ?? ''));

        $matrixUser = adminVerifyMatrixUser($matrixUsername);

        if ($matrixUser === null) {
            $error = '无法验证千万桥管理员身份。请检查用户名是否正确、MAS 管理 API 是否可用，以及该账号是否具备千万桥管理员权限。';
        } else {
            grantAdminSession([
                'via' => 'forum',
                'label' => $pending['username'] . '（论坛管理员）',
                'forum_user_id' => $pending['user_id'],
                'forum_username' => $pending['username'],
                'forum_email' => $pending['email'] ?? '',
                'matrix_username' => $matrixUser['username'],
                'mxid' => $matrixUser['mxid'],
            ], 'forum');

            unset($_SESSION['admin_pending_forum']);
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>验证千万桥账号 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <div class="card form-card mx-auto">
        <div class="card-body">
            <h2 class="page-title">验证千万桥账号</h2>
            <p class="page-subtitle">你已通过社区论坛 OAuth 登录（账号：<strong><?php echo htmlspecialchars($pending['username']); ?></strong>）。管理面板要求同时拥有社区论坛与千万桥的管理员权限，请填写你的千万桥用户名（无需 @ 与域名部分）以完成验证。</p>
            <?php if ($error !== null) : ?>
                <div class="alert alert-danger mt-4" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="verify-matrix.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-section">
                    <label for="matrixUsername" class="form-label">千万桥用户名</label>
                    <input type="text" class="form-control" id="matrixUsername" name="matrix_username" value="<?php echo htmlspecialchars($matrixUsername); ?>" required maxlength="254" autocomplete="username">
                    <small class="form-text">例如你的账号是 @alice:example.com，则填写 alice。</small>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg w-100">验证并进入管理面板</button>
                </div>
            </form>
            <div class="text-center mt-4">
                <a class="btn btn-outline-secondary w-100" href="oauth-matrix.php" role="button">改用千万桥账号登录</a>
            </div>
        </div>
    </div>
</div>
<?php require './views/footer.php'; ?>
