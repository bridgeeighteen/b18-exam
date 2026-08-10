<?php
require_once 'config.php';
require_once 'includes/security.php';
require_once 'includes/oauth.php';

initSecurity();
sendSecurityHeaders();

// 使用论坛账号（Flarum，FoskyM/flarum-oauth-center 提供方）登录以免考 Matrix 实例礼仪测试。
// 授权码流程由 includes/oauth.php 中的 oauthRunFlow() 统一处理。

if (!forumOAuthEnabled()) {
    oauthErrorPage('未启用 OAuth 免考', '论坛账号登录（免考礼仪测试）功能尚未启用或尚未完成配置。请稍后重试，或者通过管理邮箱联系我们。');
}

$result = oauthRunFlow(oauthFlarumConfig(
    'https://' . SITE . '/oauth-forum.php',
    FORUM_OAUTH_CLIENT_ID,
    FORUM_OAUTH_CLIENT_SECRET
));

if (!$result['ok']) {
    $providerDetail = '未知错误';
    if (!empty($result['provider_error'])) {
        $providerDetail = htmlspecialchars($result['provider_error']);
        if (!empty($result['provider_description'])) {
            $providerDetail .= '：' . htmlspecialchars($result['provider_description']);
        }
    }
    $messages = [
        'state' => "无法验证 OAuth 请求的来源（state 不匹配），本次免考申请已被拒绝。\n请从信息登记页面重新发起登录，或者通过管理邮箱联系我们。",
        'provider' => "论坛 OAuth 服务拒绝了本次授权请求（" . $providerDetail . "）。\n这通常是论坛 OAuth 插件中注册的客户端配置（回调地址 / 授权类型）与授权请求不匹配所致。请稍后重试，或者通过管理邮箱联系我们。",
        'token' => "与论坛交换授权码时出现问题，未能获取访问令牌。\n请稍后重试，或者通过管理邮箱联系我们。",
        'user' => "无法从论坛获取用户信息。\n请稍后重试，或者通过管理邮箱联系我们。",
    ];
    oauthErrorPage($result['error'] === 'state' ? 'OAuth 验证失败' : 'OAuth 登录失败', $messages[$result['error']]);
}

$user = $result['user'];

if ($user['email'] === '' || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
    oauthErrorPage('OAuth 登录失败', "论坛未返回有效的电子邮件地址，本次免考申请已被拒绝。\n请确认论坛账号已绑定有效的电子邮件地址后重试。");
}

$_SESSION['forum_oauth'] = [
    'user_id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'verified_at' => time(),
];

oauthRedirectBack('matrix');
