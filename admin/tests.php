<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

$admin = requireAdmin();

$channel = $_GET['channel'] ?? 'forum';
$channel = $channel === 'matrix' ? 'matrix' : 'forum';

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'status' => in_array($_GET['status'] ?? '', ['pass', 'fail'], true) ? $_GET['status'] : '',
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$data = listResults($channel, $filters, $page, $perPage);

$threshold = $channel === 'matrix' ? (int)MATRIX_SCORE_THRESHOLD : (int)SCORE_THRESHOLD;

$queryString = function (array $overrides) use ($filters, $channel) {
    $params = array_merge(['channel' => $channel], $filters, $overrides);
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
    <title>测试信息 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <h2 class="page-title">测试信息</h2>
    <p class="page-subtitle">查看各通道的测试记录，通过分数阈值为 <?php echo $threshold; ?> 分。</p>

    <ul class="nav nav-tabs mt-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?php echo $channel === 'forum' ? 'active' : ''; ?>" href="?<?php echo htmlspecialchars($queryString(['channel' => 'forum'])); ?>">社区论坛测试</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $channel === 'matrix' ? 'active' : ''; ?>" href="?<?php echo htmlspecialchars($queryString(['channel' => 'matrix'])); ?>">千万桥测试</a>
        </li>
    </ul>

    <form method="get" action="tests.php" class="row g-2 mt-3 align-items-end">
        <input type="hidden" name="channel" value="<?php echo htmlspecialchars($channel); ?>">
        <div class="col-auto">
            <label for="q" class="visually-hidden">关键词</label>
            <input type="text" class="form-control" id="q" name="q" placeholder="用户名 / 邮箱" value="<?php echo htmlspecialchars($filters['q']); ?>">
        </div>
        <div class="col-auto">
            <label for="status" class="visually-hidden">结果</label>
            <select class="form-select" id="status" name="status">
                <option value="">全部结果</option>
                <option value="pass" <?php echo $filters['status'] === 'pass' ? 'selected' : ''; ?>>通过</option>
                <option value="fail" <?php echo $filters['status'] === 'fail' ? 'selected' : ''; ?>>未通过</option>
            </select>
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
        <div class="col-auto">
            <a class="btn btn-outline-secondary" href="?channel=<?php echo htmlspecialchars($channel); ?>">重置</a>
        </div>
    </form>

    <div class="card mt-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">记录 ID</th>
                            <th scope="col">用户名</th>
                            <th scope="col">邮箱</th>
                            <th scope="col">分数</th>
                            <th scope="col">结果</th>
                            <th scope="col">交卷时间</th>
                            <th scope="col"><?php echo $channel === 'matrix' ? '注册 Token' : '邀请码'; ?></th>
                            <th scope="col">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data['items'] === []) : ?>
                            <tr><td colspan="8" class="text-muted">暂无记录</td></tr>
                        <?php else : ?>
                            <?php foreach ($data['items'] as $row) : ?>
                                <tr>
                                    <th scope="row"><?php echo (int)$row['id']; ?></th>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo (int)$row['score']; ?></td>
                                    <td>
                                        <?php if ($row['passed']) : ?>
                                            <span class="badge bg-success">通过</span>
                                        <?php else : ?>
                                            <span class="badge bg-danger">未通过</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['end_time']); ?></td>
                                    <td><code><?php echo htmlspecialchars(mb_strlen((string)$row['code']) > 24 ? mb_substr((string)$row['code'], 0, 24) . '…' : (string)$row['code']); ?></code></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal" data-detail="<?php echo (int)$row['id']; ?>" data-channel="<?php echo htmlspecialchars($channel); ?>">详情</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-result" data-channel="<?php echo htmlspecialchars($channel); ?>" data-id="<?php echo (int)$row['id']; ?>">删除记录</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user" data-channel="<?php echo htmlspecialchars($channel); ?>" data-user-id="<?php echo (int)$row['user_id']; ?>">删除用户</button>
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

<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">记录详情</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <div class="text-muted">加载中…</div>
            </div>
        </div>
    </div>
</div>

<?php require './views/footer.php'; ?>
<script src="./views/assets/js/admin.js"></script>
