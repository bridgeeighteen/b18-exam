<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

$admin = requireAdmin();
$pdo = getPDO();

$message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$error = isset($_GET['err']) ? trim((string)$_GET['err']) : '';
$newKeyPlain = null;

// 创建密钥（服务端处理，仅匹配创建表单，避免与列表中的停用 / 启用 / 删除表单相互触发）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['key_action']) && $_POST['key_action'] === 'create') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $scopes = array_values(array_filter((array)($_POST['scopes'] ?? []), 'is_string'));
        $expiryDays = max(0, (int)($_POST['expiry_days'] ?? 0));

        if ($name === '') {
            $error = '密钥名称不能为空。';
        } elseif ($scopes === []) {
            $error = '请至少选择一个作用域。';
        } else {
            $result = createApiKey($name, $scopes, $expiryDays);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $newKeyPlain = $result['plain'];
                auditLog($pdo, $admin['identity_label'], 'key:create', 'API 密钥：' . $name, ['scopes' => $scopes, 'expiry_days' => $expiryDays]);
            }
        }
    }
}

// 停用 / 启用 / 删除（服务端处理）
if (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['key_action'], $_POST['key_id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $keyId = max(1, (int)$_POST['key_id']);
        switch ($_POST['key_action']) {
            case 'revoke':
                updateApiKeyEnabled($keyId, false);
                auditLog($pdo, $admin['identity_label'], 'key:revoke', 'API 密钥 #' . $keyId);
                $message = '密钥已停用。';
                break;
            case 'enable':
                updateApiKeyEnabled($keyId, true);
                auditLog($pdo, $admin['identity_label'], 'key:enable', 'API 密钥 #' . $keyId);
                $message = '密钥已启用。';
                break;
            case 'delete':
                deleteApiKey($keyId);
                auditLog($pdo, $admin['identity_label'], 'key:delete', 'API 密钥 #' . $keyId);
                $message = '密钥已删除。';
                break;
        }
    }
}

$keys = listApiKeys();
$scopeLabels = [
    'stats:read' => '数据统计（只读）',
    'questions:read' => '题目（只读）',
    'questions:write' => '题目（读写）',
    'results:read' => '测试记录（只读）',
    'results:write' => '测试记录（读写）',
    'users:read' => '用户（只读）',
    'keys:admin' => 'API 密钥管理',
    'system:read' => '系统信息（只读）',
    'exam:read' => '考试流程（只读：试卷 / 用户名核验）',
    'exam:write' => '考试流程（读写：登记候选人 / 交卷）',
    'blacklist:read' => '黑名单（只读）',
    'blacklist:write' => '黑名单（读写）',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API 密钥 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <h1 class="page-title">API 密钥</h1>
    <p class="page-subtitle">管理供外部工具调用的系统 API 密钥。密钥仅以哈希形式存储，明文只在创建时显示一次。</p>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success mt-3" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="alert alert-danger mt-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($newKeyPlain !== null) : ?>
        <div class="alert alert-primary mt-3" role="alert">
            新密钥已创建，请立即复制保存（关闭本提示后无法再次查看）：<br>
            <code class="d-block mt-2 user-select-all"><?php echo htmlspecialchars($newKeyPlain); ?></code>
        </div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">创建新密钥</h5>
                    <form method="post" action="keys.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <input type="hidden" name="key_action" value="create">
                        <div class="form-section">
                            <label for="name" class="form-label">名称</label>
                            <input type="text" class="form-control" id="name" name="name" maxlength="50" placeholder="例如：论坛机器人" required>
                        </div>
                        <div class="form-section">
                            <label class="form-label">作用域</label>
                            <?php foreach ($scopeLabels as $scope => $label) : ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]" value="<?php echo htmlspecialchars($scope); ?>" id="scope_<?php echo htmlspecialchars($scope); ?>">
                                    <label class="form-check-label" for="scope_<?php echo htmlspecialchars($scope); ?>"><?php echo htmlspecialchars($label); ?> <code><?php echo htmlspecialchars($scope); ?></code></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-section">
                            <label for="expiry_days" class="form-label">有效期（天）</label>
                            <input type="number" class="form-control" id="expiry_days" name="expiry_days" min="0" max="3650" value="0">
                            <small class="form-text">0 表示不设置有效期。</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">创建</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">密钥列表</h5>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">名称</th>
                                    <th scope="col">作用域</th>
                                    <th scope="col">状态</th>
                                    <th scope="col">创建时间</th>
                                    <th scope="col">最后使用</th>
                                    <th scope="col">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($keys === []) : ?>
                                    <tr><td colspan="6" class="text-muted">暂无密钥</td></tr>
                                <?php else : ?>
                                    <?php foreach ($keys as $key) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($key['name']); ?></td>
                                            <td>
                                                <?php foreach (array_filter(explode(',', (string)$key['scopes'])) as $scope) : ?>
                                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($scope); ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php if ($key['expired']) : ?>
                                                    <span class="badge bg-secondary">已过期</span>
                                                <?php elseif ($key['enabled']) : ?>
                                                    <span class="badge bg-success">启用</span>
                                                <?php else : ?>
                                                    <span class="badge bg-danger">已停用</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($key['created_at']); ?></td>
                                            <td><?php echo $key['last_used_at'] !== null ? htmlspecialchars($key['last_used_at']) : '从未使用'; ?></td>
                                            <td class="text-nowrap">
                                                <?php if ($key['enabled'] && !$key['expired']) : ?>
                                                    <form method="post" action="keys.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                        <input type="hidden" name="key_action" value="revoke">
                                                        <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">停用</button>
                                                    </form>
                                                <?php elseif (!$key['expired']) : ?>
                                                    <form method="post" action="keys.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                        <input type="hidden" name="key_action" value="enable">
                                                        <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">启用</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" action="keys.php" class="d-inline" onsubmit="return confirm('确定要删除该密钥吗？删除后无法恢复。');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                    <input type="hidden" name="key_action" value="delete">
                                                    <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">删除</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require './views/footer.php'; ?>
