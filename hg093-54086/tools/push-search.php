<?php
/**
 * 搜索引擎主动推送（通用 maccms 版，适用于 www.54086.com）
 *
 * 用法：
 *   php tools/push-search.php <站点根目录> --since=3 --limit=200 --engine=baidu
 *   php tools/push-search.php <站点根目录> --engine=baidu --dry      # 只预览候选，不推送
 *   php tools/push-search.php <站点根目录> --engine=both            # 百度 + IndexNow
 *   --full 忽略时间窗口（谨慎，用于全量）
 *
 * 说明：
 *   - 候选 = 近 N 天入库的新片详情页（按 vod_time 倒序）+ 全部分类页
 *   - 百度普通收录有日配额（新站常见 10 条/天），按 push.conf.php 的 baidu_daily 截取
 *   - 去重表 push_log 主键 (url, engine)，30 天内不重复推送
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Shanghai');

function say($m)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL;
}

$root = rtrim($argv[1] ?? dirname(__DIR__), '/');
if (!is_file($root . '/application/database.php')) {
    fwrite(STDERR, "找不到 {$root}/application/database.php，请把站点根目录作为第一个参数传入\n");
    exit(2);
}

/* ---------------- 参数 ---------------- */
$args = [];
foreach (array_slice($argv, 2) as $a) {
    if (preg_match('/^--([a-z0-9_]+)(?:=(.*))?$/i', $a, $m)) {
        $args[strtolower($m[1])] = $m[2] ?? '1';
    }
}

/* ---------------- 配置 ---------------- */
$confFile = $root . '/tools/push.conf.php';
$pushConf = is_file($confFile) ? (include $confFile) : [];
if (!is_array($pushConf)) {
    $pushConf = [];
}
$site = rtrim((string)($pushConf['site'] ?? ''), '/');
if ($site === '') {
    fwrite(STDERR, "tools/push.conf.php 缺少 site\n");
    exit(2);
}

/* ---------------- 数据库（读站点自己的 database.php） ---------------- */
$dbSrc = (string)file_get_contents($root . '/application/database.php');
$pick = function ($k) use ($dbSrc) {
    return preg_match("/'" . $k . "'\s*=>\s*'([^']*)'/", $dbSrc, $m) ? $m[1] : '';
};
$db = new PDO(
    'mysql:host=' . ($pick('hostname') ?: '127.0.0.1') . ';port=' . ($pick('hostport') ?: '3306')
    . ';dbname=' . $pick('database') . ';charset=utf8mb4',
    $pick('username'),
    $pick('password'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$db->exec("CREATE TABLE IF NOT EXISTS push_log (
    url varchar(191) NOT NULL DEFAULT '',
    engine varchar(16) NOT NULL DEFAULT '',
    pushed_at int(11) NOT NULL DEFAULT 0,
    http_code smallint(6) NOT NULL DEFAULT 0,
    note varchar(255) NOT NULL DEFAULT '',
    PRIMARY KEY (url, engine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ---------------- 候选 URL ---------------- */
$since = max(1, (int)($args['since'] ?? 3));
$limit = max(1, (int)($args['limit'] ?? 500));
$full = isset($args['full']);

$urls = [];
$sql = 'SELECT vod_id FROM mac_vod WHERE vod_status=1';
if (!$full) {
    $sql .= ' AND vod_time > ' . (time() - $since * 86400);
}
$sql .= ' ORDER BY vod_time DESC LIMIT ' . $limit;
foreach ($db->query($sql) as $r) {
    $urls[] = $site . '/voddetail/' . (int)$r['vod_id'] . '.html';
}
foreach ($db->query('SELECT type_id FROM mac_type WHERE type_status=1 ORDER BY type_id') as $r) {
    $urls[] = $site . '/vodtype/' . (int)$r['type_id'] . '.html';
}
$urls = array_values(array_unique($urls));
say('待推送候选 ' . count($urls) . ' 条（since=' . $since . 'd, full=' . ($full ? 1 : 0) . '）');

/* ---------------- 去重 ---------------- */
$engine = strtolower((string)($args['engine'] ?? ''));
$dry = isset($args['dry']) || $engine === '';
$engines = $engine === 'both' ? ['baidu', 'indexnow'] : ($engine === '' ? [] : [$engine]);

$fresh = [];
$stDup = $db->prepare('SELECT pushed_at FROM push_log WHERE url=? AND engine=? LIMIT 1');
foreach ($urls as $u) {
    $seen = false;
    foreach ($engines ?: ['any'] as $e) {
        $stDup->execute([$u, $e]);
        $row = $stDup->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['pushed_at'] > time() - 30 * 86400) {
            $seen = true;
            break;
        }
    }
    if (!$seen) {
        $fresh[] = $u;
    }
}
say('去重后待推送 ' . count($fresh) . ' 条');

if ($dry) {
    foreach (array_slice($fresh, 0, 10) as $u) {
        say('  DRY ' . $u);
    }
    say('仅预览；执行推送请加 --engine=baidu 或 --engine=indexnow');
    exit(0);
}

/* ---------------- HTTP ---------------- */
function httpPost($url, $body, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 40,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return $resp === false ? [0, 'curl: ' . $err] : [$code, (string)$resp];
}

$okCount = 0;
$failCount = 0;
$stLog = $db->prepare('INSERT INTO push_log(url,engine,pushed_at,http_code,note) VALUES(?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE pushed_at=VALUES(pushed_at),http_code=VALUES(http_code),note=VALUES(note)');

foreach ($engines as $e) {
    if ($e === 'indexnow') {
        $key = trim((string)($pushConf['indexnow_key'] ?? ''));
        if ($key === '') {
            say('IndexNow key 未配置，跳过');
            continue;
        }
        $keyFile = $root . '/' . $key . '.txt';
        if (!is_file($keyFile) || trim((string)@file_get_contents($keyFile)) !== $key) {
            @file_put_contents($keyFile, $key);
            @chmod($keyFile, 0644);
        }
        foreach (array_chunk($fresh, 10000) as $batch) {
            $payload = json_encode([
                'host' => parse_url($site, PHP_URL_HOST),
                'key' => $key,
                'keyLocation' => $site . '/' . $key . '.txt',
                'urlList' => array_values($batch),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            list($code, $resp) = httpPost('https://api.indexnow.org/indexnow', $payload, ['Content-Type: application/json; charset=utf-8']);
            $ok = $code >= 200 && $code < 300;
            say('IndexNow ' . count($batch) . ' 条 -> HTTP ' . $code . ' ' . substr($resp, 0, 120));
            $ok ? $okCount += count($batch) : $failCount += count($batch);
            foreach ($batch as $u) {
                $stLog->execute([$u, 'indexnow', $ok ? time() : 0, $code, $ok ? '' : substr($resp, 0, 200)]);
            }
            sleep(1);
        }
    } elseif ($e === 'baidu') {
        $token = trim((string)($pushConf['baidu_token'] ?? ''));
        if ($token === '') {
            say('百度 token 未配置：在 tools/push.conf.php 写入 baidu_token');
            continue;
        }
        $quota = max(1, (int)($pushConf['baidu_daily'] ?? 10));
        // --max 可临时压小本批条数（用于当日剩余额度不足时避免整批 over quota）
        $cap = (int)($args['max'] ?? 0);
        $batch = array_slice($fresh, 0, $cap > 0 ? min($quota, $cap) : $quota);
        if (!$batch) {
            say('百度：无可推送 URL（均已推送过）');
            continue;
        }
        // site 必须传原始域名，urlencode 后百度会报 400 site init fail
        list($code, $resp) = httpPost(
            'http://data.zz.baidu.com/urls?site=' . $site . '&token=' . rawurlencode($token),
            implode("\n", $batch),
            ['Content-Type: text/plain']
        );
        $json = json_decode($resp, true);
        $succ = is_array($json) ? (int)($json['success'] ?? 0) : 0;
        $remain = is_array($json) && isset($json['remain']) ? (int)$json['remain'] : null;
        $err = is_array($json) && isset($json['message']) ? (string)$json['message'] : '';
        say('百度推送 ' . count($batch) . ' 条 -> HTTP ' . $code . ' success=' . $succ
            . ($remain !== null ? ' remain=' . $remain : '') . ($err !== '' ? ' msg=' . $err : ''));
        $okCount += $succ;
        $failCount += max(0, count($batch) - $succ);
        foreach ($batch as $i => $u) {
            $okOne = $i < $succ;
            $stLog->execute([$u, 'baidu', $okOne ? time() : 0, $code,
                $okOne ? ('remain=' . (string)$remain) : substr($resp, 0, 200)]);
        }
    } else {
        say('未知 engine：' . $e);
    }
}

say('推送完成：成功 ' . $okCount . ' 条，失败 ' . $failCount . ' 条');
exit($failCount > 0 ? 1 : 0);
