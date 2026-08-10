<div id="top"></div>

<div align="center">
  <a href="https://codeberg.org/bridgeeighteen/b18-exam">
    <img src="views/assets/logo_text.svg" alt="十八桥社区" height="60">
  </a>

<h3 align="center">入站测试系统</h3>

  <p align="center">
    十八桥社区使用的入站测试系统，基于 Bootstrap 和 PHP。
    <br />
    <br />
    <a href="https://codeberg.org/bridgeeighteen/b18-exam/issues">反馈 Bug</a>
    ·
    <a href="https://codeberg.org/bridgeeighteen/b18-exam/issues">请求新功能</a>
    <br />
    <br />
    <img src="https://scrutinizer-ci.com/g/bridgeeighteen/b18-exam/badges/quality-score.png?b=main" alt="Scrutinizer 分数">
    <img src="https://img.shields.io/github/contributors/bridgeeighteen/b18-exam.svg" alt="GitHub 贡献者总数">
    <img src="https://img.shields.io/gitea/pull-requests/all/bridgeeighteen/b18-exam?gitea_url=https%3A%2F%2Fcodeberg.org" alt="PR 总数">
    <img src="https://img.shields.io/github/stars/bridgeeighteen/b18-exam.svg" alt="GitHub Stars 总数">
    <img src="https://img.shields.io/gitea/stars/bridgeeighteen/b18-exam?gitea_url=https%3A%2F%2Fcodeberg.org" alt="Codeberg Stars 总数">
    <img src="https://img.shields.io/gitea/issues/all/bridgeeighteen/b18-exam?gitea_url=https%3A%2F%2Fcodeberg.org" alt="Issues 总数">
    <img src="https://img.shields.io/packagist/v/bridgeeighteen/exam" alt="Composer 版本">
    <img src="https://img.shields.io/packagist/l/bridgeeighteen/exam" alt="许可证">
  </p>
</div>

<!-- 目录 -->
<details>
  <summary>目录</summary>
  <ol>
    <li>
      <a href="#关于本项目">关于本项目</a>
      <ul>
        <li><a href="#构建工具">构建工具</a></li>
      </ul>
    </li>
    <li>
      <a href="#开始">开始</a>
      <ul>
        <li><a href="#依赖">依赖</a></li>
        <li><a href="#正常安装（生产环境推荐）">正常安装</a></li>
        <li><a href="#使用Git克隆安装">使用 Git 克隆安装</a></li>
      </ul>
    </li>
    <li><a href="#主要功能">主要功能</a></li>
    <li><a href="#贡献">贡献</a></li>
    <li><a href="#许可证">许可证</a></li>
    <li><a href="#联系我们">联系我们</a></li>
  </ol>
</details>

<!-- 关于本项目 -->
## 关于本项目

这是十八桥社区的入站测试系统，用于让用户完成入站测试并根据成绩获得邀请码 / 注册 Token 以在论坛 / Matrix 注册。

<p align="right">(<a href="#top">回到顶部</a>)</p>

### 构建工具

* [Composer](https://getcomposer.org)
* [Bootstrap](https://getbootstrap.com/)
* [jQuery](https://jquery.com)

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 开始 -->
## 开始

要获取本地副本并且配置运行，你可以按照下面的示例步骤操作。

### 依赖

* Composer
* MySQL
* PHP
* Nginx / Apache
* 已经部署好的 Flarum
  * [FoF Doorman 插件](https://github.com/FriendsOfFlarum/doorman)
  * [OAuth Center 插件](https://github.com/FoskyM/flarum-oauth-center)
* 已经部署好的 Matrix 实例（可选，用于注册 Matrix 的测试通道）
  * [Matrix Authentication Service](https://github.com/element-hq/matrix-authentication-service)
  * 启用 MAS 的 `adminapi` 资源，并准备一个具有 `urn:mas:admin` 作用域的个人访问令牌

### 正常安装（生产环境推荐）

1. 在 [Cloudflare 仪表板](https://dash.cloudflare.com/)中获取 Turnstile 的密钥（测试用途不需要），然后去 Flarum 的个人主页获取 API 密钥。同时，你需要在 Flarum 中利用 OAuth Center 插件的管理面板创建一个新的应用，先复制（或记下）插件自动生成的 ID 和私钥，然后依需要填写其余内容。回调地址填 `https://你的部署网站/admin/oauth.php`。如果还要启用“论坛账号登录免考礼仪测试”功能，需要再创建一个应用用于用户免考流程，回调地址填 `https://你的部署网站/oauth-forum.php`，并将其 ID 和私钥填入 `FORUM_OAUTH_CLIENT_ID` 与 `FORUM_OAUTH_CLIENT_SECRET` 配置项（详见下文「配置 OAuth 免考」）。

2. 通过 Composer 创建新项目。这里的 `my-new-project` 可以根据实际需要更换。

   ```shell
   composer create-project bridgeeighteen/exam my-new-project
   ```

3. 在 `config-example.php` 中根据注释提示完成配置。如果只是用于测试，须保留模板中给定的 Turnstile 密钥。

4. 使用 phpMyAdmin 等导入 `table.sql` 中定义的数据表及结构。导入前先在该文件的 `SET time_zone = "+08:00";` 一行中按照通用表示修改时区，然后到第 3 步设置的配置文件中找到 `PHP_TIMEZONE` 变量，按照其后的注释以 PHP 支持的格式同步修改。管理者在中国大陆的无需改动，在中国港澳台地区的需要修改 PHP 时区为本地时区。有些托管平台设置了时区锁，无论怎么改时区 `SELECT @@global.time_zone, @@session.time_zone;` 的查询结果均为 `SYSTEM`。遇到这种情况，请将 `DB_TIMEZONE_LOCK` 变量设置为 `true`。

5. 在 `questions` 表中手工录入试题，或通过管理面板（`/admin/`）在「题目管理」中录入或导入 Markdown 试题（详见下文「管理面板」）。

### 使用 Git 克隆安装

1. 在 [Cloudflare 仪表板](https://dash.cloudflare.com/)中获取 Turnstile 的密钥（测试用途不需要），然后去 Flarum 的个人主页获取 API 密钥。同时，你需要在 Flarum 中利用 OAuth Center 插件的管理面板创建一个新的应用，先复制（或记下）插件自动生成的 ID 和私钥，然后依需要填写其余内容。回调地址填 `https://你的部署网站/admin/oauth.php`。如果还要启用“论坛账号登录免考礼仪测试”功能，需要再创建一个应用用于用户免考流程，回调地址填 `https://你的部署网站/oauth-forum.php`，并将其 ID 和私钥填入 `FORUM_OAUTH_CLIENT_ID` 与 `FORUM_OAUTH_CLIENT_SECRET` 配置项（详见下文「配置 OAuth 免考」）。

2. 克隆本仓库。

   ```shell
   git clone https://codeberg.org/bridgeeighteen/b18-exam.git
   ```

3. 安装 Composer 依赖包。

   ```shell
   composer install
   ```

4. 在 `config-example.php` 中根据注释提示完成配置。如果只是用于测试，须保留模板中给定的 Turnstile 密钥。

5. 使用 phpMyAdmin 等导入 `table.sql` 中定义的数据表及结构。导入前先在该文件的 `SET time_zone = "+08:00";` 一行中按照通用表示修改时区，然后到第 3 步设置的配置文件中找到 `PHP_TIMEZONE` 变量，按照其后的注释以 PHP 支持的格式同步修改。管理者在中国大陆的无需改动，在中国港澳台地区的需要修改 PHP 时区为本地时区。有些托管平台设置了时区锁，无论怎么改时区 `SELECT @@global.time_zone, @@session.time_zone;` 的查询结果均为 `SYSTEM`。遇到这种情况，请将 `DB_TIMEZONE_LOCK` 变量设置为 `true`。

6. 在 `questions` 表中手工录入试题，或通过管理面板（`/admin/`）在「题目管理」中录入或导入 Markdown 试题（详见下文「管理面板」）。

### 从旧版本升级（v1.0.x → v1.1.0）

如果此前已经部署过本系统，请手动执行以下 SQL 以补齐 v1.1.0 新增的数据结构（`questions.author` 为 Markdown 导入所需的命题人字段）：

```sql
ALTER TABLE `questions`
  ADD COLUMN `author` varchar(100) NOT NULL DEFAULT '' COMMENT '命题人' AFTER `type`;

CREATE TABLE `api_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `key_hash` char(64) NOT NULL COMMENT '密钥的 SHA-256 哈希，明文只在创建时显示一次',
  `scopes` varchar(255) NOT NULL COMMENT '逗号分隔的作用域列表',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor` varchar(255) NOT NULL COMMENT '操作者标识（管理员身份或 API 密钥名称）',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `target` varchar(255) DEFAULT NULL COMMENT '操作目标',
  `detail` text COMMENT '补充信息（JSON）',
  `ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `actor` (`actor`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `api_rate_limits` (
  `bucket` varchar(128) NOT NULL COMMENT '限流桶，如 ip:1.2.3.4 或 key:1',
  `window_start` int NOT NULL COMMENT '窗口起始时间（Unix 秒）',
  `count` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`bucket`),
  KEY `window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `exam_papers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel` enum('forum','matrix') NOT NULL,
  `candidate_id` int NOT NULL,
  `question_ids` text NOT NULL COMMENT '试卷题目 ID 列表（JSON 数组，按出卷顺序）',
  `etiquette_exempt` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否礼仪题免考',
  `etiquette_exempt_ids` text COMMENT '免考礼仪题 ID 列表（JSON 数组）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `channel_candidate` (`channel`,`candidate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `blacklist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL COMMENT '被拉黑的邮箱（统一存为小写）',
  `ips` text COMMENT '与该邮箱关联的 IP 地址列表（JSON 数组，检测到被拉黑邮箱时自动补充）',
  `detection_count` int NOT NULL DEFAULT '0' COMMENT '累计检测次数（该邮箱或其任一 IP 被检测到的次数）',
  `reason` varchar(255) DEFAULT NULL COMMENT '拉黑原因（可选）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

其中 `exam_papers` 为持久化试卷表（答题页与系统 API 共用，用于保证交卷按出卷题目计分、免考状态由服务端记录），`blacklist` 为测试黑名单表（按邮箱拉黑并自动记录访问 IP、累计检测次数）。

### 配置 Matrix 测试通道（可选）

如果你需要让用户通过本系统获得“千万桥”等 Matrix 实例的注册 Token，请按照以下步骤操作。

1. 在 Matrix Authentication Service 的配置文件中启用管理 API。在 `http.listeners` 中添加 `adminapi` 资源（参见 [Use the Admin API](https://element-hq.github.io/matrix-authentication-service/topics/admin-api.html)）：

   ```yaml
   http:
     listeners:
       - name: web
         resources:
           # 其他公共资源
           - name: discovery
           # …
           - name: adminapi
         binds:
           - address: "[::]:8080"
   ```

2. 准备一个具有 `urn:mas:admin` 作用域的个人访问令牌，用于操作 MAS 管理 API。你可以使用 MAS 提供的[脚本](https://github.com/element-hq/matrix-authentication-service/blob/main/misc/device-code-grant.sh)以设备授权流程交互式获取，或通过管理员 API 的 `POST /api/admin/v1/personal-sessions` 接口签发。

3. 在 `config.php` 中填写 `MATRIX_ENABLED`、`MATRIX_INSTANCE_NAME`、`MATRIX_API_SITE`、`MATRIX_API_TOKEN`、`MATRIX_REGISTER_URL`、`MATRIX_TOS_URL` 等配置项，并按需调整礼仪测试的题目数量、通过分数阈值与计分配置。

4. 重新导入 `table.sql` 以创建 `matrix_users` 与 `matrix_results` 两张独立的数据表（礼仪测试的结果不会与论坛测试的结果混用）。

注意：`MATRIX_API_TOKEN` 为敏感信息，请妥善保管，不要提交到源代码仓库。

### 配置 OAuth 免考（可选）

本系统支持两条 OAuth 免考通道，让已拥有对应平台账号的用户免考基本礼仪题：

- **Matrix 账号免考论坛测试**：用户在 Matrix Authentication Service（MAS）上登录自己的 Matrix 账号后，注册论坛时的入站测试将不包含基本礼仪题，该部分直接获得满分（`users` 表中的 `matrix_oauth_mxid` 注解会记录其 Matrix 用户 ID）。
- **论坛账号免考 Matrix 测试**：用户登录自己的论坛账号后，注册 Matrix 实例时免考礼仪测试并直接获得满分，系统随即发放注册 Token（`matrix_users` 表中的 `forum_oauth_user_id` 注解会记录其论坛用户 ID）。

两条通道均使用 OAuth 2.0 授权码流程（含 `state` 防 CSRF 校验；MAS 通道额外使用 PKCE），且登记信息中的电子邮件地址必须与 OAuth 验证到的账号所绑定邮箱完全一致，否则免考不生效，按正常测试流程进行。论坛通道的绑定邮箱由 OAuth 用户信息接口返回；MAS 通道的 userinfo 端点不返回邮箱，绑定邮箱通过 MAS 管理 API（`GET /api/admin/v1/user-emails`）核验，因此启用该通道需同时启用 MAS 的 `adminapi` 资源并配置 `MATRIX_API_TOKEN`。

#### 配置论坛账号免考（Flarum 提供方）

1. 在 Flarum 的 OAuth Center 插件管理面板中创建一个新的应用（不要与管理员登录应用混用），回调地址填 `https://你的部署网站/oauth-forum.php`，作用域选择默认的 `user.read`。
2. 将自动生成的 ID 和私钥分别填入 `config.php` 中的 `FORUM_OAUTH_CLIENT_ID` 与 `FORUM_OAUTH_CLIENT_SECRET`，并将 `FORUM_OAUTH_ENABLED` 设置为 `true`。

> 说明：OAuth Center 插件会用自己的控制器覆盖 Flarum 的 `GET /api/user` 端点，返回平铺的 JSON（`{"id":7,"username":"...","email":"...",...}`，无 `data`/`attributes` 包裹）。本系统已同时兼容该平铺形态与标准 JSON:API 信封形态，无需额外配置。

#### 配置 Matrix 账号免考（MAS 提供方）

1. 确保 MAS 的 OAuth 资源已启用（无需再在 `oauth.clients` 中静态注册客户端——本系统会通过 [OAuth 2.0 动态客户端注册协议（RFC 7591）](https://www.rfc-editor.org/rfc/rfc7591.txt)向 MAS 的 `POST /oauth2/registration` 端点自动注册）。动态注册受 MAS 的 OPA 策略（`policy.data.client_registration`）约束，默认要求 `client_uri` 为 HTTPS 且 `redirect_uris` 与其同主机，生产环境一般无需调整：

   ```yaml
   policy:
     data:
       client_registration:
         # 本地测试（如 http://localhost 或 127.0.0.1 回调）时需要放宽以下选项
         allow_host_mismatch: false
         allow_insecure_uris: false
         allow_missing_client_uri: false
   ```

2. 在 `config.php` 中将 `MAS_OAUTH_ENABLED` 设置为 `true`（OAuth 端点与管理 API 共用 `MATRIX_API_SITE` 地址）。首次有用户点击「使用 Matrix 账号登录」时，系统将自动注册客户端，注册时提交的回调地址为 `https://你的部署网站/oauth-matrix.php` 与 `https://你的部署网站/admin/oauth-matrix.php`，授权类型为 `authorization_code`，作用域为 `openid`，凭据（客户端 ID 与 MAS 生成的私钥）保存到 `mas_oauth_clients` 数据表。需要说明的是，MAS 的 userinfo 端点只返回 `sub`（用户内部 ULID）与 `username`（localpart），并不返回邮箱；前台免考流程的邮箱核对由管理 API（`GET /api/admin/v1/user-emails?filter[user]=<sub>`）完成，管理面板登录只需用户名（localpart），两者都需要 `adminapi` 资源与 `MATRIX_API_TOKEN` 可用。如果希望沿用静态注册方式，可把 `oauth.clients` 中配置的客户端 ID 与私钥填入 `MAS_OAUTH_CLIENT_ID` 与 `MAS_OAUTH_CLIENT_SECRET`（静态客户端需使用 `client_secret_post` 认证方式，且回调地址需覆盖上述两个路径），此时将跳过动态注册。

3. 重新导入 `table.sql` 以创建 `users` 与 `matrix_users` 表上的免考注解字段（`matrix_oauth_mxid`、`matrix_oauth_verified_at`、`forum_oauth_user_id`、`forum_oauth_verified_at`）及 `mas_oauth_clients` 数据表（动态注册的客户端凭据存放于此）。

如需重置动态注册的客户端，删除 `mas_oauth_clients` 数据表中的记录即可，下一次点击「使用 Matrix 账号登录」时会重新注册。

如果 `FORUM_OAUTH_ENABLED` 或 `MAS_OAUTH_ENABLED` 为 `false`（或未填写有效的论坛 OAuth 客户端凭据），对应的免考入口不会在信息登记页面显示。

### 管理面板

管理面板位于 `/admin/`，包含数据统计、测试信息、题目管理（手动录入 / Markdown 导入 / 导出）、API 密钥管理、黑名单管理与审计日志。

#### 访问权限

管理面板只能通过 OAuth 登录访问：

- **使用社区论坛登录**：需同时满足以下两个条件（缺一不可）：
  1. 论坛账号属于 `ADMIN_GROUP_ID`（默认 `1`，即论坛管理员组）对应的用户组；
  2. 在登录后的验证页面填写的千万桥（MAS）用户名具备 `admin` 属性（通过 MAS 管理 API 校验）。
- **使用千万桥登录**：仅需满足千万桥账号具备 `admin` 属性（需将 `ADMIN_MAS_OAUTH_ENABLED` 设为 `true`；动态注册的 MAS 客户端已包含回调地址 `https://你的部署网站/admin/oauth-matrix.php`，若沿用静态注册则需手动为该客户端追加该回调地址）。

任何校验失败都会拒绝访问（fail-closed）。登录会话默认有效期为 120 分钟（`ADMIN_SESSION_LIFETIME`）。

#### Markdown 导入格式

在「题目管理 → Markdown 导入」中先选择目标分类，然后粘贴如下格式的表格（表头顺序可任意，多余列会被忽略）：

```markdown
| 题干 | A | B | C | D | 答案 | 类型 | 命题人 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 题目内容 | 选项 A | 选项 B | 选项 C | 选项 D | A | single | 张三 |
| 多选题示例 | 甲 | 乙 | 丙 | 丁 | AB | multiple | |
```

- 答案：单选填单个字母（如 `A`），多选填字母组合（如 `AB` 或 `A,B`）；
- 类型：`single`（单选）或 `multiple`（多选）；
- 命题人可留空；分类列可选（若表格含「分类」列则优先使用表格中的值）。

#### 系统 API

系统提供统一的 RESTful JSON API（`/api/v1/...`），覆盖管理端的数据统计、题目管理、测试记录、用户、API 密钥、黑名单与审计日志，以及完整的入站测试流程（候选人登记、试卷获取、交卷计分与邀请码 / 注册 Token 发放）。API 供外部工具（如论坛机器人、监控服务）与管理面板共用。

> **Nginx 部署注意**：Nginx 不读取 `.htaccess`。请务必在站点配置的 `server` 块中添加 `/api/v1/` 的伪静态规则，否则管理面板的所有 API 功能（含 Markdown 导入/导出）会收到 Nginx 的 404 页面并报 `JSON.parse: unexpected character at line 1 column 1`：
>
> ```nginx
> location ^~ /api/v1/ {
>     rewrite ^/api/v1/(.*)$ /api/index.php?path=/v1/$1 last;
> }
> ```
>
> 管理面板前端同时使用了 PATH_INFO 形式（`api/index.php/v1/...`）作为兜底，缺少该规则时面板功能依然可用。

**文档**：完整的 API 文档已独立成册 —— 人读指南见 [`docs/api.md`](docs/api.md)（认证、作用域、端点参考、示例与集成场景），机器可读规范见 [`docs/api/openapi.yaml`](docs/api/openapi.yaml)（OpenAPI 3.0，可用 Swagger UI / Redoc 渲染）。

**快速上手**：

- 认证：`Authorization: Bearer <密钥>`（管理面板创建，明文只显示一次），或已登录的管理员会话（写操作需携带 `X-CSRF-Token` 请求头）；
- API 仅接受 HTTPS（本机回环地址除外），按 IP 与密钥限流，写操作写入审计日志；
- 响应统一为 `{"ok":true,"data":...}` / `{"ok":false,"data":{"error":{"code":...,"message":...}}}`；
- 列表接口支持 `page` / `per_page` 分页并附带 RFC 5988 `Link` 响应头。

**支持的作用域**：

| 作用域 | 说明 |
| --- | --- |
| `stats:read` | 数据统计（只读） |
| `questions:read` / `questions:write` | 题目查询 / 增删改导入导出 |
| `results:read` / `results:write` | 测试记录查询 / 删除 |
| `users:read` | 用户列表与详情 |
| `keys:admin` | API 密钥与审计日志管理 |
| `system:read` | 系统信息（只读） |
| `exam:read` / `exam:write` | 考试流程：试卷与候选人查询 / 登记、交卷与删除 |
| `blacklist:read` / `blacklist:write` | 黑名单查询 / 增删改 |

**端点一览**（完整参数与示例见 [`docs/api.md`](docs/api.md)）：

| 方法与路径 | 说明 |
| --- | --- |
| `GET /api/v1/health` · `GET /api/v1/meta` | 健康检查 / 公共元数据（免认证） |
| `GET /api/v1/stats` | 统计摘要 |
| `GET /api/v1/questions` · `POST /api/v1/questions` | 题目列表（筛选 `category` / `type` / `q`）/ 新建 |
| `GET /api/v1/questions/{id}` · `PUT /api/v1/questions/{id}` · `DELETE /api/v1/questions/{id}` | 题目详情 / 更新 / 删除 |
| `POST /api/v1/questions/import` · `GET /api/v1/questions/export` | Markdown 表格导入（`dry_run` 预览）/ 导出 |
| `GET /api/v1/results` · `GET /api/v1/results/{id}` · `DELETE /api/v1/results/{id}` | 测试记录列表 / 详情 / 删除（筛选 `channel` / `status` / `date_from` / `date_to`） |
| `GET /api/v1/users` · `GET /api/v1/users/{id}` · `DELETE /api/v1/users/{id}` | 用户列表（含登记日期筛选）/ 详情 / 删除（记录级联删除） |
| `GET /api/v1/keys` · `POST /api/v1/keys` · `PATCH /api/v1/keys/{id}` · `DELETE /api/v1/keys/{id}` | API 密钥管理（`PATCH` 请求体 `{"enabled":true\|false}` 启停用） |
| `GET /api/v1/audit` | 审计日志（筛选 `q` / `action` / `date_from` / `date_to`） |
| `GET /api/v1/system` | 系统信息（只读） |
| `GET /api/v1/candidates` · `GET /api/v1/candidates/{id}` · `DELETE /api/v1/candidates/{id}` | 候选人列表（状态 `registered` / `paper_generated` / `submitted`）/ 详情（含试卷与凭据）/ 删除 |
| `POST /api/v1/candidates` | 登记候选人并生成试卷（请求体 `channel` 为 `forum` 或 `matrix`） |
| `GET /api/v1/candidates/{id}/paper?channel=forum` | 获取已生成的试卷（题目不含答案） |
| `POST /api/v1/candidates/{id}/submissions` | 交卷计分，返回分数与邀请码 / 注册 Token |
| `GET /api/v1/matrix/usernames/{name}/availability` | 核验 Matrix 用户名是否可用（免考流程使用） |
| `GET /api/v1/blacklist` · `POST /api/v1/blacklist` | 黑名单列表（筛选 `q`）/ 新建（请求体 `email` 必填，`ips` 支持多个 IP） |
| `PUT /api/v1/blacklist/{id}` · `DELETE /api/v1/blacklist/{id}` | 更新（IP 列表与原因）/ 删除黑名单条目 |

常用示例：

```shell
BASE="https://你的部署网站/api/v1"
AUTH="Authorization: Bearer <密钥>"

# 健康检查（免认证）
curl "$BASE/health"

# 统计摘要
curl -H "$AUTH" "$BASE/stats"

# 题目列表（按分类筛选，分页）
curl -H "$AUTH" "$BASE/questions?category=IT&page=1"

# 登记论坛候选人并生成试卷（返回 201，含 candidate 与 paper）
curl -X POST -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"channel":"forum","username":"张三","email":"zhangsan@example.com","categories":["IT","ACGN"]}' \
  "$BASE/candidates"

# 获取试卷（题目不含答案）
curl -H "$AUTH" "$BASE/candidates/1/paper?channel=forum"

# 交卷计分（answers 键为题目 ID，值为选项字母数组）
curl -X POST -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"channel":"forum","answers":{"1":["A"],"2":["A","B"]}}' \
  "$BASE/candidates/1/submissions"

# 查询候选人状态与凭据
curl -H "$AUTH" "$BASE/candidates/1?channel=forum"

# Matrix 用户名可用性核验
curl -H "$AUTH" "$BASE/matrix/usernames/zhangsan/availability"
```

考试流程中的候选人登记接口同样受作用域与限流保护；面向用户的网页流程（Turnstile 验证码与 OAuth 免考）保持不变。

<p align="right">(<a href="#top">回到顶部</a>)</p>


<!-- 主要功能 -->
## 主要功能

- [x] 支持单选/多选试题
- [x] 支持多基类（分区）试题设置
- [x] 时间作弊检测
- [x] 自定义测试时长、过关分数阈值、每题全对分数和多选题漏选分数
- [x] 完美支持 Flarum 内置 API 接口和 FoF Doorman 插件自带 API 接口
- [x] 支持 Matrix 实例注册测试通道（基于 Matrix Authentication Service 管理 API）
- [x] 支持 Matrix 账号 OAuth 登录免考论坛测试中的基本礼仪题（MAS 作为 OAuth 提供方）
- [x] 支持论坛账号 OAuth 登录免考 Matrix 礼仪测试（FoskyM OAuth Center 作为 OAuth 提供方）
- [x] 管理面板：数据统计、测试信息、题目管理（手动录入 / 编辑 / 删除 / Markdown 表格导入导出）、黑名单管理
- [x] 管理面板双平台管理员校验（论坛 OAuth 路径需同时为论坛管理员与千万桥管理员）
- [x] 系统 REST API（`/api/v1/...`，覆盖管理端与完整考试流程、API 密钥、作用域、限流、审计日志）
- [x] 测试黑名单：按邮箱拉黑（支持关联多个 IP），命中时拒绝登记与交卷并自动记录访问 IP、累计检测次数

你也可以到 [Open Issues](https://codeberg.org/bridgeeighteen/b18-exam/issues) 页查看所有请求的功能（以及已知的问题）。

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 贡献 -->
## 贡献

贡献让开源社区成为了一个非常适合学习、互相激励和创新的地方。你所做出的任何贡献都是**受人尊敬**的。

如果你有好的建议，请复刻（Fork）本仓库并且创建一个拉取请求（Pull Request）。你也可以简单地创建一个议题（Issue），并且添加标签「enhancement」。不要忘记给项目点一个 Star！再次感谢！

1. 复刻（Fork）本项目
2. 创建你的 Feature 分支 (`git checkout -b feature/AmazingFeature`)
3. 提交你的变更 (`git commit -m 'Add some amazing feature'`)
4. 推送到该分支 (`git push origin feature/AmazingFeature`)
5. 创建一个拉取请求（Pull Request）

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 许可证 -->
## 许可证

根据 LGPL-3.0-or-later 许可证分发。LGPL-3.0 的完整副本请见 [LICENSE](LICENSE)，GPL-3.0 的完整副本请见 [LICENSE.GPL-3.0](LICENSE.GPL-3.0)。

<p align="right">(<a href="#top">回到顶部</a>)</p>

<!-- 联系我们 -->
## 联系我们

Matrix: [#community:millions.bridge18.qzz.io](https://matrix.to/#/#community:millions.bridge18.qzz.io)

<!-- IRC 频道：irc://irc.libera.chat/#bridgeeighteen -->

<p align="right">(<a href="#top">回到顶部</a>)</p>
