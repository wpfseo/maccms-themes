<?php
/**
 * 空字段补全（地区 / 语言 / 年份）
 *
 * 设计原则：只填空值、只按「同类影片里占绝对多数（≥95%）」的取值补，绝不复写已有值。
 *
 * 用法：
 *   php tools/fill-metadata.php <root> --dry        # 只统计与抽样，不改库（默认）
 *   php tools/fill-metadata.php <root> --apply      # 实际写入，先落备份再分批 UPDATE
 *
 * 备份：/root/backup/ljly_fill_YYYYmmdd_HHMMSS.tsv（vod_id / 原 area / 原 lang / 原 year）
 * 日志：/www/wwwlogs/ljly_fill.log
 */

$root = isset($argv[1]) && $argv[1][0] !== '-' ? rtrim($argv[1], '/\\') : dirname(__DIR__);
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)$/i', $a, $m)) {
        $args[strtolower($m[1])] = true;
    }
}
$apply = isset($args['apply']);
$log = '/www/wwwlogs/ljly_fill.log';

function say($m)
{
    global $log;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $m;
    echo $line, "\n";
    @file_put_contents($log, $line . "\n", FILE_APPEND);
}

$config = @include $root . '/application/database.php';
if (!$config) {
    fwrite(STDERR, "database.php not found\n");
    exit(1);
}
$db = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['hostname'], $config['hostport'] ?: '3306', $config['database']),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* 分类 -> 默认地区/语言（依据：该分类有值样本中占比 ≥95% 的取值）
   影视解说(37)、剧情片(22)、动作片(13)、喜剧片(14) 主导值占比仅 27%~38%，不参与推断 */
$areaRules = [
    6 => '中国大陆', 9 => '中国大陆', 12 => '中国大陆', 43 => '中国大陆',
    72 => '中国大陆', 67 => '中国大陆', 68 => '中国大陆', 18 => '日本',
];
$langRules = [
    6 => '普通话', 9 => '普通话', 12 => '普通话', 43 => '普通话',
    72 => '普通话', 67 => '普通话', 68 => '普通话', 18 => '日语',
];

$plan = [];

/* ---------- 地区 ---------- */
foreach ($areaRules as $tid => $val) {
    $st = $db->prepare("SELECT COUNT(*) FROM mac_vod WHERE vod_status=1 AND type_id=? AND (vod_area='' OR vod_area IS NULL)");
    $st->execute([$tid]);
    $n = (int)$st->fetchColumn();
    if ($n > 0) {
        $plan[] = ['field' => 'vod_area', 'type' => $tid, 'value' => $val, 'count' => $n];
    }
}

/* ---------- 语言 ---------- */
foreach ($langRules as $tid => $val) {
    $st = $db->prepare("SELECT COUNT(*) FROM mac_vod WHERE vod_status=1 AND type_id=? AND (vod_lang='' OR vod_lang IS NULL)");
    $st->execute([$tid]);
    $n = (int)$st->fetchColumn();
    if ($n > 0) {
        $plan[] = ['field' => 'vod_lang', 'type' => $tid, 'value' => $val, 'count' => $n];
    }
}

/* ---------- 年份（从片名提取，三种可靠形态） ---------- */
$yearSql = [
    // 1) 8 位日期 YYYYMMDD（体育赛事）：取前 4 位
    'date8' => ["vod_name REGEXP '(19[5-9][0-9]|20[0-2][0-9])(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])'", 'left'],
    // 2) 括号年份 (2018) / （2018）
    'paren' => ["vod_name REGEXP '[(（](19[5-9][0-9]|20[0-2][0-9])[)）]'", 'paren'],
    // 3) 片名中的裸年份 1950-2026（如「猫和老鼠2014」）
    'bare' => ["vod_name REGEXP '(19[5-9][0-9]|20[0-2][0-9])'
                AND vod_name NOT REGEXP '(19[5-9][0-9]|20[0-2][0-9])(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])'
                AND vod_name NOT REGEXP '[(（](19[5-9][0-9]|20[0-2][0-9])[)）]'", 'bare'],
];
foreach ($yearSql as $k => $one) {
    $sql = "SELECT COUNT(*) FROM mac_vod WHERE vod_status=1 AND (vod_year='' OR vod_year='0') AND " . $one[0];
    $n = (int)$db->query($sql)->fetchColumn();
    if ($n > 0) {
        $plan[] = ['field' => 'vod_year', 'type' => 0, 'value' => $k, 'count' => $n];
    }
}

say('==== 补全计划（' . ($apply ? 'APPLY' : 'DRY') . '）====');
$total = 0;
foreach ($plan as $p) {
    $total += $p['count'];
    say(sprintf('  %-9s 分类 %-3s 填充值 %-8s 预计影响 %s 条', $p['field'], $p['type'] ?: '全部', $p['value'], number_format($p['count'])));
}
say('合计预计写入 ' . number_format($total) . ' 个字段值（每行可能同时补地区+语言+年份）');

/* ---------- 抽样 ---------- */
say('---- 抽样（各规则 5 条）----');
foreach ($plan as $p) {
    if ($p['field'] === 'vod_area') {
        $sql = "SELECT vod_id,vod_name FROM mac_vod WHERE vod_status=1 AND type_id=" . (int)$p['type']
            . " AND (vod_area='' OR vod_area IS NULL) LIMIT 5";
    } elseif ($p['field'] === 'vod_lang') {
        $sql = "SELECT vod_id,vod_name FROM mac_vod WHERE vod_status=1 AND type_id=" . (int)$p['type']
            . " AND (vod_lang='' OR vod_lang IS NULL) LIMIT 5";
    } else {
        $idx = $p['value'];
        $sql = "SELECT vod_id,vod_name FROM mac_vod WHERE vod_status=1 AND (vod_year='' OR vod_year='0') AND " . $yearSql[$idx][0] . " LIMIT 5";
    }
    foreach ($db->query($sql) as $r) {
        say('  ' . $p['field'] . '[' . $p['value'] . '] ' . $r['vod_id'] . '  ' . mb_substr($r['vod_name'], 0, 40, 'UTF-8'));
    }
}

if (!$apply) {
    say('DRY 模式未写入任何数据。确认后执行：php tools/fill-metadata.php <root> --apply');
    exit(0);
}

/* ---------- 备份 ---------- */
$dir = '/root/backup';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$bak = $dir . '/ljly_fill_' . date('Ymd_His') . '.tsv';
$fh = fopen($bak, 'w');
if (!$fh) {
    say('备份文件创建失败，终止');
    exit(1);
}
$selIds = [];
foreach ($plan as $p) {
    $where = $p['field'] === 'vod_year'
        ? "vod_status=1 AND (vod_year='' OR vod_year='0') AND " . $yearSql[$p['value']][0]
        : "vod_status=1 AND type_id=" . (int)$p['type'] . " AND (" . $p['field'] . "='' OR " . $p['field'] . " IS NULL)";
    foreach ($db->query('SELECT vod_id,vod_area,vod_lang,vod_year FROM mac_vod WHERE ' . $where) as $r) {
        if (isset($selIds[$r['vod_id']])) {
            continue;
        }
        $selIds[$r['vod_id']] = 1;
        fwrite($fh, implode("\t", [$r['vod_id'], $r['vod_area'], $r['vod_lang'], $r['vod_year']]) . "\n");
    }
}
fclose($fh);
say('备份已写入 ' . $bak . '（' . number_format(count($selIds)) . ' 行）');

/* ---------- 分批写入 ---------- */
$db->exec('SET SESSION sql_mode=\'\'');
foreach ($plan as $p) {
    if ($p['field'] === 'vod_year') {
        continue; // 年份逐行处理，见下
    }
    $sql = "UPDATE mac_vod SET " . $p['field'] . "=? WHERE vod_status=1 AND type_id=? AND (" . $p['field'] . "='' OR " . $p['field'] . " IS NULL) LIMIT 5000";
    $st = $db->prepare($sql);
    $done = 0;
    while (true) {
        $st->execute([$p['value'], $p['type']]);
        $n = $st->rowCount();
        if ($n <= 0) {
            break;
        }
        $done += $n;
    }
    say(sprintf('  写入 %s = %s（分类 %s）：%s 条', $p['field'], $p['value'], $p['type'], number_format($done)));
}

/* 年份：逐行提取后批量更新 */
$stUp = $db->prepare('UPDATE mac_vod SET vod_year=? WHERE vod_id=? AND (vod_year=\'\' OR vod_year=\'0\')');
foreach (['date8', 'paren', 'bare'] as $rule) {
    $sql = "SELECT vod_id,vod_name FROM mac_vod WHERE vod_status=1 AND (vod_year='' OR vod_year='0') AND " . $yearSql[$rule][0];
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $done = 0;
    foreach ($rows as $r) {
        $y = '';
        if ($rule === 'date8') {
            if (preg_match('/(19[5-9]\d|20[0-2]\d)(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])/', $r['vod_name'], $m)) {
                $y = $m[1];
            }
        } elseif ($rule === 'paren') {
            if (preg_match('/[(（](19[5-9]\d|20[0-2]\d)[)）]/', $r['vod_name'], $m)) {
                $y = $m[1];
            }
        } else {
            $cands = [];
            if (preg_match_all('/(?<!\d)(19[5-9]\d|20[0-2]\d)(?!\d)/', $r['vod_name'], $m)) {
                $cands = $m[1];
            }
            if ($cands) {
                $y = end($cands);
            }
        }
        if ($y !== '' && (int)$y >= 1950 && (int)$y <= (int)date('Y') + 1) {
            $stUp->execute([$y, $r['vod_id']]);
            $done += $stUp->rowCount();
        }
    }
    say('  写入 vod_year（' . $rule . '）：' . number_format($done) . ' 条');
}

say('完成。建议随后执行 refresh.sh 重建聚合页与 sitemap。');
