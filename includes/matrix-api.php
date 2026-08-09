<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/version.php';

// Matrix Authentication Service（MAS）管理 API 客户端。
// 所有请求均使用具有 urn:mas:admin 作用域的个人访问令牌进行身份验证，
// 参见 https://element-hq.github.io/matrix-authentication-service/api/

// 拼接完整的 API 请求地址
function matrixApiUrl(string $path): string
{
    $site = MATRIX_API_SITE;
    if (strpos($site, 'http://') !== 0 && strpos($site, 'https://') !== 0) {
        $site = 'https://' . $site;
    }
    return rtrim($site, '/') . $path;
}

// 向 MAS 管理 API 发送请求，返回 ['status' => int, 'data' => array]，
// 网络错误或无法解析的响应返回 null（调用方必须按失败处理，不得发放 Token）。
function matrixApiRequest(string $method, string $path, array $payload = []): ?array
{
    $ch = curl_init(matrixApiUrl($path));
    $headers = [
        'Content-Type: application/json; charset=UTF-8',
        'Authorization: Bearer ' . MATRIX_API_TOKEN,
        'User-Agent: b18-exam/' . VERSION . ' b18-matrix-php/1.0.0',
    ];

    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ];

    if (!empty($payload)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($payload);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        error_log("Matrix API cURL 错误：" . $error);
        return null;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Matrix API 收到了未知的 JSON 回应：" . $response);
        return null;
    }

    return ['status' => $statusCode, 'data' => $data];
}

// 检查用户名是否已在实例上实际注册使用（GET /api/admin/v1/users/by-username/{username}）。
// 返回 true 表示已被实际使用，false 表示未被使用，null 表示 API 不可用。
function masUsernameInUse(string $username): ?bool
{
    $result = matrixApiRequest('GET', '/api/admin/v1/users/by-username/' . rawurlencode($username));

    if ($result === null) {
        return null;
    }

    if ($result['status'] === 200) {
        return true;
    }

    if ($result['status'] === 404) {
        return false;
    }

    error_log("Matrix API 查询用户名返回了异常的 HTTP 状态码：" . $result['status']);
    return null;
}

// 在 MAS 中创建注册 Token（POST /api/admin/v1/user-registration-tokens）。
// 成功时返回 Token 字符串，失败时返回 null。
function masCreateRegistrationToken(string $token): ?string
{
    $payload = [
        'token' => $token,
        'usage_limit' => MATRIX_TOKEN_USAGE_LIMIT,
    ];

    if (defined('MATRIX_TOKEN_EXPIRY_DAYS') && (int)MATRIX_TOKEN_EXPIRY_DAYS > 0) {
        $expiry = new DateTime('+' . (int)MATRIX_TOKEN_EXPIRY_DAYS . ' days', new DateTimeZone('UTC'));
        $payload['expires_at'] = $expiry->format('Y-m-d\TH:i:s\Z');
    }

    $result = matrixApiRequest('POST', '/api/admin/v1/user-registration-tokens', $payload);

    if ($result === null || $result['status'] !== 201) {
        if ($result !== null) {
            error_log("Matrix API 创建注册 Token 返回了异常的 HTTP 状态码：" . $result['status']);
        }
        return null;
    }

    if (!isset($result['data']['data']['attributes']['token'])) {
        error_log("Matrix API 创建注册 Token 返回了意外的响应结构：" . json_encode($result['data']));
        return null;
    }

    return $result['data']['data']['attributes']['token'];
}

// 生成随机注册 Token 字符串（字母与数字）
function generateMatrixToken(): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charsArray = [];
    for ($i = 0; $i < 8; $i++) {
        $charsArray[] = $characters[random_int(0, strlen($characters) - 1)];
    }
    return implode('', $charsArray);
}

// 规范化用户名：去除首尾空白并转为小写（MAS 用户名为小写 localpart）
function normalizeMatrixUsername(string $username): string
{
    return strtolower(trim($username));
}

// 校验用户名是否符合 MAS localpart 规则（小写字母、数字及 . _ = -）
function isValidMatrixUsername(string $username): bool
{
    return preg_match('/^[a-z0-9._=-]{1,254}$/', $username) === 1;
}
