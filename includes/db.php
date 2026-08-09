<?php

require_once __DIR__ . '/../config.php';

if (DB_TIMEZONE_LOCK) {
} else {
    date_default_timezone_set(PHP_TIMEZONE);
}

// Establish database connection
function connectToDatabase()
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Close the database connection
function closeDatabaseConnection($conn)
{
    $conn->close();
}

// Establish a shared PDO connection (for admin panel and API)
// $throwOnFailure 为 true 时连接失败抛出 PDOException（供 API 健康检查等场景自行处理），否则直接输出错误并终止脚本
function getPDO(bool $throwOnFailure = false): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("数据库连接失败：" . $e->getMessage());
            if ($throwOnFailure) {
                throw $e;
            }
            die("数据库连接失败。如果问题依旧存在，请稍后重试。");
        }
    }

    return $pdo;
}
