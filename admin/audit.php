<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

requireAdmin();

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$data = listAuditLogs($filters, $page, 20);

$queryString = function (array $overrides) use ($filters) {
    return http_build_query(array_filter(array_merge($filters, $overrides), function ($v) {
        return $v !== '';
    }));
};
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>审计日志 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <h2 class="page-title">审计日志</h2>
    <p class="page-subtitle">记录管理员面板与系统 API 的关键变更操作。</p>

    <form method="get" action="audit.php" class="row g-2 mt-3 align-items-end">
        <div class="col-auto">
            <label for="q" class="visually-hidden">关键词</label>
            <input type="text" class="form-control" id="q" name="q" placeholder="操作者 / 动作 / 目标" value="<?php echo htmlspecialchars($filters['q']); ?>">
        </div>
        <div class="col-auto">
            <label for="action" class="visually-hidden">动作</label>
            <input type="text" class="form-control" id="action" name="action" placeholder="动作（可选）" value="<?php echo htmlspecialchars($filters['action']); ?>">
        </div>
        <div class="col-auto">
            <label for="date_from" class="visually-hidden">开始日期</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
        </div>
        <div class="col-auto">
            <label for="date_to" class="visually-hidden">结束日期</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">筛选</button>
        </div>
    </form>

    <div class="card mt-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">时间</th>
                            <th scope="col">操作者</th>
                            <th scope="col">动作</th>
                            <th scope="col">目标</th>
                            <th scope="col">详情</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data['items'] === []) : ?>
                            <tr><td colspan="6" class="text-muted">暂无日志</td></tr>
                        <?php else : ?>
                            <?php foreach ($data['items'] as $log) : ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($log['actor']); ?></td>
                                    <td><code><?php echo htmlspecialchars($log['action']); ?></code></td>
                                    <td><?php echo $log['target'] !== null ? htmlspecialchars($log['target']) : '—'; ?></td>
                                    <td><?php echo $log['detail'] !== null ? '<code>' . htmlspecialchars(mb_strlen($log['detail']) > 80 ? mb_substr($log['detail'], 0, 80) . '…' : $log['detail']) . '</code>' : '—'; ?></td>
                                    <td><?php echo htmlspecialchars((string)$log['ip']); ?></td>
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
<?php require './views/footer.php'; ?>
