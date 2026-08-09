<?php

require_once __DIR__ . '/../config.php';

// Markdown 试题表格解析器。
// 表格表头（顺序可任意，多余列会被忽略）：
// | 题干 | A | B | C | D | 答案 | 类型 | 命题人 |
// 可选包含“分类”列，若表格中提供分类则优先使用表格中的分类，否则使用面板中选择的分类。

const MD_HEADER_ALIASES = [
    'category' => ['分类', '类别'],
    'question_text' => ['题干', '题目', '题目内容'],
    'option_a' => ['A', '选项A', '选项 A'],
    'option_b' => ['B', '选项B', '选项 B'],
    'option_c' => ['C', '选项C', '选项 C'],
    'option_d' => ['D', '选项D', '选项 D'],
    'answer' => ['答案', 'Answer'],
    'type' => ['类型', '题型'],
    'author' => ['命题人', '作者', 'Author'],
];

// 按 '|' 拆分一行表格单元格（支持 \| 转义、可选首尾管道线）
function mdSplitRow(string $line): array
{
    $line = trim($line);
    if (mb_substr($line, 0, 1) === '|') {
        $line = mb_substr($line, 1);
    }
    if (mb_substr($line, -1) === '|') {
        $line = mb_substr($line, 0, -1);
    }

    $cells = [];
    $buffer = '';
    $length = mb_strlen($line);

    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($line, $i, 1);
        if ($char === '\\' && $i + 1 < $length && mb_substr($line, $i + 1, 1) === '|') {
            $buffer .= '|';
            $i++;
        } elseif ($char === '|') {
            $cells[] = trim($buffer);
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }

    $cells[] = trim($buffer);
    return $cells;
}

// 判断一行是否为分隔行（如 |---|---|）
function mdIsSeparatorRow(array $cells): bool
{
    if ($cells === []) {
        return false;
    }
    foreach ($cells as $cell) {
        if (trim($cell) === '') {
            return false;
        }
        if (preg_match('/^:?-{2,}:?$/', trim($cell)) !== 1) {
            return false;
        }
    }
    return true;
}

// 解析 Markdown 文本，返回行列表：['header' => [列键 => 单元格索引] 或 null, 'rows' => [['raw' => [...], 'cells' => [列键 => 值]]]
function parseMarkdownQuestionTable(string $markdown): array
{
    // 注意：不能用 /\R/ 或 /u 组合以外的写法分割行，\R 会误匹配 UTF-8 多字节字符中的 0x85 字节
    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $rows = [];
    $currentHeader = null;

    foreach ($lines as $line) {
        if (trim($line) === '' || strpos($line, '|') === false) {
            continue;
        }

        $cells = mdSplitRow($line);

        if (mdIsSeparatorRow($cells)) {
            continue;
        }

        if ($cells === []) {
            continue;
        }

        // 识别表头：单元格与已知表头别名匹配（至少匹配 4 列）
        $mapped = mdMapHeader($cells);
        if ($mapped !== null) {
            $currentHeader = $mapped;
            continue;
        }

        if ($currentHeader === null) {
            continue;
        }

        $rowCells = [];
        foreach ($currentHeader as $key => $index) {
            $rowCells[$key] = $cells[$index] ?? '';
        }
        $rows[] = ['raw' => $cells, 'cells' => $rowCells];
    }

    return ['header' => $currentHeader, 'rows' => $rows];
}

// 将一行单元格映射为 [列键 => 单元格索引]，不匹配表头时返回 null
function mdMapHeader(array $cells): ?array
{
    $map = [];

    foreach ($cells as $index => $cell) {
        $cell = trim($cell);
        foreach (MD_HEADER_ALIASES as $key => $aliases) {
            if (isset($map[$key])) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (strcasecmp($cell, $alias) === 0) {
                    $map[$key] = $index;
                    break 2;
                }
            }
        }
    }

    // 至少需要题干 + 答案两列才视为表头
    if (!isset($map['question_text'], $map['answer'])) {
        return null;
    }

    return $map;
}

// 校验一行题目，返回 ['valid' => bool, 'errors' => array, 'data' => array|null]
function validateImportedQuestionRow(array $cells, string $defaultCategory): array
{
    $categories = ['IT', 'ACGN', 'Virtual_Singer', 'Broadcasting', 'Etiquette'];
    $errors = [];
    $data = [];

    $category = isset($cells['category']) && trim($cells['category']) !== '' ? trim($cells['category']) : $defaultCategory;
    if (!in_array($category, $categories, true)) {
        $errors[] = '分类“' . htmlspecialchars($category) . '”无效。';
    } else {
        $data['category'] = $category;
    }

    $questionText = trim($cells['question_text'] ?? '');
    if ($questionText === '') {
        $errors[] = '题干为空。';
    } elseif (mb_strlen($questionText) > 5000) {
        $errors[] = '题干过长（最多 5000 字）。';
    } else {
        $data['question_text'] = $questionText;
    }

    foreach (['A', 'B', 'C', 'D'] as $letter) {
        $option = trim($cells['option_' . strtolower($letter)] ?? '');
        if ($option === '') {
            $errors[] = '选项 ' . $letter . ' 为空。';
        } elseif (mb_strlen($option) > 255) {
            $errors[] = '选项 ' . $letter . ' 过长（最多 255 字）。';
        } else {
            $data['option_' . strtolower($letter)] = $option;
        }
    }

    $type = trim($cells['type'] ?? '');
    if ($type === '') {
        $type = 'single';
    }
    if (!in_array($type, ['single', 'multiple'], true)) {
        $errors[] = '题型“' . htmlspecialchars($type) . '”无效（仅支持 single / multiple）。';
    } else {
        $data['type'] = $type;
    }

    if (isset($data['type'])) {
        $answer = normalizeAnswer(trim($cells['answer'] ?? ''), $data['type']);
        if ($answer === null) {
            $errors[] = $data['type'] === 'single' ? '单选题答案只能是一个选项（A/B/C/D）。' : '多选题答案至少包含两个选项（A/B/C/D 中的字母组合）。';
        } else {
            $data['answer'] = $answer;
        }
    }

    $data['author'] = mb_substr(trim($cells['author'] ?? ''), 0, 100);

    if ($errors !== []) {
        return ['valid' => false, 'errors' => $errors, 'data' => null];
    }

    return ['valid' => true, 'errors' => [], 'data' => $data];
}
