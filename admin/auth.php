<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/version.php';
require_once __DIR__ . '/../includes/oauth.php';

// 管理员身份与访问控制。
// 校验规则：
// - 论坛 OAuth 路径：必须同时通过论坛管理员组校验与千万桥（MAS）管理员校验；
// - 千万桥（MAS）OAuth 路径：仅需通过 MAS 管理员校验。

// 返回当前登录的管理员会话，未登录或已过期返回 null
function currentAdmin(): ?array
{
    initSecurity();

    $admin = $_SESSION['admin'] ?? null;
    if (!is_array($admin) || !isset($admin['identity'], $admin['verified_at'])) {
        return null;
    }

    if (time() - (int)$admin['verified_at'] > (int)ADMIN_SESSION_LIFETIME * 60) {
        unset($_SESSION['admin']);
        return null;
    }

    return $admin;
}

// 页面守卫：未登录时跳转登录页
function requireAdmin(): array
{
    $admin = currentAdmin();
    if ($admin === null) {
        header('Location: index.php');
        exit;
    }
    return $admin;
}

// 登录成功后建立管理员会话（并重生成会话 ID 防止会话固定攻击）
function grantAdminSession(array $identity, string $via): void
{
    initSecurity();
    session_regenerate_id(true);

    $_SESSION['admin'] = [
        'identity' => $identity,
        'identity_label' => $identity['label'],
        'verified_at' => time(),
        'via' => $via,
    ];
}

// 校验用户是否属于 Flarum 管理员用户组。
// 优先使用站点的管理 API 令牌查询目标用户，其次使用 OAuth 访问令牌查询当前用户。
// 返回 ['id', 'username', 'email', 'forum_admin']，查询失败返回 null（fail-closed）。
function adminVerifyForumUser(string $oauthAccessToken): ?array
{
    $userId = null;
    $username = '';
    $email = '';

    // 第一步：获取论坛用户基本信息（OAuth 访问令牌）
    $userResult = oauthFetchFlarumUser($oauthAccessToken);
    if ($userResult === null) {
        return null;
    }
    $userId = $userResult['id'];
    $username = $userResult['username'];
    $email = $userResult['email'];

    // 第二步：校验用户组。优先使用管理 API 令牌
    $forumAdmin = false;

    $adminToken = defined('API_X_CSRF_TOKEN') ? (string)API_X_CSRF_TOKEN : '';
    if ($adminToken !== '' && strpos($adminToken, 'YOUR_') !== 0) {
        $headers = [
            'Authorization: Token ' . $adminToken,
            'User-Agent: b18-exam/' . VERSION . ' b18-admin-php/1.0.0',
        ];
        $ch = curl_init(oauthSiteUrl(API_SITE, '/api/users/' . rawurlencode($userId) . '?include=groups'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$error && $statusCode === 200) {
            $payload = json_decode($response, true);
            if (is_array($payload)) {
                $forumAdmin = adminHasGroup($payload, ADMIN_GROUP_ID);
            }
        }
    }

    if (!$forumAdmin) {
        // 回退：使用 OAuth 访问令牌查询当前用户并携带 include=groups
        $fallback = oauthHttpGet(oauthSiteUrl(API_SITE, '/api/user') . '?include=groups', $oauthAccessToken, 'access_token');
        if ($fallback !== null && isset($fallback['data'])) {
            $forumAdmin = adminHasGroup($fallback, ADMIN_GROUP_ID);
        }
    }

    return [
        'id' => (string)$userId,
        'username' => $username,
        'email' => $email,
        'forum_admin' => $forumAdmin,
    ];
}

// 检查 Flarum API 响应中是否包含指定用户组（relationships.groups 或 included）
function adminHasGroup(array $payload, $groupId): bool
{
    $groupId = (string)$groupId;

    foreach ($payload['data']['relationships']['groups']['data'] ?? [] as $group) {
        if (isset($group['id']) && (string)$group['id'] === $groupId) {
            return true;
        }
    }

    foreach ($payload['included'] ?? [] as $included) {
        if (($included['type'] ?? '') === 'groups' && isset($included['id']) && (string)$included['id'] === $groupId) {
            return true;
        }
    }

    return false;
}

// 校验用户是否为千万桥（MAS）管理员。
// 通过 MAS 管理 API 按用户名（localpart）查询用户并检查 admin 属性，
// 再结合实例服务器名拼装 MXID（验证结果必须包含合法 MXID）。
// 返回 ['username', 'mxid']；校验失败、API 不可用或无法确定 MXID 时返回 null（fail-closed）。
function adminVerifyMatrixUser(string $username): ?array
{
    require_once __DIR__ . '/../includes/matrix-api.php';

    $username = strtolower(trim($username));
    if (!isValidMatrixUsername($username)) {
        return null;
    }

    $result = matrixApiRequest('GET', '/api/admin/v1/users/by-username/' . rawurlencode($username));

    if ($result === null || $result['status'] !== 200) {
        return null;
    }

    $attributes = $result['data']['data']['attributes'] ?? null;
    if (!is_array($attributes) || empty($attributes['admin'])) {
        return null;
    }

    $serverName = masServerName();
    if ($serverName === null) {
        return null;
    }

    $mxid = '@' . $username . ':' . $serverName;
    if (!isValidMxid($mxid)) {
        return null;
    }

    return [
        'username' => $username,
        'mxid' => $mxid,
    ];
}

// 获取 MAS 实例的服务器名（用于拼装 MXID），失败时返回 null
function masServerName(): ?string
{
    static $serverName = false;

    if ($serverName !== false) {
        return $serverName;
    }

    require_once __DIR__ . '/../includes/matrix-api.php';
    $result = matrixApiRequest('GET', '/api/admin/v1/site-config');

    if ($result === null || $result['status'] !== 200 || empty($result['data']['server_name'])) {
        $serverName = null;
        return null;
    }

    $serverName = (string)$result['data']['server_name'];
    return $serverName;
}

// 输出管理员登录流程的错误页，与前台 oauthErrorPage() 共用渲染函数
function adminErrorPage(string $title, string $message): void
{
    oauthRenderErrorPage(
        $title,
        $message,
        '..',
        __DIR__ . '/views/nav.php',
        __DIR__ . '/views/footer.php',
        'index.php',
        '返回登录',
        'form-card form-card-narrow mx-auto'
    );
}
