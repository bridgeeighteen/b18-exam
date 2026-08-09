<?php

// PHP 内置服务器开发路由（可选）：php -S 127.0.0.1:8080 router.php
// 将 /api/v1/... 转发给 api/index.php（对应生产环境 .htaccess 的重写规则），
// 其余请求按原路径交给内置服务器处理。生产环境（Apache / Nginx）无需此文件。

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/api/v1(/|$)#', $path)) {
    $_SERVER['PATH_INFO'] = substr($path, 4); // 去掉 /api 前缀，保留 /v1/...
    require __DIR__ . '/api/index.php';
    return true;
}

return false;
