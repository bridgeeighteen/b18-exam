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

// 解析客户端凭据：静态配置优先，否则通过 RFC 7591 动态客户端注册协议向 MAS 自动注册
if (oauthMasCredentials() === null) {
    oauthErrorPage('OAuth 登录失败', "无法获取 Matrix Authentication Service 的 OAuth 客户端凭据（动态注册失败或 MAS 策略拒绝了本系统的注册请求）。\n请稍后重试，或者通过管理邮箱联系我们。");
}

$result = oauthRunFlow(oauthMasConfig('https://' . SITE . '/oauth-matrix.php'));

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
        'provider' => "Matrix Authentication Service 拒绝了本次授权请求（" . $providerDetail . "）。\n这通常是服务端注册的 OAuth 客户端元数据（response_type / grant_types）与授权请求不匹配所致。请稍后重试，或者通过管理邮箱联系我们。",
        'token' => "与 Matrix Authentication Service 交换授权码时出现问题，未能获取访问令牌。\n请稍后重试，或者通过管理邮箱联系我们。",
        'user' => "无法从 Matrix Authentication Service 获取用户信息。\n请稍后重试，或者通过管理邮箱联系我们。",
        'user_invalid' => "Matrix Authentication Service 返回的用户信息格式不正确，本次免考申请已被拒绝。\n请通过管理邮箱联系我们并向源代码仓库创建 Issues 以报告此问题。",
    ];
    oauthErrorPage($result['error'] === 'state' ? 'OAuth 验证失败' : 'OAuth 登录失败', $messages[$result['error']]);
}

$subject = $result['user']['subject'];

// MAS 的 userinfo 端点不返回 email，账号绑定邮箱需通过管理 API 核验
// （GET /api/admin/v1/user-emails?filter[user]=<sub>，sub 即用户 ULID）。
// 核验失败时 fail-closed：不发放免考资格。
if ($subject === '') {
    oauthErrorPage('OAuth 登录失败', "Matrix Authentication Service 返回的用户信息缺少用户标识，本次免考申请已被拒绝。\n请通过管理邮箱联系我们并向源代码仓库创建 Issues 以报告此问题。");
}

$emails = masUserEmails($subject);
if ($emails === null) {
    oauthErrorPage('OAuth 登录失败', "无法核验 Matrix 账号绑定的电子邮件地址（管理 API 不可用）。\n请稍后重试，或者通过管理邮箱联系我们。");
}

$_SESSION['matrix_oauth'] = [
    'mxid' => $result['user']['mxid'],
    'emails' => $emails,
    'verified_at' => time(),
];

oauthRedirectBack('forum');
