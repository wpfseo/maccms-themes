<?php
/**
 * P2 数据构建：组合标签（nf_combo）+ 系列片单（nf_series）
 *
 * 用法：php tools/build-p2.php /www/wwwroot/www.ljly.net
 *
 * 说明：
 *   - nf_combo  双标签共现（>= COMBO_MIN 部）全量重建；slug 直接取 nf_tag.slug，稳定
 *   - nf_series 同名多部（非电影大类，>= SERIES_MIN 部）用暂存表比对重建，
 *               已有 slug 原样保留，避免像早期 nf_actor 那样被清空导致 URL 全变
 */
if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}
error_reporting(E_ALL & ~E_NOTICE);
$root = isset($argv[1]) ? rtrim($argv[1], '/') : dirname(__DIR__);

$cfg    = include $root . '/application/database.php';
$host   = $cfg['hostname'] ?? '127.0.0.1';
$port   = $cfg['hostport'] ?: 3306;
$dbname = $cfg['database'] ?? '';
$prefix = $cfg['prefix'] ?? 'mac_';

define('COMBO_MIN', 300);
define('SERIES_MIN', 3);

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Exception $e) {
    exit('DB connect failed: ' . $e->getMessage() . "\n");
}
function out($s) { echo '[' . date('H:i:s') . '] ' . $s . "\n"; }

/* ---------- 1. 建表 ---------- */
$ddl = [
    "CREATE TABLE IF NOT EXISTS `nf_combo` (
        `a` varchar(40) NOT NULL,
        `b` varchar(40) NOT NULL,
        `a_slug` varchar(64) NOT NULL DEFAULT '',
        `b_slug` varchar(64) NOT NULL DEFAULT '',
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`a`,`b`),
        KEY `idx_count` (`vod_count`),
        KEY `idx_aslug` (`a_slug`),
        KEY `idx_bslug` (`b_slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_series` (
        `name` varchar(120) NOT NULL,
        `type_id` int unsigned NOT NULL DEFAULT 0,
        `slug` varchar(64) NOT NULL DEFAULT '',
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`name`),
        KEY `idx_slug` (`slug`),
        KEY `idx_count` (`vod_count`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `nf_series_stage` (
        `name` varchar(120) NOT NULL,
        `type_id` int unsigned NOT NULL DEFAULT 0,
        `vod_count` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}
out('tables ready');

/* ---------- 2. 组合标签 ---------- */
$t0 = microtime(true);
$pdo->exec('TRUNCATE TABLE nf_combo');
$sql = "INSERT INTO nf_combo (a,b,a_slug,b_slug,vod_count)
        SELECT x.tag, y.tag, f1.slug, f2.slug, COUNT(*) AS c
        FROM nf_vod_tag x
        INNER JOIN nf_vod_tag y ON y.vod_id = x.vod_id AND x.tag < y.tag
        INNER JOIN nf_tag f1 ON f1.name = x.tag AND f1.slug <> ''
        INNER JOIN nf_tag f2 ON f2.name = y.tag AND f2.slug <> ''
        GROUP BY x.tag, y.tag
        HAVING c >= " . COMBO_MIN;
$pdo->exec($sql);
$combo = $pdo->query('SELECT COUNT(*) FROM nf_combo')->fetchColumn();
out("combo pairs=$combo (>= " . COMBO_MIN . ') in ' . round(microtime(true) - $t0, 1) . 's');

/* ---------- 3. 系列片单（暂存表比对，slug 保留） ---------- */
$t0 = microtime(true);
$pdo->exec('TRUNCATE TABLE nf_series_stage');
$pdo->exec("INSERT INTO nf_series_stage (name,type_id,vod_count)
            SELECT LEFT(vod_name,120) AS nm, MIN(type_id) AS tid, COUNT(*) AS n
            FROM {$prefix}vod
            WHERE vod_status=1 AND type_id_1 IN (1,3,4,5) AND TRIM(vod_name)<>''
            GROUP BY nm HAVING n >= " . SERIES_MIN);

$total = (int)$pdo->query('SELECT COUNT(*) FROM nf_series_stage')->fetchColumn();
$pdo->exec('DELETE s FROM nf_series s LEFT JOIN nf_series_stage t ON t.name = s.name WHERE t.name IS NULL');
$upd = $pdo->exec('UPDATE nf_series s INNER JOIN nf_series_stage t ON t.name = s.name SET s.type_id = t.type_id, s.vod_count = t.vod_count');
$ins = $pdo->exec("INSERT INTO nf_series (name,type_id,slug,vod_count)
                   SELECT t.name, t.type_id, '', t.vod_count FROM nf_series_stage t
                   LEFT JOIN nf_series s ON s.name = t.name WHERE s.name IS NULL");
out("series total=$total updated=$upd inserted=$ins in " . round(microtime(true) - $t0, 1) . 's');

$slugged = (int)$pdo->query("SELECT COUNT(*) FROM nf_series WHERE slug <> ''")->fetchColumn();
out("series slug filled: $slugged / $total");
