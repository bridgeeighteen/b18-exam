# 入站测试系统 API 指南

入站测试系统提供统一的 RESTful JSON API（`/api/v1/...`），覆盖数据统计、题目管理、测试记录、用户与候选人、API 密钥、黑名单与审计日志，以及完整的入站测试流程（候选人登记 → 试卷获取 → 交卷计分 → 邀请码 / 注册 Token 发放）。

- 机器可读规范（OpenAPI 3.0）：[docs/api/openapi.yaml](https://codeberg.org/bridgeeighteen/b18-exam/src/branch/main/docs/api/openapi.yaml)，可用 Swagger UI / Redoc 等工具直接渲染。
- API 供外部工具（如论坛机器人、监控服务）与管理面板共用。

---

## 1. 基础地址

```
https://你的部署网站/api/v1
```

本地开发（PHP 内置服务器）：`php -S 127.0.0.1:8080 router.php`，地址为 `http://127.0.0.1:8080/api/v1`。

路径解析支持两种方式：

- 重写规则（Apache `.htaccess` 已内置）：`/api/v1/...` → `api/index.php?path=/v1/...`
- PATH_INFO（Nginx / PHP 内置服务器）：`api/index.php/v1/...`

Nginx 部署请在站点配置的 `server` 块中添加以下规则（对应 Apache `.htaccess` 的重写）：

```nginx
location ^~ /api/v1/ {
    rewrite ^/api/v1/(.*)$ /api/index.php?path=/v1/$1 last;
}
```

若未添加上述规则，`/api/v1/...` 将返回 Nginx 的 404 页面，所有标准 API 接口连接都不可用。管理面板前端已使用 PATH_INFO 形式（`api/index.php/v1/...`）作为兜底，因此即使缺少该规则，面板功能依然可用。

## 2. 认证与安全

所有请求（公共端点除外）必须携带以下任一凭据：

| 方式 | 说明 |
| --- | --- |
| `Authorization: Bearer <密钥>` | API 密钥，在「管理面板 → API 密钥」中创建，明文只显示一次，以 `b18k_` 开头。密钥仅以 SHA-256 哈希存库 |
| 管理员会话 | 管理面板的已登录会话（Cookie）；**写操作还需携带 `X-CSRF-Token` 请求头**（面板内自动携带） |

其他安全规则：

- API 仅接受 HTTPS（本机回环地址 `127.0.0.1` / `::1` 除外），违反返回 `403 https_required`；
- 限流：每个 API 密钥每分钟 `API_RATE_LIMIT_PER_MINUTE`（默认 120）次，每个 IP 每分钟 `API_RATE_LIMIT_IP_PER_MINUTE`（默认 60）次，超出返回 `429 rate_limited`；
- 写操作（`POST` / `PUT` / `PATCH` / `DELETE`）会写入审计日志（`api:v1:*`）；
- 试卷接口返回的题目**不含答案**，避免泄露。

### 2.1 作用域

API 密钥在创建时选择作用域。所有作用域：

| 作用域 | 说明 |
| --- | --- |
| `stats:read` | 数据统计（只读） |
| `questions:read` | 题目查询、导出 |
| `questions:write` | 题目增删改、Markdown 导入 |
| `results:read` | 测试记录查询 |
| `results:write` | 测试记录 / 用户删除 |
| `users:read` | 用户列表与详情 |
| `keys:admin` | API 密钥与审计日志管理 |
| `system:read` | 系统信息（只读） |
| `exam:read` | 考试流程只读：试卷获取、候选人列表与详情、用户名核验 |
| `exam:write` | 考试流程写：候选人登记、交卷、删除候选人 |
| `blacklist:read` | 黑名单（只读） |
| `blacklist:write` | 黑名单（读写） |

## 3. 请求与响应

### 3.1 信封格式

所有响应统一为：

```json
// 成功
{ "ok": true, "data": { ... } }

// 失败
{ "ok": false, "data": { "error": { "code": "not_found", "message": "题目不存在。" } } }
```

### 3.2 HTTP 方法

| 方法 | 语义 | 成功状态码 |
| --- | --- | --- |
| `GET` | 查询 | 200 |
| `POST` | 新建 | 201（附 `Location` 响应头） |
| `PUT` | 整体更新 | 200 |
| `PATCH` | 部分更新 | 200 |
| `DELETE` | 删除 | 204（无响应体） |
| `OPTIONS` | 探测允许的方法 | 204（附 `Allow` 响应头） |

### 3.3 分页

列表接口支持 `page`（默认 1）与 `per_page`（默认 20，最大 100）查询参数，响应包含：

```json
{ "items": [...], "total": 42, "page": 1, "per_page": 20, "pages": 3 }
```

分页列表响应同时附带 RFC 5988 `Link` 响应头（`first` / `prev` / `next` / `last`），客户端可按 `rel="next"` 自动翻页。

### 3.4 错误码

| HTTP 状态码 | code | 说明 |
| --- | --- | --- |
| 400 | — | — |
| 401 | `invalid_key` / `unauthenticated` | 密钥无效 / 会话过期 |
| 403 | `https_required` / `key_disabled` / `key_expired` / `forbidden` / `csrf_failed` / `blacklisted` | 安全或权限类错误 / 命中测试黑名单 |
| 404 | `not_found` | 资源、路径或接口版本不存在 |
| 405 | `method_not_allowed` | 该路径不支持请求方法（附 `Allow` 头） |
| 409 | `duplicate` / `username_in_use` / `time_violation` / `no_paper` | 冲突（重复题目、用户名占用、超时交卷、未出卷） |
| 422 | `validation_failed` | 参数校验失败 |
| 429 | `rate_limited` | 请求过于频繁 |
| 500 | `internal_error` | 服务器内部错误 |
| 503 | `mas_unavailable` / health `degraded` | Matrix Authentication Service 不可用 / 数据库不可用 |

## 4. 端点参考

> 公共端点（免认证）：`GET /v1/health`、`GET /v1/meta`。

### 4.1 健康检查与元数据（公共）

#### `GET /health`

探测数据库连通性，供 UptimeRobot 等外部监控使用。

```shell
curl https://你的部署网站/api/v1/health
```

```json
{ "ok": true, "data": { "status": "ok", "version": "1.1.0", "db": "ok", "time": "2026-08-09 12:00:00" } }
```

数据库不可用时返回 `503` 且 `status` 为 `degraded`。

#### `GET /meta`

返回版本、通道配置、题目分类与题型等非敏感信息，供外部工具接入前发现系统能力。

```json
{
  "ok": true,
  "data": {
    "version": "1.1.0",
    "channels": {
      "forum":  { "enabled": true,  "duration_minutes": 20, "score_threshold": 80, "score_correct": 5, "score_partial": 2 },
      "matrix": { "enabled": true,  "instance_name": "千万桥", "duration_minutes": 10, "score_threshold": 80, "score_correct": 5, "score_partial": 2 }
    },
    "question_categories": ["IT", "ACGN", "Virtual_Singer", "Broadcasting", "Etiquette"],
    "question_types": ["single", "multiple"]
  }
}
```

### 4.2 统计

#### `GET /stats`（`stats:read`）

论坛与 Matrix 通道的用户 / 成绩 / 通过率 / 免考人数、题目总数与分类分布、今日登记数、最近 8 条记录与考试配置。

### 4.3 题目（`questions:read` / `questions:write`）

| 方法与路径 | 说明 |
| --- | --- |
| `GET /questions` | 列表：`category` / `type` / `q`（匹配题干或命题人） |
| `POST /questions` | 新建 |
| `GET /questions/{id}` | 详情 |
| `PUT /questions/{id}` | 整体更新 |
| `DELETE /questions/{id}` | 删除 |
| `POST /questions/import` | Markdown 表格批量导入（`dry_run` 预览） |
| `GET /questions/export` | 导出为 Markdown 表格 |

题目对象（含答案字段）：

```json
{
  "id": 1,
  "category": "IT",
  "question_text": "题目",
  "option_a": "甲", "option_b": "乙", "option_c": "丙", "option_d": "丁",
  "answer": "A",
  "type": "single",
  "author": ""
}
```

新建 / 更新请求体：

```json
{
  "category": "IT",
  "question_text": "题目",
  "option_a": "甲", "option_b": "乙", "option_c": "丙", "option_d": "丁",
  "answer": "A",
  "type": "single",
  "author": ""
}
```

- 答案：单选填单个字母（`A`）；多选填字母组合（`ABC` 或 `A,B`）；
- 相同分类下不允许存在相同题干的题目（`409 duplicate`）。

导入（Markdown 表格）：

```shell
curl -X POST -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"category":"IT","markdown":"| 题干 | A | B | C | D | 答案 | 类型 | 命题人 |\n|---|---|---|---|---|---|---|---|\n| 题目 | 甲 | 乙 | 丙 | 丁 | A | single | |","skip_duplicates":true,"dry_run":false}' \
  "$BASE/questions/import"
```

```json
{ "ok": true, "data": { "total": 1, "imported": 1, "skipped": 0, "failed": 0, "errors": [] } }
```

### 4.4 测试记录（`results:read` / `results:write`）

| 方法与路径 | 说明 |
| --- | --- |
| `GET /results` | 列表：`channel` / `status`（`pass` / `fail`）/ `date_from` / `date_to`（按结束时间）/ `q` |
| `GET /results/{id}` | 详情：记录 + 用户完整信息 + 该用户全部历史 |
| `DELETE /results/{id}` | 删除记录 |

记录对象（含凭据）：

```json
{
  "id": 1, "score": 85, "end_time": "2026-08-09 10:00:00", "code": "B18R@ABCD1234",
  "user_id": 1, "username": "张三", "email": "zhangsan@example.com", "passed": true
}
```

### 4.5 用户与候选人

#### 用户（`users:read` / `results:write`）

| 方法与路径 | 说明 |
| --- | --- |
| `GET /users` | 列表：`channel` / `q` / `date_from` / `date_to`（按登记时间 `start_time`） |
| `GET /users/{id}` | 详情：用户完整信息 + 全部测试记录（含 `passed`） |
| `DELETE /users/{id}` | 删除用户（其测试记录级联删除） |

用户行：forum 通道含 `selected_categories` / `matrix_oauth_mxid`；matrix 通道含 `forum_oauth_user_id`。

#### 候选人 / 考试流程（`exam:read` / `exam:write`）

候选人列表与详情用于外部工具查看考试进程与治理（`registered` 已登记 / `paper_generated` 已出卷 / `submitted` 已交卷）：

| 方法与路径 | 说明 |
| --- | --- |
| `GET /candidates?channel=forum` | 列表：`channel`（必填）/ `status` / `q` / `date_from` / `date_to`（按登记时间） |
| `GET /candidates/{id}?channel=forum` | 详情：信息 + 状态 + 最近成绩（含凭据）+ 最近试卷 |
| `DELETE /candidates/{id}?channel=forum` | 删除候选人（试卷与记录级联删除） |

列表项：

```json
{
  "id": 1, "channel": "forum", "username": "张三", "email": "zhangsan@example.com",
  "start_time": "2026-08-09 09:30:00", "status": "submitted",
  "exam_paper_id": 5, "latest_score": 85, "passed": true, "ended_at": "2026-08-09 09:45:00",
  "selected_categories": "IT,ACGN", "matrix_oauth_mxid": null
}
```

详情在列表项基础上增加 `result`（`id` / `score` / `passed` / `end_time` / `code`）与 `paper`。

### 4.6 API 密钥（`keys:admin`）

| 方法与路径 | 说明 |
| --- | --- |
| `GET /keys` | 列表（元数据，不含明文） |
| `POST /keys` | 创建：`{"name":"...","scopes":["exam:read","exam:write"],"expiry_days":0}`，明文仅本次返回 |
| `PATCH /keys/{id}` | 启停用：`{"enabled":true|false}` |
| `DELETE /keys/{id}` | 删除 |

### 4.7 审计日志（`keys:admin`）

`GET /audit`：`q`（匹配操作者 / 动作 / 目标）、`action`（精确匹配，如 `api:v1:questions:create`）、`date_from` / `date_to`。

### 4.8 系统信息（`system:read`）

`GET /system`：版本、PHP 版本、通道开关与考试计分配置（只读，不含敏感信息）。公共的 `GET /meta` 覆盖其中非敏感部分。

### 4.9 黑名单（`blacklist:read` / `blacklist:write`）

| 方法与路径 | 说明 |
| --- | --- |
| `GET /blacklist` | 列表：`q`（匹配邮箱或原因），分页 |
| `POST /blacklist` | 新建：`{"email":"...","ips":["1.2.3.4","2001:db8::1"],"reason":"可选"}`，邮箱必填且不可修改 |
| `PUT /blacklist/{id}` | 整体更新：`{"ips":[...],"reason":"..."}`（邮箱作为条目主键不可修改） |
| `DELETE /blacklist/{id}` | 删除 |

条目对象：

```json
{
  "id": 1,
  "email": "blacklisted@example.com",
  "ips": ["1.2.3.4", "2001:db8::1"],
  "detection_count": 3,
  "reason": "邀请码滥用",
  "created_at": "2026-08-09 12:00:00",
  "updated_at": "2026-08-09 12:00:00"
}
```

- 黑名单在候选人登记与交卷时生效：命中条目（邮箱或访问者 IP）的请求返回 `403 blacklisted`；
- 检测到被拉黑邮箱时，系统自动将访问者 IP 补充进该条目（若尚不存在），并累计该条目的 `detection_count`；IP 命中时同样累计其所属条目的检测次数；
- 邮箱比较不区分大小写（存储为小写）。

## 5. 完整考试流程示例

```shell
BASE="https://你的部署网站/api/v1"
AUTH="Authorization: Bearer <密钥>"

# 1) 登记候选人并生成试卷（返回 201，含 candidate 与 paper）
curl -X POST -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"channel":"forum","username":"张三","email":"zhangsan@example.com","categories":["IT","ACGN"]}' \
  "$BASE/candidates"

# 2) 获取试卷（题目不含答案）
curl -H "$AUTH" "$BASE/candidates/1/paper?channel=forum"

# 3) 交卷计分（answers 键为题目 ID，值为选项字母数组）
curl -X POST -H "$AUTH" -H "Content-Type: application/json" \
  -d '{"channel":"forum","answers":{"1":["A"],"2":["A","B"]}}' \
  "$BASE/candidates/1/submissions"

# 4) 查询候选人状态与凭据
curl -H "$AUTH" "$BASE/candidates/1?channel=forum"
```

交卷响应：

```json
{
  "ok": true,
  "data": {
    "score": 85,
    "passed": true,
    "etiquette_exempt": false,
    "credential": { "type": "invitation_code", "issued": true, "value": "B18R@ABCD1234", "error": null }
  }
}
```

Matrix 通道流程相同，仅需将 `channel` 换为 `matrix`（用户名须为 MAS localpart，凭据为注册 Token）。免考标记（`matrix_oauth_mxid` / `forum_oauth_user_id`）由服务端记录，交卷时自动跳过礼仪题计分。

## 6. 常用集成场景

- **论坛机器人**：`POST /candidates` 登记 → `GET /candidates/{id}/paper` 发题 → 收答后 `POST /candidates/{id}/submissions` 计分并把凭据私发给用户。
- **监控服务**（免认证）：定时 `GET /health`；接入前先 `GET /meta` 了解通道开关与分数线。
- **数据归档**：`GET /results?channel=forum&date_from=...&date_to=...` 分页拉取全部记录；配合 `page` 与 `Link` 头翻页。
- **考试治理**：`GET /candidates?channel=matrix&status=paper_generated` 找出出卷未交卷的候选人，按需 `DELETE /candidates/{id}` 清理。
