<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/markdown-import.php';
require_once __DIR__ . '/../includes/admin-data.php';
require_once __DIR__ . '/auth.php';

initSecurity();
sendSecurityHeaders();

$admin = requireAdmin();
$pdo = getPDO();

$tab = $_GET['tab'] ?? 'list';
$tab = in_array($tab, ['list', 'create', 'import', 'export'], true) ? $tab : 'list';

$message = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$error = isset($_GET['err']) ? trim((string)$_GET['err']) : '';

// 手动录入（服务端处理）
if ($tab === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF 验证失败，请刷新页面后重试。';
    } else {
        $validated = validateQuestionInput($_POST);
        if (isset($validated['errors'])) {
            $error = implode(' ', $validated['errors']);
            $form = array_merge([
                'category' => 'IT', 'question_text' => '', 'option_a' => '', 'option_b' => '',
                'option_c' => '', 'option_d' => '', 'answer' => '', 'type' => 'single', 'author' => '',
            ], array_intersect_key($_POST, array_flip(['category', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'answer', 'type', 'author'])));
        } else {
            $result = createQuestion($validated['data']);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                auditLog($pdo, $admin['identity_label'], 'question:create', '题目 #' . $result['id'], ['category' => $validated['data']['category']]);
                header('Location: questions.php?tab=list&msg=' . rawurlencode('题目 #' . $result['id'] . ' 已添加。'));
                exit;
            }
        }
    }
}

$filters = [
    'category' => in_array($_GET['category'] ?? '', QUESTION_CATEGORIES, true) ? $_GET['category'] : '',
    'type' => in_array($_GET['type'] ?? '', QUESTION_TYPES, true) ? $_GET['type'] : '',
    'q' => trim((string)($_GET['q'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$list = $tab === 'list' ? listQuestions($filters, $page, 20) : null;

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
    <title>题目管理 - 十八桥社区入站测试系统</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../views/assets/css/noto-face.css">
    <link rel="stylesheet" href="../views/assets/css/tokens.css">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
</head>
<?php require './views/nav.php'; ?>
<div class="page">
    <h2 class="page-title">题目管理</h2>
    <p class="page-subtitle">维护题库：支持手动录入、编辑、删除，以及 Markdown 表格批量导入与导出。</p>

    <?php if ($message !== '') : ?>
        <div class="alert alert-success mt-3" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== '') : ?>
        <div class="alert alert-danger mt-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mt-3" role="tablist">
        <li class="nav-item"><a class="nav-link <?php echo $tab === 'list' ? 'active' : ''; ?>" href="?tab=list">题目列表</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab === 'create' ? 'active' : ''; ?>" href="?tab=create">手动录入</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab === 'import' ? 'active' : ''; ?>" href="?tab=import">Markdown 导入</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab === 'export' ? 'active' : ''; ?>" href="?tab=export">导出</a></li>
    </ul>

    <?php if ($tab === 'list') : ?>
        <form method="get" action="questions.php" class="row g-2 mt-3 align-items-end">
            <input type="hidden" name="tab" value="list">
            <div class="col-auto">
                <label for="q" class="visually-hidden">关键词</label>
                <input type="text" class="form-control" id="q" name="q" placeholder="题干 / 命题人" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-auto">
                <label for="category" class="visually-hidden">分类</label>
                <select class="form-select" id="category" name="category">
                    <option value="">全部分类</option>
                    <?php foreach (QUESTION_CATEGORIES as $category) : ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filters['category'] === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="type" class="visually-hidden">题型</label>
                <select class="form-select" id="type" name="type">
                    <option value="">全部题型</option>
                    <option value="single" <?php echo $filters['type'] === 'single' ? 'selected' : ''; ?>>单选</option>
                    <option value="multiple" <?php echo $filters['type'] === 'multiple' ? 'selected' : ''; ?>>多选</option>
                </select>
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
                                <th scope="col">ID</th>
                                <th scope="col">分类</th>
                                <th scope="col">题干</th>
                                <th scope="col">题型</th>
                                <th scope="col">答案</th>
                                <th scope="col">命题人</th>
                                <th scope="col">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($list['items'] === []) : ?>
                                <tr><td colspan="7" class="text-muted">暂无题目</td></tr>
                            <?php else : ?>
                                <?php foreach ($list['items'] as $question) : ?>
                                    <tr data-question-id="<?php echo (int)$question['id']; ?>">
                                        <th scope="row"><?php echo (int)$question['id']; ?></th>
                                        <td><?php echo htmlspecialchars($question['category']); ?></td>
                                        <td class="text-start" style="max-width: 28rem;"><?php echo htmlspecialchars(mb_strlen($question['question_text']) > 60 ? mb_substr($question['question_text'], 0, 60) . '…' : $question['question_text']); ?></td>
                                        <td><?php echo $question['type'] === 'single' ? '单选' : '多选'; ?></td>
                                        <td><code><?php echo htmlspecialchars($question['answer']); ?></code></td>
                                        <td><?php echo htmlspecialchars($question['author'] !== '' ? $question['author'] : '—'); ?></td>
                                        <td class="text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-question" data-id="<?php echo (int)$question['id']; ?>">编辑</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-question" data-id="<?php echo (int)$question['id']; ?>">删除</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($list['pages'] > 1) : ?>
                    <nav class="mt-3" aria-label="分页">
                        <ul class="pagination mb-0">
                            <?php for ($i = 1; $i <= $list['pages']; $i++) : ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo htmlspecialchars($queryString(['page' => $i, 'tab' => 'list'])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'create') : ?>
        <?php $form = $form ?? ['category' => 'IT', 'question_text' => '', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'answer' => '', 'type' => 'single', 'author' => '']; ?>
        <div class="card mt-3">
            <div class="card-body">
                <form method="post" action="questions.php?tab=create">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="category" class="form-label">分类</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach (QUESTION_CATEGORIES as $category) : ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $form['category'] === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="type" class="form-label">题型</label>
                            <select class="form-select" id="type" name="type">
                                <option value="single" <?php echo $form['type'] === 'single' ? 'selected' : ''; ?>>单选</option>
                                <option value="multiple" <?php echo $form['type'] === 'multiple' ? 'selected' : ''; ?>>多选</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="author" class="form-label">命题人</label>
                            <input type="text" class="form-control" id="author" name="author" value="<?php echo htmlspecialchars($form['author']); ?>" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label for="question_text" class="form-label">题干</label>
                            <textarea class="form-control" id="question_text" name="question_text" rows="3" required><?php echo htmlspecialchars($form['question_text']); ?></textarea>
                        </div>
                        <?php foreach (['A', 'B', 'C', 'D'] as $letter) : ?>
                            <div class="col-md-6">
                                <label for="option_<?php echo strtolower($letter); ?>" class="form-label">选项 <?php echo $letter; ?></label>
                                <input type="text" class="form-control" id="option_<?php echo strtolower($letter); ?>" name="option_<?php echo strtolower($letter); ?>" value="<?php echo htmlspecialchars($form['option_' . strtolower($letter)]); ?>" required maxlength="255">
                            </div>
                        <?php endforeach; ?>
                        <div class="col-md-4">
                            <label for="answer" class="form-label">答案</label>
                            <input type="text" class="form-control" id="answer" name="answer" value="<?php echo htmlspecialchars($form['answer']); ?>" placeholder="单选填 A；多选填 AB" required>
                            <small class="form-text">多选答案由 A/B/C/D 字母组合而成。</small>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">提交</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'import') : ?>
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Markdown 表格导入</h5>
                <p class="card-text">选择分类后，粘贴 Markdown 表格。表头为 <code>| 题干 | A | B | C | D | 答案 | 类型 | 命题人 |</code>（答案如 <code>A</code>、<code>AB</code>；类型为 <code>single</code> 或 <code>multiple</code>；命题人可留空）。点击「预览」后确认导入。</p>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="import_category" class="form-label">目标分类</label>
                        <select class="form-select" id="import_category">
                            <?php foreach (QUESTION_CATEGORIES as $category) : ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="skip_duplicates" checked>
                            <label class="form-check-label" for="skip_duplicates">跳过重复题（同分类同题干）</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="import_markdown" class="form-label">Markdown 表格内容</label>
                        <textarea class="form-control" id="import_markdown" rows="10" placeholder="| 题干 | A | B | C | D | 答案 | 类型 | 命题人 |&#10;| --- | --- | --- | --- | --- | --- | --- | --- |"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" id="btn-preview-import">预览</button>
                        <button type="button" class="btn btn-success d-none" id="btn-confirm-import">确认导入</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btn-clear-import">清空结果</button>
                    </div>
                </div>
                <div id="import-result" class="mt-3"></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'export') : ?>
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">导出 Markdown 表格</h5>
                <p class="card-text">导出当前题库（可按分类、题型筛选），生成的内容可直接用于导入备份或迁移。</p>
                <div class="row g-3">
                    <div class="col-auto">
                        <label for="export_category" class="visually-hidden">分类</label>
                        <select class="form-select" id="export_category">
                            <option value="">全部分类</option>
                            <?php foreach (QUESTION_CATEGORIES as $category) : ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="export_type" class="visually-hidden">题型</label>
                        <select class="form-select" id="export_type">
                            <option value="">全部题型</option>
                            <option value="single">单选</option>
                            <option value="multiple">多选</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-primary" id="btn-export">导出</button>
                    </div>
                </div>
                <textarea class="form-control mt-3" id="export_markdown" rows="15" readonly placeholder="点击「导出」后此处显示生成的 Markdown 表格。"></textarea>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">编辑题目</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body">
                <div id="editModalError" class="alert alert-danger d-none"></div>
                <input type="hidden" id="edit_id">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="edit_category" class="form-label">分类</label>
                        <select class="form-select" id="edit_category">
                            <?php foreach (QUESTION_CATEGORIES as $category) : ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_type" class="form-label">题型</label>
                        <select class="form-select" id="edit_type">
                            <option value="single">单选</option>
                            <option value="multiple">多选</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="edit_author" class="form-label">命题人</label>
                        <input type="text" class="form-control" id="edit_author" maxlength="100">
                    </div>
                    <div class="col-12">
                        <label for="edit_question_text" class="form-label">题干</label>
                        <textarea class="form-control" id="edit_question_text" rows="3"></textarea>
                    </div>
                    <?php foreach (['A', 'B', 'C', 'D'] as $letter) : ?>
                        <div class="col-md-6">
                            <label for="edit_option_<?php echo strtolower($letter); ?>" class="form-label">选项 <?php echo $letter; ?></label>
                            <input type="text" class="form-control" id="edit_option_<?php echo strtolower($letter); ?>" maxlength="255">
                        </div>
                    <?php endforeach; ?>
                    <div class="col-md-4">
                        <label for="edit_answer" class="form-label">答案</label>
                        <input type="text" class="form-control" id="edit_answer" maxlength="7">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="btn-save-question">保存</button>
            </div>
        </div>
    </div>
</div>

<?php require './views/footer.php'; ?>
<script src="./views/assets/js/admin.js"></script>
