<?php
// 使用方法：在本文件中修改配置，然后重命名为 config.php。
// 修改时确保只对 define 函数最后的字符串作出修改，否则将导致系统无法获取配置。
define('SITE', 'example.com'); // 部署系统的域名，部分场景下会使用

// MySQL 数据库配置
define('DB_HOST', 'localhost:3306'); // 数据库主机
define('DB_NAME', 'YOUR_DATABASE_NAME'); // 数据库名
define('DB_USER', 'USERNAME'); // 数据库用户名
define('DB_PASS', 'PASSWORD'); // 数据库密码

// API 配置
define('API_SITE', 'www.bridge18.rr.nu'); // Flarum 站点域名
define('API_X_CSRF_TOKEN', 'YOUR_API_X_CSRF_TOKEN'); // Flarum API 的令牌，直接从个人设置 -> 安全页面创建并获取

// 其他配置
define('CODE_TYPE', 'B18R'); // 邀请码类型，出现在其前缀
define('GROUP_ID', 3); // 邀请码创建用户所属用户组，默认注册用户（3）
define('MAX_USES', 1); // 邀请码最大使用次数，默认为 1
define('ACTIVATES', false); // 使用邀请码后是否立即激活，默认否

// Cloudflare Turnstile
define('CF_TURNSTILE_SITEKEY', '1x00000000000000000000AA'); // Cloudflare Turnstile 的 Site Key
define('CF_TURNSTILE_SECRET', '1x0000000000000000000000000000000AA'); // Cloudflare Turnstile 的 Secret Key

// OAuth
define('OAUTH_CLIENT_ID', 'YOUR_CLIENT_ID'); // OAuth 的应用 ID
define('OAUTH_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'); // OAuth 的应用私钥
?>