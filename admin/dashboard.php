<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

$admin = requireAdmin();
$stats = getStatsSummary();

$forum = $stats['forum'];
$matrix = $stats['matrix'];
$questions = $stats['questions'];
$today = $stats['today'];
$config = $stats['config'];
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <h1 class="page-title">数据统计</h1>
    <p class="page-subtitle">你好，<?php echo htmlspecialchars($admin['identity_label']); ?>。以下是入站测试系统的最新数据概况。</p>

    <?php if ($config['forum_closed']) : ?>
        <div class="alert alert-warning mt-3" role="alert">社区论坛测试通道当前已关闭。</div>
    <?php endif; ?>
    <?php if ($config['matrix_enabled'] && $config['matrix_closed']) : ?>
        <div class="alert alert-warning mt-3" role="alert">千万桥测试通道当前已关闭。</div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">社区论坛测试</h5>
                    <div class="d-flex justify-content-between mt-3"><span class="text-muted">登记用户</span><strong><?php echo (int)$forum['users']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">完成测试</span><strong><?php echo (int)$forum['results']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">通过（≥<?php echo (int)$config['forum_threshold']; ?> 分）</span><strong><?php echo (int)$forum['passed']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">通过率</span><strong><?php echo htmlspecialchars((string)$forum['pass_rate']); ?>%</strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">平均分</span><strong><?php echo htmlspecialchars((string)$forum['avg_score']); ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">礼仪免考人数</span><strong><?php echo (int)$forum['exempt_count']; ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">千万桥测试</h5>
                    <div class="d-flex justify-content-between mt-3"><span class="text-muted">登记用户</span><strong><?php echo (int)$matrix['users']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">完成测试</span><strong><?php echo (int)$matrix['results']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">通过（≥<?php echo (int)$config['matrix_threshold']; ?> 分）</span><strong><?php echo (int)$matrix['passed']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">通过率</span><strong><?php echo htmlspecialchars((string)$matrix['pass_rate']); ?>%</strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">平均分</span><strong><?php echo htmlspecialchars((string)$matrix['avg_score']); ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">礼仪免考人数</span><strong><?php echo (int)$matrix['exempt_count']; ?></strong></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">题库与今日动态</h5>
                    <div class="d-flex justify-content-between mt-3"><span class="text-muted">题目总数</span><strong><?php echo (int)$questions['total']; ?></strong></div>
                    <?php foreach (['IT', 'ACGN', 'Virtual_Singer', 'Broadcasting', 'Etiquette'] as $category) : ?>
                        <div class="d-flex justify-content-between mt-2"><span class="text-muted"><?php echo htmlspecialchars($category); ?></span><strong><?php echo (int)($questions['categories'][$category] ?? 0); ?></strong></div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">今日论坛新增登记</span><strong><?php echo (int)$today['forum_registered']; ?></strong></div>
                    <div class="d-flex justify-content-between mt-2"><span class="text-muted">今日千万桥新增登记</span><strong><?php echo (int)$today['matrix_registered']; ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">最近论坛测试</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">用户名</th>
                                    <th scope="col">分数</th>
                                    <th scope="col">交卷时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stats['recent']['forum'] === []) : ?>
                                    <tr><td colspan="3" class="text-muted">暂无记录</td></tr>
                                <?php else : ?>
                                    <?php foreach ($stats['recent']['forum'] as $row) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td><?php echo (int)$row['score']; ?></td>
                                            <td><?php echo htmlspecialchars($row['end_time']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">最近千万桥测试</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">用户名</th>
                                    <th scope="col">分数</th>
                                    <th scope="col">交卷时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stats['recent']['matrix'] === []) : ?>
                                    <tr><td colspan="3" class="text-muted">暂无记录</td></tr>
                                <?php else : ?>
                                    <?php foreach ($stats['recent']['matrix'] as $row) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td><?php echo (int)$row['score']; ?></td>
                                            <td><?php echo htmlspecialchars($row['end_time']); ?></td>
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
