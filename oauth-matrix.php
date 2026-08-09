<?php
require_once 'config.php';
require_once 'includes/security.php';
require_once 'includes/oauth.php';

initSecurity();
sendSecurityHeaders();

// 使用 Matrix 账号（Matrix Authentication Service 上的账号）登录以免考论坛测试中的基本礼仪题。
// 授权码流程（含 PKCE）由 includes/oauth.php 中的 oauthRunFlow() 统一处理。

if (!masOAuthEnabled()) {
    oauthErrorPage('未启用 OAuth 免考', 'Matrix 账号登录（免考礼仪测试）功能尚未启用或尚未完成配置。请稍后重试，或者通过管理邮箱联系我们。');
}

$result = oauthRunFlow(oauthMasConfig('https://' . SITE . '/oauth-matrix.php'));

if (!$result['ok']) {
    $messages = [
        'state' => "无法验证 OAuth 请求的来源（state 不匹配），本次免考申请已被拒绝。\n请从信息登记页面重新发起登录，或者通过管理邮箱联系我们。",
        'token' => "与 Matrix Authentication Service 交换授权码时出现问题，未能获取访问令牌。\n请稍后重试，或者通过管理邮箱联系我们。",
        'user' => "无法从 Matrix Authentication Service 获取用户信息。\n请稍后重试，或者通过管理邮箱联系我们。",
        'user_invalid' => "Matrix Authentication Service 返回的用户信息格式不正确，本次免考申请已被拒绝。\n请通过管理邮箱联系我们并向源代码仓库创建 Issues 以报告此问题。",
    ];
    oauthErrorPage($result['error'] === 'state' ? 'OAuth 验证失败' : 'OAuth 登录失败', $messages[$result['error']]);
}

$_SESSION['matrix_oauth'] = [
    'mxid' => $result['user']['mxid'],
    'email' => $result['user']['email'],
    'verified_at' => time(),
];

oauthRedirectBack('forum');
