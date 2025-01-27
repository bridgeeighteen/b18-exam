<?php
// 使用方法：在本文件中修改配置，然后重命名为 config.php。
// 修改时确保只对 define 函数最后的字符串作出修改，否则将导致系统无法获取配置。
define('SITE', 'example.com'); // 部署系统的域名，部分场景下会使用

// MySQL 数据库配置
define('DB_HOST', 'localhost:3306'); // 数据库主机
define('DB_NAME', 'YOUR_DATABASE_NAME'); // 数据库名
define('DB_USER', 'USERNAME'); // 数据库用户名
define('DB_PASS', 'PASSWORD'); // 数据库密码
define('DB_TIMEZONE_LOCK', false); // 数据库时区锁情况确认
define('PHP_TIMEZONE', 'Asia/Shanghai'); // PHP 的时区（参见 https://www.php.net/manual/timezones.php）。为防止冲突，这里的时区应与你在导入数据库模板前在模板开头修改的时区一致。

// API 配置
define('API_SITE', 'www.bridge18.us.kg'); // Flarum 站点域名
define('API_X_CSRF_TOKEN', 'YOUR_API_X_CSRF_TOKEN'); // Flarum API 的令牌，直接从个人设置 -> 安全页面创建并获取

// 测试配置
define('CLOSED', false); // 是否关闭测试通道
define('CLOSED_REASON', ''); // 关闭测试通道的原因。请记得在末尾加句号 / 感叹号。
define('CODE_TYPE', 'B18R'); // 邀请码类型，出现在其前缀
define('GROUP_ID', 3); // 邀请码创建用户所属用户组，默认注册用户（3）
define('MAX_USES', 1); // 邀请码最大使用次数，默认为 1
define('ACTIVATES', false); // 使用邀请码后是否立即激活，默认否
define('EXAM_REMAIN_TIME', '20'); // 测试时长，以分钟为单位
define('SCORE_THRESHOLD', '60'); // 通过分数阈值，默认为 60 分
define('SCORE_CORRECT_QUESTION', '4'); // 答对每道题目所给的分数，默认为 4 分（共 25 道题）
define('SCORE_PARTIAL_MULTIPLE_QUESTION', '1'); // 多选题答对但不全所给的分数，默认为 1 分

// Cloudflare Turnstile
define('CF_TURNSTILE_SITEKEY', '1x00000000000000000000AA'); // Cloudflare Turnstile 的 Site Key
define('CF_TURNSTILE_SECRET', '1x0000000000000000000000000000000AA'); // Cloudflare Turnstile 的 Secret Key

// OAuth
define('OAUTH_CLIENT_ID', 'YOUR_CLIENT_ID'); // OAuth 的应用 ID
define('OAUTH_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'); // OAuth 的应用私钥
?>
