<?php
/**
 * 构建演员 / 标签倒排表（供 P1 聚合页使用）
 *
 * 用法：
 *   php tools/build-relations.php /www/wwwroot/www.ljly.net            # 全量重建
 *   php tools/build-relations.php /www/wwwroot/www.ljly.net --limit=5000
 *
 * 说明：
 *   - mac_vod 里演员/标签是文本字段（" , " 分隔），无法直接聚合，因此落成两张倒排表
 *   - nf_vod_actor(tag, vod_id) 主键天然去重，重建时 TRUNCATE 重灌
 *   - 每次采集/入库后需要重跑本脚本，已有数据自动刷新
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
error_reporting(E_ALL & ~E_NOTICE);
$root = isset($argv[1]) ? rtrim($argv[1], '/') : dirname(__DIR__);
$limit = 0;
foreach ($argv as $a) {
    if (strpos($a, '--limit=') === 0) {
        $limit = (int)substr($a, 8);
    }
}
$cfgFile = $root . '/application/database.php';
if (!is_file($cfgFile)) {
    exit("database.php not found: $cfgFile\n");
}
$cfg = include $cfgFile;
$host = $cfg['hostname'] ?? '127.0.0.1';
$port = $cfg['hostport'] ?? 3306;
$dbname = $cfg['database'] ?? '';
$user = $cfg['username'] ?? '';
$pass = $cfg['password'] ?? '';
$prefix = $cfg['prefix'] ?? 'mac_';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Exception $e) {
    exit('DB connect failed: ' . $e->getMessage() . "\n");
}

function out($s)
{
    echo '[' . date('H:i:s') . '] ' . $s . "\n";
}

/* ---------- 1. 建表 ---------- */
$ddl = [
    "CREATE TABLE IF NOT EXISTS `nf_vod_actor` (
        `vod_id` int unsigned NOT NULL,
        `actor` varchar(80) NOT NULL,
        PRIMARY KEY (`actor`,`vod_id`),
        KEY `idx_vod` (`vod_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_actor` (
        `name` varchar(80) NOT NULL,
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`name`),
        KEY `idx_count` (`vod_count`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_vod_tag` (
        `vod_id` int unsigned NOT NULL,
        `tag` varchar(40) NOT NULL,
        PRIMARY KEY (`tag`,`vod_id`),
        KEY `idx_vod` (`vod_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_tag` (
        `name` varchar(40) NOT NULL,
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`name`),
        KEY `idx_count` (`vod_count`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_vod_director` (
        `vod_id` int unsigned NOT NULL,
        `director` varchar(80) NOT NULL,
        PRIMARY KEY (`director`,`vod_id`),
        KEY `idx_vod` (`vod_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_director` (
        `name` varchar(80) NOT NULL,
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        `slug` varchar(64) NOT NULL DEFAULT '',
        PRIMARY KEY (`name`),
        KEY `idx_count` (`vod_count`),
        KEY `idx_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}
// P1 新增 slug 列，旧库自动补
foreach (['nf_actor', 'nf_tag', 'nf_director'] as $t) {
    $has = $pdo->query("SHOW COLUMNS FROM `$t` LIKE 'slug'")->fetch();
    if (!$has) {
        $pdo->exec("ALTER TABLE `$t` ADD COLUMN `slug` varchar(64) NOT NULL DEFAULT '', ADD KEY `idx_slug` (`slug`)");
    }
}
out('tables ready');

/* ---------- 2. 清空倒排表 ---------- */
$pdo->exec('TRUNCATE TABLE nf_vod_actor');
$pdo->exec('TRUNCATE TABLE nf_vod_tag');
$pdo->exec('TRUNCATE TABLE nf_vod_director');

/* ---------- 3. 拆分与灌入 ---------- */
$actorBlack = ['内详', '未知', '暂无', '无', '主演', '演员', '群演', '导演', '佚名', '不详', '其他'];
$tagBlack   = ['内详', '未知', '暂无', '无', '其他', '其它'];

function splitNames($str, $black, $max)
{
    $str = trim((string)$str);
    if ($str === '') {
        return [];
    }
    // 采集源里大量 &amp; &#039; 之类的 HTML 实体，必须先解码，否则会被当成分隔符切碎
    $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $str = str_replace(['&nbsp;', '　', '&'], ' ', $str);
    $parts = preg_split('/\s*[,，、\/|;；]+\s*/u', $str);
    $out = [];
    foreach ($parts as $p) {
        $p = trim(preg_replace('/\s+/u', ' ', $p));
        $len = mb_strlen($p, 'UTF-8');
        if ($p === '' || $len < 1 || $len > $max) {
            continue;
        }
        if (in_array($p, $black, true)) {
            continue;
        }
        // 纯数字/符号、单个英文字母（实体碎片），一律丢弃
        if (preg_match('/^[\d\.\-]+$/', $p)) {
            continue;
        }
        if ($len === 1 && preg_match('/^[A-Za-z0-9]$/', $p)) {
            continue;
        }
        if (preg_match('/^[\s\p{P}]+$/u', $p)) {
            continue;
        }
        $out[$p] = 1;
    }
    return array_keys($out);
}

$sqlSelect = "SELECT vod_id, vod_actor, vod_class, vod_tag, vod_director FROM `{$prefix}vod` WHERE vod_status=1";
if ($limit > 0) {
    $sqlSelect .= " LIMIT $limit";
} else {
    $sqlSelect .= " ORDER BY vod_id";
}

$lastId = 0;
$batch = 2000;
$rowsDone = 0;
$actorPairs = 0;
$tagPairs = 0;
$dirPairs = 0;
$t0 = microtime(true);

while (true) {
    if ($limit > 0) {
        $stmt = $pdo->query($sqlSelect);
    } else {
        $stmt = $pdo->prepare("SELECT vod_id, vod_actor, vod_class, vod_tag, vod_director FROM `{$prefix}vod`
            WHERE vod_status=1 AND vod_id > ? ORDER BY vod_id LIMIT $batch");
        $stmt->execute([$lastId]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        break;
    }

    $aVals = [];
    $tVals = [];
    $dVals = [];
    foreach ($rows as $r) {
        $vid = (int)$r['vod_id'];
        $lastId = $vid;
        $rowsDone++;

        foreach (splitNames($r['vod_actor'], $actorBlack, 30) as $name) {
            $aVals[] = '(' . $vid . ',' . $pdo->quote($name) . ')';
        }
        foreach (splitNames($r['vod_director'], $actorBlack, 30) as $dname) {
            $dVals[] = '(' . $vid . ',' . $pdo->quote($dname) . ')';
        }
        $tags = splitNames($r['vod_tag'], $tagBlack, 20);
        if (!$tags) {
            $tags = splitNames($r['vod_class'], $tagBlack, 20);
        }
        foreach ($tags as $t) {
            $tVals[] = '(' . $vid . ',' . $pdo->quote($t) . ')';
        }
    }

    foreach (array_chunk($aVals, 800) as $chunk) {
        $pdo->exec('INSERT IGNORE INTO nf_vod_actor (vod_id,actor) VALUES ' . implode(',', $chunk));
        $actorPairs += count($chunk);
    }
    foreach (array_chunk($tVals, 800) as $chunk) {
        $pdo->exec('INSERT IGNORE INTO nf_vod_tag (vod_id,tag) VALUES ' . implode(',', $chunk));
        $tagPairs += count($chunk);
    }
    foreach (array_chunk($dVals, 800) as $chunk) {
        $pdo->exec('INSERT IGNORE INTO nf_vod_director (vod_id,director) VALUES ' . implode(',', $chunk));
        $dirPairs += count($chunk);
    }

    if ($rowsDone % 40000 === 0) {
        out("progress vod=$rowsDone actor_pairs=$actorPairs tag_pairs=$tagPairs");
    }
    if ($limit > 0) {
        break;
    }
}
out("scan done vod=$rowsDone actor_pairs=$actorPairs tag_pairs=$tagPairs dir_pairs=$dirPairs in " . round(microtime(true) - $t0, 1) . 's');

/* ---------- 4. 汇总计数（保留已有拼音 slug，否则重建会清空 slug） ---------- */
foreach (['nf_actor' => ['actor', 'nf_vod_actor'], 'nf_tag' => ['tag', 'nf_vod_tag'], 'nf_director' => ['director', 'nf_vod_director']] as $agg => $src) {
    list($col, $inv) = $src;
    $stage = $agg . '_stage';
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$stage` (
        `name` varchar(80) NOT NULL,
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("TRUNCATE TABLE `$stage`");
    $pdo->exec("INSERT INTO `$stage` (name,vod_count) SELECT `$col`, COUNT(*) FROM `$inv` GROUP BY `$col`");
    // 1) 已有演员/标签只更新计数，slug 原样保留
    $pdo->exec("UPDATE `$agg` a JOIN `$stage` s ON a.name=s.name SET a.vod_count=s.vod_count");
    // 2) 本次已不存在的行删掉
    $pdo->exec("DELETE a FROM `$agg` a LEFT JOIN `$stage` s ON a.name=s.name WHERE s.name IS NULL");
    // 3) 新增的行插入（slug 留空，由 tools/fill-slugs.py 补齐）
    $pdo->exec("INSERT INTO `$agg` (name,vod_count,slug)
        SELECT s.name,s.vod_count,'' FROM `$stage` s
        LEFT JOIN `$agg` a ON a.name=s.name WHERE a.name IS NULL");
    $pdo->exec("TRUNCATE TABLE `$stage`");
}
out('aggregated (slug preserved)');

$stat = function ($sql) use ($pdo) {
    return (int)$pdo->query($sql)->fetchColumn();
};
$actors = $stat('SELECT COUNT(*) FROM nf_actor');
$actors10 = $stat('SELECT COUNT(*) FROM nf_actor WHERE vod_count>=10');
$tags = $stat('SELECT COUNT(*) FROM nf_tag');
$tags100 = $stat('SELECT COUNT(*) FROM nf_tag WHERE vod_count>=100');
$dirs = $stat('SELECT COUNT(*) FROM nf_director');
$dirs10 = $stat('SELECT COUNT(*) FROM nf_director WHERE vod_count>=10');
out("actors=$actors (>=10: $actors10)  tags=$tags (>=100: $tags100)  directors=$dirs (>=10: $dirs10)");

echo "\nTop actors:\n";
foreach ($pdo->query('SELECT name,vod_count FROM nf_actor ORDER BY vod_count DESC LIMIT 10') as $r) {
    echo "  {$r['name']}\t{$r['vod_count']}\n";
}
echo "\nTop tags:\n";
foreach ($pdo->query('SELECT name,vod_count FROM nf_tag ORDER BY vod_count DESC LIMIT 15') as $r) {
    echo "  {$r['name']}\t{$r['vod_count']}\n";
}
out('all done');
