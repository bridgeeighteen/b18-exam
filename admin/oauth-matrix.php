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
    adminErrorPage('未配置 OAuth 登录', '千万桥 OAuth 登录尚未启用（MAS_OAUTH_ENABLED）。\n请在 config.php 中将 MAS_OAUTH_ENABLED 设为 true 并确认 MATRIX_API_SITE 已填写后重试。');
}

if (currentAdmin() !== null) {
    header('Location: dashboard.php');
    exit;
}

// 解析客户端凭据：静态配置优先，否则通过 RFC 7591 动态客户端注册协议向 MAS 自动注册
if (oauthMasCredentials() === null) {
    adminErrorPage('OAuth 登录失败', "无法获取 Matrix Authentication Service 的 OAuth 客户端凭据。\n请确认 MAS 的 OAuth 资源已启用、策略（policy.data.client_registration）允许本系统的回调地址 https://" . SITE . "/，然后重新尝试登录。");
}

// 管理面板登录只需要用户名（MXID），仅请求 openid 作用域（不索取 email 授权）
$result = oauthRunFlow(oauthMasConfig('https://' . SITE . '/admin/oauth-matrix.php', 'openid'));

if (!$result['ok']) {
    $providerDetail = '未知错误';
    if (!empty($result['provider_error'])) {
        $providerDetail = htmlspecialchars($result['provider_error']);
        if (!empty($result['provider_description'])) {
            $providerDetail .= '：' . htmlspecialchars($result['provider_description']);
        }
    }
    $messages = [
        'state' => "无法验证 OAuth 请求的来源（state 不匹配），本次登录已被拒绝。\n请从管理面板登录页重新发起登录。",
        'provider' => "Matrix Authentication Service 拒绝了本次授权请求（" . $providerDetail . "）。\n这通常是服务端注册的 OAuth 客户端元数据（response_type / grant_types）与授权请求不匹配所致。请稍后重试。",
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
