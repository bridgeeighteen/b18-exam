<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/../includes/blacklist.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

$admin = requireAdmin();
$pdo = getPDO();

$message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$error = isset($_GET['err']) ? trim((string)$_GET['err']) : '';

// 创建条目（服务端处理）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blacklist_action']) && $_POST['blacklist_action'] === 'create') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $ips = preg_split('/[\s,，;；]+/', (string)($_POST['ips'] ?? '')) ?: [];
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $error = '电子邮箱地址格式不正确。';
        } elseif (normalizeBlacklistIps($ips) === []) {
            $error = 'IP 地址列表为空或均不合法。';
        } else {
            $result = createBlacklistEntry($email, $ips, $reason);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                auditLog($pdo, $admin['identity_label'], 'blacklist:create', '黑名单：' . strtolower($email), ['ips' => normalizeBlacklistIps($ips), 'reason' => $reason !== '' ? $reason : null]);
                $message = '黑名单条目已创建。';
            }
        }
    }
}

// 更新条目（服务端处理）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blacklist_action'], $_POST['blacklist_id']) && $_POST['blacklist_action'] === 'update') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $entryId = max(1, (int)$_POST['blacklist_id']);
        $entry = getBlacklistEntry($entryId);
        $ips = preg_split('/[\s,，;；]+/', (string)($_POST['ips'] ?? '')) ?: [];
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($entry === null) {
            $error = '黑名单条目不存在。';
        } elseif (normalizeBlacklistIps($ips) === []) {
            $error = 'IP 地址列表为空或均不合法。';
        } else {
            updateBlacklistEntry($entryId, $ips, $reason);
            auditLog($pdo, $admin['identity_label'], 'blacklist:update', '黑名单：' . $entry['email'], ['ips' => normalizeBlacklistIps($ips), 'reason' => $reason !== '' ? $reason : null]);
            $message = '黑名单条目已更新。';
        }
    }
}

// 删除条目（服务端处理）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blacklist_action'], $_POST['blacklist_id']) && $_POST['blacklist_action'] === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $entryId = max(1, (int)$_POST['blacklist_id']);
        $entry = getBlacklistEntry($entryId);
        if ($entry === null) {
            $error = '黑名单条目不存在。';
        } else {
            deleteBlacklistEntry($entryId);
            auditLog($pdo, $admin['identity_label'], 'blacklist:delete', '黑名单：' . $entry['email']);
            $message = '黑名单条目已删除。';
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$data = listBlacklistEntries(['q' => trim((string)($_GET['q'] ?? ''))], $page, 20);

$editing = isset($_GET['edit']) ? getBlacklistEntry(max(1, (int)$_GET['edit'])) : null;

$queryString = function (array $overrides) use ($data) {
    $params = array_merge(['q' => trim((string)($_GET['q'] ?? ''))], $overrides);
    return http_build_query(array_filter($params, function ($v) {
        return $v !== '';
    }));
};
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>黑名单 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <h1 class="page-title">黑名单</h1>
    <p class="page-subtitle">被拉黑的邮箱或 IP 地址无法开始或提交测试。检测到被拉黑邮箱时，系统会自动记录访问者 IP 并累计检测次数。</p>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success mt-3" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="alert alert-danger mt-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $editing !== null ? '编辑条目' : '新建条目'; ?></h5>
                    <form method="post" action="blacklist.php">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                        <input type="hidden" name="blacklist_action" value="<?php echo $editing !== null ? 'update' : 'create'; ?>">
                        <?php if ($editing !== null) : ?>
                            <input type="hidden" name="blacklist_id" value="<?php echo (int)$editing['id']; ?>">
                            <div class="form-section">
                                <label for="email" class="form-label">邮箱</label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($editing['email']); ?>" disabled>
                                <small class="form-text">邮箱作为条目主键，创建后不可修改。</small>
                            </div>
                        <?php else : ?>
                            <div class="form-section">
                                <label for="email" class="form-label">邮箱 <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" maxlength="254" placeholder="被拉黑的邮箱地址" required>
                            </div>
                        <?php endif; ?>
                        <div class="form-section">
                            <label for="ips" class="form-label">IP 地址</label>
                            <textarea class="form-control" id="ips" name="ips" rows="4" placeholder="每行一个，或使用逗号分隔，支持多个 IP"><?php echo $editing !== null ? htmlspecialchars(implode("\n", $editing['ips'])) : ''; ?></textarea>
                            <small class="form-text">IPv4 / IPv6 均可。留空则仅匹配邮箱；检测到被拉黑邮箱时系统会自动补充其访问 IP。</small>
                        </div>
                        <div class="form-section">
                            <label for="reason" class="form-label">原因</label>
                            <input type="text" class="form-control" id="reason" name="reason" maxlength="255" value="<?php echo $editing !== null ? htmlspecialchars((string)$editing['reason']) : ''; ?>" placeholder="可选">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><?php echo $editing !== null ? '保存修改' : '创建'; ?></button>
                            <?php if ($editing !== null) : ?>
                                <a class="btn btn-outline-secondary" href="blacklist.php">取消</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <form method="get" action="blacklist.php" class="row g-2 align-items-end mb-3">
                <div class="col-auto">
                    <label for="q" class="visually-hidden">关键词</label>
                    <input type="text" class="form-control" id="q" name="q" placeholder="邮箱 / 原因" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">筛选</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="blacklist.php">重置</a>
                </div>
            </form>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">条目列表</h5>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">邮箱</th>
                                    <th scope="col">IP 地址</th>
                                    <th scope="col">检测次数</th>
                                    <th scope="col">原因</th>
                                    <th scope="col">创建时间</th>
                                    <th scope="col">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($data['items'] === []) : ?>
                                    <tr><td colspan="7" class="text-muted">暂无条目</td></tr>
                                <?php else : ?>
                                    <?php foreach ($data['items'] as $entry) : ?>
                                        <tr>
                                            <th scope="row"><?php echo (int)$entry['id']; ?></th>
                                            <td><?php echo htmlspecialchars($entry['email']); ?></td>
                                            <td>
                                                <?php if ($entry['ips'] === []) : ?>
                                                    <span class="text-muted">—</span>
                                                <?php else : ?>
                                                    <?php foreach ($entry['ips'] as $ip) : ?>
                                                        <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($ip); ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-<?php echo (int)$entry['detection_count'] > 0 ? 'warning' : 'light text-dark border'; ?>"><?php echo (int)$entry['detection_count']; ?></span></td>
                                            <td><?php echo $entry['reason'] !== null ? htmlspecialchars($entry['reason']) : '—'; ?></td>
                                            <td><?php echo htmlspecialchars($entry['created_at']); ?></td>
                                            <td class="text-nowrap">
                                                <a class="btn btn-sm btn-outline-secondary" href="?edit=<?php echo (int)$entry['id']; ?>">编辑</a>
                                                <form method="post" action="blacklist.php" class="d-inline" onsubmit="return confirm('确定要删除该黑名单条目吗？删除后无法恢复。');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                    <input type="hidden" name="blacklist_action" value="delete">
                                                    <input type="hidden" name="blacklist_id" value="<?php echo (int)$entry['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">删除</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($data['pages'] > 1) : ?>
                        <nav class="mt-3" aria-label="分页">
                            <ul class="pagination mb-0">
                                <?php for ($i = 1; $i <= $data['pages']; $i++) : ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo htmlspecialchars($queryString(['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require './views/footer.php'; ?>
