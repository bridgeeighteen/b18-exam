<?php
require_once __DIR__ . '/../config.php';
require_once 'check_access.php';

session_start();

$api_site = API_SITE;
$client_id = OAUTH_CLIENT_ID;
$client_secret = OAUTH_CLIENT_SECRET;
$redirect_uri = 'http://' . SITE . '/admin/oauth.php';

if (!isset($_GET['code'])) {
    header("Location: https://{$api_site}/oauth/authorize?client_id={$client_id}&response_type=code&redirect_uri={$redirect_uri}&scope=user.read");
    exit;
} else {
    $code = $_GET['code'];

    $data = array(
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://' . $api_site . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    $_SESSION['access_token'] = $response->access_token;

    if (check_access($_SESSION['access_token'])) {
        header("Location: /admin/dashboard.php");
    } else {
        header("Location: /index.php"); 
    }
    exit;
}