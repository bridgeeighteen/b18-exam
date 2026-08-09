<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/oauth.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

// 使用千万桥（Matrix Authentication Service）账号 OAuth 登录管理面板。
// 本页面是 OAuth 2.0 授权码流程（含 PKCE）中的客户端回调端点，同时承担发起授权与处理回调两个步骤。
// 授权码流程由 includes/oauth.php 中的 oauthRunFlow() 统一处理，本页仅校验千万桥管理员身份。
// 该路径仅校验千万桥管理员身份。

if (!(defined('ADMIN_MAS_OAUTH_ENABLED') && ADMIN_MAS_OAUTH_ENABLED)) {
    adminErrorPage('未启用千万桥登录', '使用千万桥账号登录管理面板的功能尚未启用（ADMIN_MAS_OAUTH_ENABLED）。');
}

if (!masOAuthEnabled()) {
    adminErrorPage('未配置 OAuth 登录', '千万桥 OAuth 登录尚未完成配置（MAS_OAUTH_CLIENT_ID / MAS_OAUTH_CLIENT_SECRET）。\n请在 config.php 中完成配置，并在 MAS 的 OAuth 客户端配置中追加回调地址 https://' . SITE . '/admin/oauth-matrix.php 后重试。');
}

if (currentAdmin() !== null) {
    header('Location: dashboard.php');
    exit;
}

$result = oauthRunFlow(oauthMasConfig('https://' . SITE . '/admin/oauth-matrix.php'));

if (!$result['ok']) {
    $messages = [
        'state' => "无法验证 OAuth 请求的来源（state 不匹配），本次登录已被拒绝。\n请从管理面板登录页重新发起登录。",
        'token' => "与 Matrix Authentication Service 交换授权码时出现问题，未能获取访问令牌。\n请稍后重试。",
        'user' => "无法从 Matrix Authentication Service 获取用户信息。\n请稍后重试。",
        'user_invalid' => "Matrix Authentication Service 返回的用户信息格式不正确，本次登录已被拒绝。",
    ];
    adminErrorPage($result['error'] === 'state' ? 'OAuth 验证失败' : 'OAuth 登录失败', $messages[$result['error']]);
}

$mxid = $result['user']['mxid'];

// 校验千万桥管理员身份（fail-closed）
$matrixUser = adminVerifyMatrixUser($result['user']['username']);

if ($matrixUser === null) {
    adminErrorPage('权限不足', "账号 " . htmlspecialchars($mxid) . " 不是千万桥管理员，无法访问管理面板。");
}

grantAdminSession([
    'via' => 'mas',
    'label' => $matrixUser['username'] . '（千万桥）',
    'matrix_username' => $matrixUser['username'],
    'mxid' => $matrixUser['mxid'] ?? $mxid,
], 'mas');

header('Location: dashboard.php');
exit;
