<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

// 黑名单数据访问与检测逻辑。
// 管理面板（admin/blacklist.php）、系统 API（api/index.php）与考试业务层
// （includes/exam-service.php）共用，保证网页与 API 行为一致。
//
// 规则：
// - 以邮箱为条目主键（email 唯一，统一存为小写），一个条目可关联多个 IP；
// - 检测到被拉黑邮箱时自动记录访问者 IP 并累计检测次数；
// - 任一条目中的 IP 命中时同样拒绝测试，并累计该条目的检测次数。

// 规范化 IP 列表：仅保留合法 IPv4 / IPv6 地址并去重
function normalizeBlacklistIps(array $raw): array
{
    $ips = [];
    foreach ($raw as $value) {
        $ip = trim((string)$value);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            continue;
        }
        $ips[$ip] = $ip;
    }
    return array_values($ips);
}

// 解码数据库中存储的 IP 列表（JSON 数组），返回数组
function decodeBlacklistIps(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), 'strlen')) : [];
}

// 判断邮箱是否已存在于黑名单（可选排除指定条目 ID）
function blacklistEmailExists(PDO $db, string $email, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM blacklist WHERE email = ? AND id != ?');
        $stmt->execute([$email, $excludeId]);
    } else {
        $stmt = $db->prepare('SELECT COUNT(*) FROM blacklist WHERE email = ?');
        $stmt->execute([$email]);
    }
    return (int)$stmt->fetchColumn() > 0;
}

// 黑名单列表（q 匹配邮箱或原因）
function listBlacklistEntries(array $filters, int $page, int $perPage): array
{
    $db = getPDO();

    $where = [];
    $params = [];
    if (!empty($filters['q'])) {
        $where[] = '(email LIKE ? OR reason LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare('SELECT COUNT(*) FROM blacklist' . $whereSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT * FROM blacklist' . $whereSql . ' ORDER BY id DESC LIMIT ? OFFSET ?');
    $stmt->execute(array_merge($params, [$perPage, ($page - 1) * $perPage]));

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['ips'] = decodeBlacklistIps((string)$row['ips']);
        $items[] = $row;
    }

    return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int)ceil($total / $perPage)];
}

// 新建黑名单条目，返回 ['id' => int] 或 ['error' => 原因]
function createBlacklistEntry(string $email, array $ips, string $reason): array
{
    $db = getPDO();
    $email = strtolower(trim($email));

    if (blacklistEmailExists($db, $email)) {
        return ['error' => '该邮箱已在黑名单中。'];
    }

    $stmt = $db->prepare('INSERT INTO blacklist (email, ips, reason) VALUES (?, ?, ?)');
    $stmt->execute([
        mb_substr($email, 0, 255),
        json_encode(normalizeBlacklistIps($ips), JSON_UNESCAPED_UNICODE),
        mb_substr(trim($reason), 0, 255) !== '' ? mb_substr(trim($reason), 0, 255) : null,
    ]);

    return ['id' => (int)$db->lastInsertId()];
}

// 更新黑名单条目（IP 列表与原因；邮箱作为条目主键不可修改）
function updateBlacklistEntry(int $id, array $ips, string $reason): bool
{
    $stmt = getPDO()->prepare('UPDATE blacklist SET ips = ?, reason = ? WHERE id = ?');
    $stmt->execute([
        json_encode(normalizeBlacklistIps($ips), JSON_UNESCAPED_UNICODE),
        mb_substr(trim($reason), 0, 255) !== '' ? mb_substr(trim($reason), 0, 255) : null,
        $id,
    ]);
    return $stmt->rowCount() > 0;
}

// 删除黑名单条目
function deleteBlacklistEntry(int $id): bool
{
    $stmt = getPDO()->prepare('DELETE FROM blacklist WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

// 读取单个黑名单条目（不存在返回 null）
function getBlacklistEntry(int $id): ?array
{
    $stmt = getPDO()->prepare('SELECT * FROM blacklist WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }
    $row['ips'] = decodeBlacklistIps((string)$row['ips']);
    return $row;
}

// 黑名单检测：email 命中或 ip 命中任一条目时，累计检测次数；
// email 命中时自动将访问者 IP 补充进该条目的 IP 列表。
// 返回 ['entry_id' => int, 'matched' => 'email'|'ip', 'email' => string] 或 null。
function checkBlacklist(string $email, string $ip): ?array
{
    $db = getPDO();
    $email = strtolower(trim($email));
    $ip = trim($ip);

    if ($email === '' || $ip === '') {
        return null;
    }

    $stmt = $db->prepare('SELECT id, ips FROM blacklist WHERE email = ?');
    $stmt->execute([$email]);
    $entry = $stmt->fetch();

    $matchedIp = false;
    if ($entry === false) {
        // 邮箱未命中时，检查访问者 IP 是否命中任一条目的 IP 列表
        foreach ($db->query('SELECT id, ips FROM blacklist') as $row) {
            if (in_array($ip, decodeBlacklistIps((string)$row['ips']), true)) {
                $entry = $row;
                $matchedIp = true;
                break;
            }
        }
    }
    if ($entry === false) {
        return null;
    }

    $entryId = (int)$entry['id'];
    $ips = decodeBlacklistIps((string)$entry['ips']);

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('UPDATE blacklist SET detection_count = detection_count + 1 WHERE id = ?');
        $stmt->execute([$entryId]);

        if (!$matchedIp && !in_array($ip, $ips, true)) {
            $ips[] = $ip;
            $stmt = $db->prepare('UPDATE blacklist SET ips = ? WHERE id = ?');
            $stmt->execute([json_encode($ips, JSON_UNESCAPED_UNICODE), $entryId]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('黑名单检测记录失败：' . $e->getMessage());
    }

    return ['entry_id' => $entryId, 'matched' => $matchedIp ? 'ip' : 'email', 'email' => $email];
}
