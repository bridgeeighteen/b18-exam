<?php
// 使用方法：在本文件中修改配置，然后重命名为 config.php。
// 修改时确保只对 define 函数的第二个字符串参数作出修改，否则将导致系统无法获取配置。

// 基础配置
define('SITE', 'exam.bridge18.qzz.io'); // 部署系统的域名，部分场景下会使用
define('ADMIN_EMAIL_NAME', 'admin'); // 管理邮箱的用户名，也就是前半部分
define('ADMIN_EMAIL_DOMAIN', 'example.com'); // 管理邮箱的域名，也就是后半部分
define('ABOUT_URL', 'https://bridge18.qzz.io/d/1'); // 点击导航栏中的“关于”时会跳转到的链接。建议在论坛中开个专门帖子介绍论坛或者详细讲解入站测试的相关信息，然后在这里设置链接。
define('TOS_URL', 'https://bridge18.qzz.io/p/1-terms-of-service'); // 使用条款链接。可以使用 FoF Pages 插件创建单独页面存放内容，然后在这里设置链接。
// 首页下部左侧的卡片是告示区，可以在这里放近期重要的动态。
define('ANNOUCEMENT_TITLE', '不畏困境，终得光复'); // 告示区标题
define('ANNOUCEMENT_CONTENT', '十八桥社区在 2025 年下旬遭受来自 FreeFlarum 无预警删除社区论坛的重创，此后陷入停摆。在社区创始人和云湖网友的共同努力下，我们得以重新站起，准备着为捍卫中文互联网的开放与包容继续奋斗。'); // 告示区内容，建议 15 - 30 字。
define('ANNOUCEMENT_LINK', 'https://dvd.chat/@bridgeeighteen'); // 告示区链接

// MySQL / MariaDB 数据库配置
define('DB_HOST', 'localhost:3306'); // 数据库主机，如果运行在容器环境（如直接从 1Panel 应用商店安装的 PHP）应改为容器名
define('DB_NAME', 'YOUR_DATABASE_NAME'); // 数据库名
define('DB_USER', 'USERNAME'); // 数据库用户名
define('DB_PASS', 'PASSWORD'); // 数据库密码
define('DB_TIMEZONE_LOCK', false); // 数据库时区锁情况确认，参见 README
define('PHP_TIMEZONE', 'Asia/Shanghai'); // PHP 的时区（参见 https://www.php.net/manual/timezones.php）。为防止冲突，这里的时区应与你在导入数据库模板前在模板开头修改的时区一致。

// API 配置
define('API_SITE', 'bridge18.qzz.io'); // Flarum 站点域名
define('API_X_CSRF_TOKEN', 'YOUR_API_X_CSRF_TOKEN'); // Flarum API 的令牌，直接从个人设置 -> 安全页面创建并获取

// 社区论坛测试配置
define('FORUM_CLOSED', false); // 是否关闭社区论坛测试通道
define('FORUM_CLOSED_REASON', ''); // 关闭社区论坛测试通道的原因。请记得在末尾加句号或感叹号。
define('CODE_TYPE', 'B18R'); // 邀请码类型，出现在其前缀
define('GROUP_ID', 3); // 邀请码创建用户所属用户组，默认注册用户（3）
define('MAX_USES', 1); // 邀请码最大使用次数，默认为 1
define('ACTIVATES', false); // 使用邀请码后是否立即激活，默认否
define('EXAM_REMAIN_TIME', '20'); // 测试时长，以分钟为单位
define('SCORE_THRESHOLD', '60'); // 通过分数阈值，默认为 60 分
define('SCORE_CORRECT_QUESTION', '4'); // 答对每道题目所给的分数，默认为 4 分（共 25 道题）
define('SCORE_PARTIAL_MULTIPLE_QUESTION', '1'); // 多选题答对但不全所给的分数，默认为 1 分

// Matrix（千万桥）测试配置
define('MATRIX_ENABLED', true); // 是否启用注册 Matrix 的测试通道，false 时信息登记页面将不显示该选项
define('MATRIX_CLOSED', false); // 是否关闭 Matrix 测试通道
define('MATRIX_CLOSED_REASON', ''); // 关闭 Matrix 测试通道的原因。请记得在末尾加句号或感叹号。
define('MATRIX_INSTANCE_NAME', '千万桥'); // 实例名称，页面上的相关文案会使用该名称
define('MATRIX_API_SITE', 'mas.example.com'); // Matrix Authentication Service 的域名（若启用 adminapi 资源），管理 API 通过该地址访问
define('MATRIX_API_TOKEN', 'YOUR_MATRIX_PERSONAL_ACCESS_TOKEN'); // 具有 urn:mas:admin 作用域的个人访问令牌，用于操作 MAS 管理 API
define('MATRIX_REGISTER_URL', 'https://matrix.example.com/register'); // 实例注册页面链接，测试通过后引导用户前往注册
define('MATRIX_TOS_URL', 'https://example.com/matrix-tos'); // 实例使用条款链接
define('MATRIX_QUESTION_COUNT', '15'); // 礼仪测试题目数量，从基本礼仪题中随机抽取
define('MATRIX_SCORE_THRESHOLD', '90'); // 通过分数阈值，默认为 90 分
define('MATRIX_SCORE_CORRECT_QUESTION', '10'); // 答对每道题目所给的分数，默认为 10 分（共 15 道题，满分为 150 分）
define('MATRIX_SCORE_PARTIAL_MULTIPLE_QUESTION', '5'); // 多选题答对但不全所给的分数，默认为 5 分
define('MATRIX_EXAM_REMAIN_TIME', '20'); // 测试时长，以分钟为单位
define('MATRIX_TOKEN_USAGE_LIMIT', 1); // 注册 Token 最大使用次数，默认为 1
define('MATRIX_TOKEN_EXPIRY_DAYS', 0); // 注册 Token 有效期，以天为单位，0 表示不设置有效期

// Cloudflare Turnstile
define('CF_TURNSTILE_SITEKEY', '1x00000000000000000000AA'); // Cloudflare Turnstile 的 Site Key
define('CF_TURNSTILE_SECRET', '1x0000000000000000000000000000000AA'); // Cloudflare Turnstile 的 Secret Key

// OAuth
define('OAUTH_CLIENT_ID', 'YOUR_CLIENT_ID'); // OAuth 的应用 ID（管理面板登录用）
define('OAUTH_CLIENT_SECRET', 'YOUR_CLIENT_SECRET'); // OAuth 的应用私钥（管理面板登录用）

// 管理面板配置
define('ADMIN_GROUP_ID', 1); // Flarum 管理员用户组 ID，默认 1（管理员）
define('ADMIN_SESSION_LIFETIME', 120); // 管理员登录会话有效期，以分钟为单位
define('ADMIN_MAS_OAUTH_ENABLED', false); // 是否启用“使用千万桥账号登录管理面板”。复用下方 MAS OAuth 免考配置（动态注册的客户端已包含回调地址 https://你的部署网站/admin/oauth-matrix.php）

// API 限流配置
define('API_RATE_LIMIT_PER_MINUTE', 120); // 每个 API 密钥每分钟最大请求数
define('API_RATE_LIMIT_IP_PER_MINUTE', 60); // 每个 IP 每分钟最大请求数

// 论坛 OAuth 免考配置（基于 OAuth Center，用户登录论坛账号后免考礼仪测试）
define('FORUM_OAUTH_ENABLED', false); // 是否启用“使用论坛账号登录（免考礼仪测试）”
define('FORUM_OAUTH_CLIENT_ID', 'YOUR_FORUM_OAUTH_CLIENT_ID'); // 在 OAuth Center 中为本站用户免考流程单独创建的应用 ID，回调地址填 https://你的部署网站/oauth-forum.php
define('FORUM_OAUTH_CLIENT_SECRET', 'YOUR_FORUM_OAUTH_CLIENT_SECRET'); // 上述应用的应用私钥

// Matrix（MAS）OAuth 免考配置（用户在 Matrix Authentication Service 上登录 Matrix 账号后免考礼仪测试）
//   仅需 MAS_OAUTH_ENABLED 与 MATRIX_API_SITE（OAuth 端点与管理 API 共用该地址）即可：首次使用时会通过
// OAuth 2.0 动态客户端注册协议（RFC 7591，POST <MAS>/oauth2/registration）自动注册客户端，注册返回的
// 凭据保存到 mas_oauth_clients 数据表。回调地址自动注册为 https://你的部署网站/oauth-matrix.php 与
// https://你的部署网站/admin/oauth-matrix.php，授权类型为 authorization_code，作用域为 openid。
//   注意：MAS 的 userinfo 端点只返回 sub（用户内部 ULID）与 username（localpart），不返回 email；
// 前台免考流程通过管理 API（GET /api/admin/v1/user-emails，需 MATRIX_API_TOKEN 可用）核对登记邮箱
// 是否为该账号的绑定邮箱，管理面板登录只需用户名（无需邮箱）。请确认 MAS 的 adminapi 资源已启用。
// 如需沿用 MAS 配置文件中 oauth.clients 的静态注册方式，可把静态客户端 ID 与私钥填入下面两项，
// 此时将跳过动态注册（两项均需同时填写）。
define('MAS_OAUTH_ENABLED', false); // 是否启用“使用 Matrix 账号登录（免考礼仪测试）”
define('MAS_OAUTH_CLIENT_ID', 'YOUR_MAS_OAUTH_CLIENT_ID'); // （可选）MAS 中静态注册的 OAuth 客户端 ID，留空使用动态注册
define('MAS_OAUTH_CLIENT_SECRET', 'YOUR_MAS_OAUTH_CLIENT_SECRET'); // （可选）上述静态注册客户端的私钥，留空使用动态注册
?>
