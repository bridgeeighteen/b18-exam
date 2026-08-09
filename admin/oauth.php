<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/oauth.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

// 使用社区论坛（Flarum，FoskyM/flarum-oauth-center 提供方）OAuth 登录管理面板。
// 本页面同时承担发起授权与处理回调两个步骤。
// 授权码流程由 includes/oauth.php 中的 oauthRunFlow() 统一处理，用户校验由
// adminVerifyForumUser() 完成；通过论坛校验后仍需在 verify-matrix.php 完成千万桥（MAS）管理员校验。

$apiSite = API_SITE;
$clientId = OAUTH_CLIENT_ID;
$clientSecret = OAUTH_CLIENT_SECRET;

if (strpos($clientId, 'YOUR_') === 0 || strpos($clientSecret, 'YOUR_') === 0) {
    adminErrorPage('未配置 OAuth 登录', '管理面板的论坛 OAuth 登录尚未完成配置（OAUTH_CLIENT_ID / OAUTH_CLIENT_SECRET）。\n请在 config.php 中完成配置后重试。');
}

if (currentAdmin() !== null) {
    header('Location: dashboard.php');
    exit;
}

// fetch_user 为 false：本流程不需要 oauthRunFlow() 获取用户信息，
// 访问令牌会随结果返回，由 adminVerifyForumUser() 完成用户信息与用户组校验。
$result = oauthRunFlow(oauthFlarumConfig(
    'https://' . SITE . '/admin/oauth.php',
    $clientId,
    $clientSecret,
    false
));

if (!$result['ok']) {
    $messages = [
        'state' => "无法验证 OAuth 请求的来源（state 不匹配），本次登录已被拒绝。\n请从管理面板登录页重新发起登录。",
        'token' => "与论坛交换授权码时出现问题，未能获取访问令牌。\n请稍后重试。",
        'user' => "无法从论坛获取用户信息。\n请稍后重试。",
    ];
    adminErrorPage($result['error'] === 'state' ? 'OAuth 验证失败' : 'OAuth 登录失败', $messages[$result['error']]);
}

// 校验论坛管理员身份（fail-closed）
$forumUser = adminVerifyForumUser($result['access_token']);

if ($forumUser === null) {
    adminErrorPage('OAuth 登录失败', "无法从论坛获取用户信息。\n请稍后重试。");
}

if (!$forumUser['forum_admin']) {
    adminErrorPage('权限不足', "账号 " . $forumUser['username'] . " 不属于论坛管理员用户组，无法访问管理面板。");
}

// 暂存论坛身份，等待千万桥管理员校验
$_SESSION['admin_pending_forum'] = [
    'user_id' => $forumUser['id'],
    'username' => $forumUser['username'],
    'email' => $forumUser['email'],
    'verified_at' => time(),
];

header('Location: verify-matrix.php');
exit;
