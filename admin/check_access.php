<?php

require_once __DIR__ . '/../config.php';
function check_access($access_token) {
    // 使用 Flarum API 获取用户信息
    $url = 'https://' . API_SITE . '/api/user?access_token=' . $access_token;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $user_data = json_decode($response, true);

    // 检查用户 ID 是否为 1
    return isset($user_data['id']) && $user_data['id'] === 1;
}