<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/matrix-api.php';

// 通用 OAuth 2.0 客户端，供以下四套授权流程共用：
// - oauth-matrix.php：Matrix Authentication Service（MAS）作为 OAuth 提供方，用户登录其
//   Matrix 账号后，在论坛入站测试中免考基本礼仪题（直接获得该部分满分）；
// - oauth-forum.php：Flarum（FoskyM/flarum-oauth-center）作为 OAuth 提供方，用户登录其
//   论坛账号后，在 Matrix 实例礼仪测试中免考（直接获得满分并发放注册 Token）；
// - admin/oauth-matrix.php：使用千万桥（MAS）账号 OAuth 登录管理面板；
// - admin/oauth.php：使用论坛账号 OAuth 登录管理面板。
//
// 各入口页面只需调用 oauthRunFlow()（传入 oauthMasConfig() / oauthFlarumConfig()
// 的配置）处理授权流程，并把失败结果映射到各自的提示页即可。
//
// 面向 MAS 的客户端凭据优先使用 config.php 中静态配置的 MAS_OAUTH_CLIENT_ID /
// MAS_OAUTH_CLIENT_SECRET；未配置时，通过 OAuth 2.0 动态客户端注册协议
// （RFC 7591，POST <MAS>/oauth2/registration）在首次使用时自动注册，并把注册
// 返回的凭据保存到 mas_oauth_clients 数据表（见 oauthMasCredentials()）。

// 拼接 OAuth 提供方地址（自动补齐 https:// 前缀）
function oauthSiteUrl(string $site, string $path): string
{
    if (strpos($site, 'http://') !== 0 && strpos($site, 'https://') !== 0) {
        $site = 'https://' . $site;
    }
    return rtrim($site, '/') . $path;
}

// 以表单编码 POST 发起请求（OAuth token / revoke 端点），返回 ['status' => int, 'data' => array]，
// 网络错误或无法解析的响应返回 null（调用方必须按失败处理，不得发放任何免考资格）。
function oauthHttpPostForm(string $url, array $fields): ?array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'b18-exam/' . VERSION . ' b18-codegen-php/1.0.0');
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log("OAuth 请求 cURL 错误：" . $error);
        return null;
    }

    if (trim($response) === '') {
        return ['status' => $statusCode, 'data' => []];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("OAuth 请求收到了未知的 JSON 回应：" . $response);
        return null;
    }

    return ['status' => $statusCode, 'data' => $data];
}

// 以 JSON 编码 POST 发起请求（RFC 7591 客户端注册端点），返回 ['status' => int, 'data' => array]，
// 网络错误或无法解析的响应返回 null。
function oauthHttpPostJson(string $url, array $payload): ?array
{
    $json = json_encode($payload);
    if ($json === false) {
        error_log("OAuth 注册请求编码失败：" . json_last_error_msg());
        return null;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: b18-exam/' . VERSION . ' b18-codegen-php/1.0.0',
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log("OAuth 注册请求 cURL 错误：" . $error);
        return null;
    }

    if (trim($response) === '') {
        return ['status' => $statusCode, 'data' => []];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("OAuth 注册请求收到了未知的 JSON 回应：" . $response);
        return null;
    }

    return ['status' => $statusCode, 'data' => $data];
}

// 携带访问令牌发起 GET 请求；$queryParam 非空时以查询参数形式传递（Flarum 资源端点），
// 否则使用 Authorization: Bearer 头（MAS userinfo 端点）。
function oauthHttpGet(string $url, string $accessToken, ?string $queryParam = null): ?array
{
    if ($queryParam !== null) {
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . rawurlencode($queryParam) . '=' . rawurlencode($accessToken);
        $headers = [
            'User-Agent: b18-exam/' . VERSION . ' b18-codegen-php/1.0.0',
        ];
    } else {
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: b18-exam/' . VERSION . ' b18-codegen-php/1.0.0',
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        error_log("OAuth 资源请求 cURL 错误：" . $error);
        return null;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("OAuth 资源请求收到了未知的 JSON 回应：" . $response);
        return null;
    }

    return ['status' => $statusCode, 'data' => $data];
}

// 生成并保存 OAuth state（防 CSRF），返回 state 值
function oauthStartState(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    return $state;
}

// 校验 OAuth 回调中的 state 与本次会话发起的一致
function oauthVerifyState(?string $state): bool
{
    return isset($state, $_SESSION['oauth_state'])
        && hash_equals($_SESSION['oauth_state'], $state);
}

// 清除本次会话中的 OAuth state
function oauthClearState(): void
{
    unset($_SESSION['oauth_state']);
}

// 生成 PKCE 验证器并保存（仅 MAS 提供方支持 PKCE），返回验证器
function oauthStartPkce(): string
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $_SESSION['oauth_pkce_verifier'] = $verifier;
    return $verifier;
}

// 计算 PKCE S256 挑战值
function oauthPkceChallenge(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

// 取出并清除 PKCE 验证器
function oauthTakePkceVerifier(): ?string
{
    $verifier = $_SESSION['oauth_pkce_verifier'] ?? null;
    unset($_SESSION['oauth_pkce_verifier']);
    return is_string($verifier) ? $verifier : null;
}

// 使用授权码交换访问令牌（authorization_code 授权类型）
function oauthExchangeAuthorizationCode(
    string $tokenEndpoint,
    string $clientId,
    string $clientSecret,
    string $code,
    string $redirectUri,
    ?string $codeVerifier = null
): ?array {
    $fields = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ];

    if ($codeVerifier !== null) {
        $fields['code_verifier'] = $codeVerifier;
    }

    return oauthHttpPostForm($tokenEndpoint, $fields);
}

// 吊销已经用完的访问令牌（MAS /oauth2/revoke，尽力而为，失败不影响流程）
function oauthRevokeToken(string $revokeEndpoint, string $clientId, string $clientSecret, string $accessToken): void
{
    oauthHttpPostForm($revokeEndpoint, [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'token' => $accessToken,
    ]);
}

// 校验 Matrix 用户 ID（MXID）格式，如 @user:example.com
function isValidMxid(string $mxid): bool
{
    return preg_match('/^@[A-Za-z0-9._=\/-]+:[A-Za-z0-9.-]+$/', $mxid) === 1;
}

// 邮箱是否匹配（忽略大小写与首尾空白比较）
function oauthEmailsMatch(string $a, string $b): bool
{
    return strcasecmp(trim($a), trim($b)) === 0;
}

// 解析 Flarum 用户信息为统一结构：['id', 'username', 'email']。
// 兼容两种响应形态：
// - 标准 JSON:API 信封（data 内为资源，字段位于 attributes 中）；
// - FoskyM/flarum-oauth-center 的平铺响应（其 ApiUserController 覆盖了 Flarum 的
//   /api/user 端点，返回 {"id": 7, "username": "...", "email": "...", ...}，无 attributes 包裹）。
// 缺少 id 时返回 null（fail-closed）。
function oauthFlarumUserFromData(array $data): ?array
{
    $resource = is_array($data['data'] ?? null) ? $data['data'] : $data;
    if (!isset($resource['id'])) {
        return null;
    }
    $attributes = $resource['attributes'] ?? [];
    $username = isset($attributes['username']) ? (string)$attributes['username'] : '';
    $email = isset($attributes['email']) && is_string($attributes['email']) ? $attributes['email'] : '';

    // OAuth Center 平铺响应：username / email 位于资源顶层，而非 attributes 内
    if ($username === '' && isset($resource['username']) && is_scalar($resource['username'])) {
        $username = (string)$resource['username'];
    }
    if ($email === '' && isset($resource['email']) && is_string($resource['email'])) {
        $email = $resource['email'];
    }

    return [
        'id' => $resource['id'],
        'username' => $username,
        'email' => $email,
    ];
}

// 使用访问令牌从 Flarum 获取当前用户信息，返回 oauthFlarumUserFromData() 的结构，
// 请求失败或响应缺失 id 时返回 null（fail-closed）。
function oauthFetchFlarumUser(string $accessToken): ?array
{
    $userResult = oauthHttpGet(oauthSiteUrl(API_SITE, '/api/user'), $accessToken, 'access_token');
    if ($userResult === null || !isset($userResult['data'])) {
        return null;
    }
    return oauthFlarumUserFromData($userResult['data']);
}

// ---- RFC 7591 动态客户端注册（MAS）----

// 注册请求携带的客户端元数据（RFC 7591 §2）。MAS 的策略要求 client_uri 与
// redirect_uris 均为同一 HTTPS 主机下的合法地址（本地测试环境需在 MAS 的
// policy.data.client_registration 中放宽 allow_insecure_uris 等限制）。
function oauthMasClientMetadata(): array
{
    $site = 'https://' . SITE;
    return [
        'application_type' => 'web',
        'client_name' => '入站测试系统',
        'client_uri' => $site . '/',
        'redirect_uris' => [
            $site . '/oauth-matrix.php',
            $site . '/admin/oauth-matrix.php',
        ],
        'grant_types' => ['authorization_code'],
        'response_types' => ['code'],
        // 仅请求 openid：MAS 的 userinfo 端点只返回 sub（用户 ULID）与 username
        // （localpart），并不返回 email 等其它声明；免考流程的邮箱核对改由
        // 管理 API（GET /api/admin/v1/user-emails）完成，见 oauth-matrix.php。
        'scope' => 'openid',
        // 与 oauthExchangeAuthorizationCode() 一致：凭据以表单字段形式提交
        'token_endpoint_auth_method' => 'client_secret_post',
    ];
}

// 通过 RFC 7591 动态客户端注册协议向 MAS 注册客户端，返回
// ['client_id' => string, 'client_secret' => string]；请求失败、状态码不是
// 201 或响应缺少凭据时返回 null（fail-closed）。客户端私钥由 MAS 生成。
function oauthRegisterMasClient(): ?array
{
    $result = oauthHttpPostJson(
        oauthSiteUrl(MATRIX_API_SITE, '/oauth2/registration'),
        oauthMasClientMetadata()
    );

    if ($result === null || $result['status'] !== 201 || !isset($result['data']['client_id'])) {
        $detail = is_array($result) ? json_encode($result['data'], JSON_UNESCAPED_UNICODE) : '网络错误';
        error_log("MAS OAuth 客户端注册失败：" . $detail);
        return null;
    }

    $clientId = $result['data']['client_id'];
    $clientSecret = isset($result['data']['client_secret']) && is_string($result['data']['client_secret'])
        ? $result['data']['client_secret']
        : '';

    if (!is_string($clientId) || $clientId === '' || $clientSecret === '') {
        error_log("MAS OAuth 客户端注册响应缺少 client_id 或 client_secret");
        return null;
    }

    return ['client_id' => $clientId, 'client_secret' => $clientSecret];
}

// 从 mas_oauth_clients 数据表读取动态注册的客户端凭据，无记录或结构不完整时返回 null
function oauthMasCredentialsFromDb(): ?array
{
    try {
        $row = getPDO(true)->query('SELECT client_id, client_secret FROM mas_oauth_clients ORDER BY id DESC LIMIT 1')->fetch();
    } catch (Throwable $e) {
        error_log("读取 MAS OAuth 客户端凭据失败：" . $e->getMessage());
        return null;
    }

    if (!is_array($row) || !is_string($row['client_id'] ?? null) || $row['client_id'] === ''
        || !is_string($row['client_secret'] ?? null) || $row['client_secret'] === '') {
        return null;
    }

    return ['client_id' => $row['client_id'], 'client_secret' => $row['client_secret']];
}

// 把动态注册获得的客户端凭据保存到 mas_oauth_clients 数据表，失败时返回 false
function oauthMasStoreCredentials(array $creds): bool
{
    try {
        $stmt = getPDO(true)->prepare('INSERT INTO mas_oauth_clients (client_id, client_secret) VALUES (?, ?)');
        return $stmt->execute([$creds['client_id'], $creds['client_secret']]);
    } catch (Throwable $e) {
        error_log("保存 MAS OAuth 客户端凭据失败：" . $e->getMessage());
        return false;
    }
}

// 解析当前可用的 MAS OAuth 客户端凭据，优先级：
// 1. config.php 中静态配置的 MAS_OAUTH_CLIENT_ID / MAS_OAUTH_CLIENT_SECRET（兜底）；
// 2. mas_oauth_clients 数据表中已动态注册的凭据；
// 3. 通过 RFC 7591 动态客户端注册协议向 MAS 注册并保存。
// 全部不可用时返回 null（fail-closed）。动态注册过程持有数据库锁
// （mas_oauth_register），防止并发访问时重复注册产生多个孤儿客户端。
function oauthMasCredentials(): ?array
{
    if (defined('MAS_OAUTH_CLIENT_ID') && defined('MAS_OAUTH_CLIENT_SECRET')
        && MAS_OAUTH_CLIENT_ID !== '' && strpos(MAS_OAUTH_CLIENT_ID, 'YOUR_') !== 0
        && MAS_OAUTH_CLIENT_SECRET !== '' && strpos(MAS_OAUTH_CLIENT_SECRET, 'YOUR_') !== 0) {
        return ['client_id' => MAS_OAUTH_CLIENT_ID, 'client_secret' => MAS_OAUTH_CLIENT_SECRET];
    }

    $fromDb = oauthMasCredentialsFromDb();
    if ($fromDb !== null) {
        return $fromDb;
    }

    $pdo = null;
    try {
        $pdo = getPDO(true);
        $pdo->query('SELECT GET_LOCK("mas_oauth_register", 10)')->fetchColumn();

        // 持锁后再查一次，另一并发请求可能已经完成注册
        $fromDb = oauthMasCredentialsFromDb();
        if ($fromDb !== null) {
            return $fromDb;
        }

        $registered = oauthRegisterMasClient();
        if ($registered === null) {
            return null;
        }

        if (oauthMasStoreCredentials($registered)) {
            return $registered;
        }

        // 保存失败（如数据表缺失）时仍返回本次注册结果，让当前流程可用，
        // 但下一次请求会重新注册，故记录日志便于排查
        error_log("MAS OAuth 客户端凭据保存失败，本次注册的 client_id：" . $registered['client_id']);
        return $registered;
    } catch (Throwable $e) {
        error_log("MAS OAuth 客户端凭据解析失败：" . $e->getMessage());
        return null;
    } finally {
        if ($pdo !== null) {
            try {
                $pdo->query('SELECT RELEASE_LOCK("mas_oauth_register")')->fetchColumn();
            } catch (Throwable $e) {
                // 释放锁失败不影响流程，连接关闭时锁会自动释放
            }
        }
    }
}

// Matrix（MAS）提供方配置，返回的配置直接交给 oauthRunFlow() 使用。
// 客户端凭据通过 oauthMasCredentials() 解析（静态配置优先，否则动态注册）。
// $scope 仅用于授权请求，两条 MAS 流程都只需 'openid'（MAS 的 userinfo 端点
// 只返回 sub 与 username 两个声明，详见下方 user_parser）。
// 解析结果中的用户结构为 ['username'（localpart）, 'mxid', 'subject'（MAS 用户 ULID）, 'email']。
// 注意：MAS userinfo 的 sub 是用户内部 ULID（如 01J5Y2GZ...），不是 MXID；
// MXID 由 localpart 与实例服务器名拼装（masServerName()），邮箱不存在于
// userinfo 中，由 oauth-matrix.php 通过管理 API 另行核验。
function oauthMasConfig(string $redirectUri, string $scope = 'openid'): array
{
    $credentials = oauthMasCredentials();
    return [
        'site' => MATRIX_API_SITE,
        'client_id' => $credentials['client_id'] ?? '',
        'client_secret' => $credentials['client_secret'] ?? '',
        'redirect_uri' => $redirectUri,
        'authorize_path' => '/authorize',
        'token_path' => '/oauth2/token',
        'user_path' => '/oauth2/userinfo',
        'revoke_path' => '/oauth2/revoke',
        'scope' => $scope,
        'use_pkce' => true,
        'fetch_user' => true,
        'revoke' => true,
        'user_query_param' => null,
        'user_parser' => static function (array $data): array {
            // MAS userinfo 响应形如 {"sub": "<用户 ULID>", "username": "<localpart>"}
            $username = isset($data['username']) && is_string($data['username'])
                ? strtolower(trim($data['username'])) : '';
            if ($username === '' || !isValidMatrixUsername($username)) {
                error_log("MAS userinfo 解析失败：username 缺失或格式不正确 - " . json_encode($data));
                return ['error' => 'user_invalid'];
            }

            $serverName = masServerName();
            if ($serverName === null) {
                error_log("MAS userinfo 解析失败：无法获取实例服务器名以拼装 MXID");
                return ['error' => 'user'];
            }

            $mxid = '@' . $username . ':' . $serverName;
            if (!isValidMxid($mxid)) {
                error_log("MAS userinfo 解析失败：拼装出的 MXID 格式不正确 - " . $mxid);
                return ['error' => 'user_invalid'];
            }

            $subject = isset($data['sub']) && is_string($data['sub']) ? $data['sub'] : '';
            $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
            return [
                'user' => [
                    'username' => $username,
                    'mxid' => $mxid,
                    'subject' => $subject,
                    'email' => $email,
                ],
            ];
        },
    ];
}

// Flarum（FoskyM/flarum-oauth-center）提供方配置，返回的配置直接交给 oauthRunFlow() 使用。
// $fetchUser 为 false 时跳过用户信息获取，仅用于管理面板登录流程（用户校验由
// adminVerifyForumUser() 完成）。
function oauthFlarumConfig(string $redirectUri, string $clientId, string $clientSecret, bool $fetchUser = true): array
{
    return [
        'site' => API_SITE,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'authorize_path' => '/oauth/authorize',
        'token_path' => '/oauth/token',
        'user_path' => '/api/user',
        'revoke_path' => null,
        'scope' => 'user.read',
        'use_pkce' => false,
        'fetch_user' => $fetchUser,
        'revoke' => false,
        'user_query_param' => 'access_token',
        'user_parser' => static function (array $data): array {
            // 兼容 JSON:API 信封与 OAuth Center 平铺两种响应形态，
            // 均交由 oauthFlarumUserFromData() 统一解析
            $user = oauthFlarumUserFromData($data);
            if ($user === null) {
                return ['error' => 'user'];
            }
            return ['user' => $user];
        },
    ];
}

// 运行一套完整的 OAuth 2.0 授权码流程（发起 + 回调）：
// - 请求中没有 code 时视为第一步：生成 state（MAS 额外生成 PKCE 验证器），
//   跳转至提供方的授权端点并终止脚本（本函数不再返回）；
// - 请求中有 code 时视为第二步：校验 state、交换授权码、按需获取用户信息、
//   按需吊销访问令牌，返回统一结果：
//     ['ok' => true, 'access_token' => string, 'user' => array]（fetch_user 为 false 时无 user）
//     ['ok' => false, 'error' => 'state'|'token'|'user'|'user_invalid']
//   失败结果由调用方映射为各自的提示页文案。
function oauthRunFlow(array $cfg): array
{
    if (!isset($_GET['code'])) {
        // 提供方拒绝了授权请求（如 unsupported_response_type / unauthorized_client /
        // invalid_scope 等），回调中只带有 error / error_description / state 而没有 code。
        // 此时不得重启流程（否则会与提供方之间无限重定向），直接返回失败结果，
        // 由入口页面展示提供方给出的错误信息，便于定位注册的客户端元数据问题。
        if (isset($_GET['error'])) {
            $providerError = is_string($_GET['error']) ? $_GET['error'] : '';
            $providerDescription = isset($_GET['error_description']) && is_string($_GET['error_description'])
                ? $_GET['error_description'] : '';
            error_log("OAuth 提供方拒绝了授权请求：" . $providerError . ($providerDescription !== '' ? ' - ' . $providerDescription : ''));
            return [
                'ok' => false,
                'error' => 'provider',
                'provider_error' => $providerError,
                'provider_description' => $providerDescription,
            ];
        }

        $params = [
            'client_id' => $cfg['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $cfg['redirect_uri'],
            'scope' => $cfg['scope'],
            'state' => oauthStartState(),
        ];
        if ($cfg['use_pkce']) {
            $verifier = oauthStartPkce();
            $params['code_challenge'] = oauthPkceChallenge($verifier);
            $params['code_challenge_method'] = 'S256';
        }
        header('Location: ' . oauthSiteUrl($cfg['site'], $cfg['authorize_path']) . '?' . http_build_query($params));
        exit;
    }

    if (!oauthVerifyState($_GET['state'] ?? null)) {
        return ['ok' => false, 'error' => 'state'];
    }
    oauthClearState();

    $tokenResponse = oauthExchangeAuthorizationCode(
        oauthSiteUrl($cfg['site'], $cfg['token_path']),
        $cfg['client_id'],
        $cfg['client_secret'],
        $_GET['code'],
        $cfg['redirect_uri'],
        $cfg['use_pkce'] ? oauthTakePkceVerifier() : null
    );

    if ($tokenResponse === null || !isset($tokenResponse['data']['access_token'])) {
        return ['ok' => false, 'error' => 'token'];
    }

    $accessToken = $tokenResponse['data']['access_token'];

    if (!$cfg['fetch_user']) {
        return ['ok' => true, 'access_token' => $accessToken];
    }

    $userData = $cfg['user_query_param'] !== null
        ? oauthHttpGet(oauthSiteUrl($cfg['site'], $cfg['user_path']), $accessToken, $cfg['user_query_param'])
        : oauthHttpGet(oauthSiteUrl($cfg['site'], $cfg['user_path']), $accessToken);

    // 用完即吊销访问令牌，不在本系统留存（尽力而为，失败不影响流程）
    if ($cfg['revoke'] && $cfg['revoke_path'] !== null) {
        oauthRevokeToken(oauthSiteUrl($cfg['site'], $cfg['revoke_path']), $cfg['client_id'], $cfg['client_secret'], $accessToken);
    }

    if ($userData === null || !isset($userData['data'])) {
        return ['ok' => false, 'error' => 'user'];
    }

    $parsed = $cfg['user_parser']($userData['data']);
    if (isset($parsed['error'])) {
        return ['ok' => false, 'error' => $parsed['error']];
    }

    return ['ok' => true, 'access_token' => $accessToken, 'user' => $parsed['user']];
}

// 读取本次会话中已完成的 Matrix（MAS）OAuth 验证，结构不完整时返回 null。
// emails 为 MAS 账号绑定的邮箱列表（小写，由管理 API 核验），可为空数组。
function matrixOAuthVerified(): ?array
{
    $data = $_SESSION['matrix_oauth'] ?? null;
    if (!is_array($data) || !isset($data['mxid'], $data['emails'], $data['verified_at'])
        || !is_array($data['emails'])) {
        return null;
    }
    return $data;
}

// 读取本次会话中已完成的论坛（Flarum）OAuth 验证，结构不完整时返回 null
function forumOAuthVerified(): ?array
{
    $data = $_SESSION['forum_oauth'] ?? null;
    if (!is_array($data) || !isset($data['user_id'], $data['username'], $data['email'], $data['verified_at'])) {
        return null;
    }
    return $data;
}

// 论坛 OAuth 免考是否已正确配置
function forumOAuthEnabled(): bool
{
    return defined('FORUM_OAUTH_ENABLED') && FORUM_OAUTH_ENABLED
        && defined('FORUM_OAUTH_CLIENT_ID') && FORUM_OAUTH_CLIENT_ID !== '' && strpos(FORUM_OAUTH_CLIENT_ID, 'YOUR_') !== 0
        && defined('FORUM_OAUTH_CLIENT_SECRET') && FORUM_OAUTH_CLIENT_SECRET !== '' && strpos(FORUM_OAUTH_CLIENT_SECRET, 'YOUR_') !== 0;
}

// Matrix（MAS）OAuth 免考是否已启用。仅做配置检查（不发起点点网络或数据库请求），
// 入口页面据此显示登录入口；实际的客户端凭据由 oauthMasCredentials() 解析：
// 优先使用 config.php 中静态配置的凭据，否则在首次使用时通过 RFC 7591 动态
// 客户端注册协议向 MAS 注册并保存到 mas_oauth_clients 数据表。
function masOAuthEnabled(): bool
{
    return defined('MAS_OAUTH_ENABLED') && MAS_OAUTH_ENABLED
        && defined('MATRIX_API_SITE') && MATRIX_API_SITE !== ''
        && defined('SITE') && SITE !== '';
}

// 完成 OAuth 验证后跳回信息登记页面（target 用于自动选中对应表单）
function oauthRedirectBack(string $target = 'forum'): void
{
    $allowed = ['forum', 'matrix'];
    $target = in_array($target, $allowed, true) ? $target : 'forum';
    header('Location: info.php?target=' . $target);
    exit;
}

// 输出 OAuth 失败提示页（前台流程共用，样式与其它页面保持一致），随后终止脚本
function oauthErrorPage(string $title, string $message): void
{
    oauthRenderErrorPage(
        $title,
        $message,
        '.',
        __DIR__ . '/../views/nav.php',
        __DIR__ . '/../views/footer.php',
        'info.php',
        '返回信息登记',
        'result-card'
    );
}

// 输出 OAuth 失败提示页（管理面板流程共用，样式与其它页面保持一致），随后终止脚本。
// 与 oauthErrorPage() 共用同一渲染函数，仅路径与返回链接不同。
function oauthRenderErrorPage(
    string $title,
    string $message,
    string $assetPrefix,
    string $navFile,
    string $footerFile,
    string $backHref,
    string $backLabel,
    string $cardClass
): void {
    sendSecurityHeaders();
    $name = defined('ADMIN_EMAIL_NAME') ? ADMIN_EMAIL_NAME : 'admin';
    $domain = defined('ADMIN_EMAIL_DOMAIN') ? ADMIN_EMAIL_DOMAIN : 'example.com';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - 十八桥社区入站测试系统</title>
        <link rel="stylesheet" href="<?php echo $assetPrefix; ?>/vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="<?php echo $assetPrefix; ?>/views/assets/css/noto-face.css">
        <link rel="stylesheet" href="<?php echo $assetPrefix; ?>/views/assets/css/tokens.css">
    </head>
    <?php require $navFile; ?>
    <div class="page">
        <div class="card <?php echo $cardClass; ?>">
            <div class="card-body">
                <h1 class="page-title"><?php echo htmlspecialchars($title); ?></h1>
                <div class="alert alert-danger mt-4" role="alert"><?php echo nl2br(htmlspecialchars($message)); ?></div>
                <p class="card-text">如果此问题持续存在，请截屏此页面然后向<a
                        href="javascript:location.href = 'mailto:' + ['<?php echo htmlspecialchars($name); ?>','<?php echo htmlspecialchars($domain); ?>'].join('@')">管理邮箱</a>发送电子邮件。</p>
                <a class="btn btn-primary" href="<?php echo $backHref; ?>" role="button"><?php echo $backLabel; ?></a>
            </div>
        </div>
    </div>
    <?php require $footerFile; ?>
    <?php
    exit;
}
