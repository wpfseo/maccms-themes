<?php
/**
 * 搜索引擎主动推送（百度 / IndexNow）
 *
 * 用法：
 *   php tools/push-search.php --since=7 --limit=500 --dry      # 预览将要推送的 URL（默认）
 *   php tools/push-search.php --since=7 --engine=indexnow      # 推送 IndexNow（必应/Yandex 等）
 *   php tools/push-search.php --since=7 --engine=baidu         # 推送百度（需配置 token）
 *   php tools/push-search.php --engine=both --full             # 全量聚合页 + 近期新片
 *
 * 配置：可选 tools/push.conf.php
 *   return ['baidu_token' => 'xxxx', 'indexnow_key' => '', 'site' => 'https://www.ljly.net'];
 *   indexnow_key 留空时自动生成，并写入站根 {key}.txt（IndexNow 校验文件）
 *
 * 记录：nf_push_log(url, engine, pushed_at, http_code, note) 做去重，30 天内不重复推同一 URL
 * 日志：/www/wwwlogs/ljly_push.log
 */

$root = isset($argv[1]) && $argv[1][0] !== '-' ? rtrim($argv[1], '/\\') : dirname(__DIR__);
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/i', $a, $m)) {
        $args[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

$config = @include $root . '/application/database.php';
if (!$config) {
    fwrite(STDERR, "database.php not found\n");
    exit(1);
}
$pushConf = [];
if (is_file($root . '/tools/push.conf.php')) {
    $pushConf = (array)@include $root . '/tools/push.conf.php';
}
$site = rtrim($pushConf['site'] ?? 'https://www.ljly.net', '/');
$logFile = '/www/wwwlogs/ljly_push.log';

$db = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['hostname'], $config['hostport'] ?: '3306', $config['database']),
    $config['username'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$db->exec("CREATE TABLE IF NOT EXISTS nf_push_log (
    url VARCHAR(191) NOT NULL PRIMARY KEY,
    engine VARCHAR(16) NOT NULL DEFAULT '',
    pushed_at INT NOT NULL DEFAULT 0,
    http_code SMALLINT NOT NULL DEFAULT 0,
    note VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");

function say($msg)
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line, "\n";
    @file_put_contents($logFile, $line . "\n", FILE_APPEND);
}

function httpPost($url, $body, $headers = [], $timeout = 20)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $resp === false ? $err : (string)$resp];
}

/* ---------------- 生成待推送 URL ---------------- */
$since = max(1, (int)($args['since'] ?? 7));
$limit = max(1, (int)($args['limit'] ?? 2000));
$full = isset($args['full']);
$from = time() - $since * 86400;

$urls = [];

if ($full) {
    // 聚合页：库首页 + 演员/导演/题材/组合/年份/系列/筛选/频道
    foreach (['/cast.html', '/director.html', '/genre.html', '/combo.html', '/year.html', '/series.html',
              '/duanju.html', '/sports.html', '/jieshuo.html', '/nightrank.html', '/nightbrowse.html'] as $u) {
        $urls[] = $site . $u;
    }
    $maps = [
        ['SELECT slug FROM nf_actor WHERE slug<>\'\' AND vod_count>=10', '/cast/'],
        ['SELECT slug FROM nf_director WHERE slug<>\'\' AND vod_count>=10', '/director/'],
        ['SELECT slug FROM nf_tag WHERE slug<>\'\' AND vod_count>=100', '/genre/'],
        ['SELECT slug FROM nf_series WHERE slug<>\'\'', '/series/'],
    ];
    foreach ($maps as $m) {
        try {
            $st = $db->query($m[0]);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $urls[] = $site . $m[1] . rawurlencode($r['slug']) . '.html';
            }
        } catch (Exception $e) {
            say('跳过 ' . $m[1] . '：' . $e->getMessage());
        }
    }
    try {
        $st = $db->query('SELECT year FROM nf_year WHERE total>=50');
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $urls[] = $site . '/year/' . rawurlencode($r['year']) . '.html';
        }
    } catch (Exception $e) {
        // nf_year 不存在时忽略
    }
} else {
    // 近期新片详情页（按收录时间）
    $st = $db->prepare('SELECT vod_id FROM mac_vod WHERE vod_status=1 AND vod_time>=? ORDER BY vod_time DESC LIMIT ' . (int)$limit);
    $st->execute([$from]);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $urls[] = $site . '/voddetail/' . (int)$r['vod_id'] . '.html';
    }
}

$urls = array_values(array_unique($urls));
say('待推送候选 ' . count($urls) . ' 条（since=' . $since . 'd, full=' . ($full ? 1 : 0) . '）');

/* ---------------- 去重 ---------------- */
$engine = strtolower((string)($args['engine'] ?? ''));
$dry = isset($args['dry']) || $engine === '';
$engines = $engine === 'both' ? ['baidu', 'indexnow'] : ($engine === '' ? [] : [$engine]);

$fresh = [];
foreach ($urls as $u) {
    $ok = false;
    foreach ($engines ?: ['any'] as $e) {
        $st = $db->prepare('SELECT pushed_at FROM nf_push_log WHERE url=? AND engine=? LIMIT 1');
        $st->execute([$u, $e]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['pushed_at'] > time() - 30 * 86400) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        $fresh[] = $u;
    }
}
say('去重后待推送 ' . count($fresh) . ' 条');

if ($dry) {
    foreach (array_slice($fresh, 0, 10) as $u) {
        say('  DRY ' . $u);
    }
    say('未指定 --engine，仅预览。配置 token / key 后执行 --engine=indexnow 或 --engine=baidu');
    exit(0);
}

/* ---------------- IndexNow ---------------- */
function indexNowKey($root, $pushConf)
{
    $key = trim((string)($pushConf['indexnow_key'] ?? ''));
    if ($key === '') {
        $key = bin2hex(random_bytes(16));
        $f = $root . '/tools/push.conf.php';
        $body = "<?php\n// 搜索引擎推送配置（自动生成，可手工修改）\nreturn " . var_export([
            'baidu_token' => (string)($pushConf['baidu_token'] ?? ''),
            'indexnow_key' => $key,
            'site' => (string)($pushConf['site'] ?? 'https://www.ljly.net'),
        ], true) . ";\n";
        @file_put_contents($f, $body);
        say('已生成 IndexNow key 并写入 tools/push.conf.php');
    }
    $verify = $root . '/' . $key . '.txt';
    if (!is_file($verify) || trim((string)@file_get_contents($verify)) !== $key) {
        @file_put_contents($verify, $key);
        say('已写入校验文件 ' . $key . '.txt');
    }
    return $key;
}

$okCount = 0;
$failCount = 0;

foreach ($engines as $e) {
    if ($e === 'indexnow') {
        $key = indexNowKey($root, $pushConf);
        foreach (array_chunk($fresh, 10000) as $batch) {
            $payload = json_encode([
                'host' => parse_url($site, PHP_URL_HOST),
                'key' => $key,
                'keyLocation' => $site . '/' . $key . '.txt',
                'urlList' => array_values($batch),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            list($code, $resp) = httpPost('https://api.indexnow.org/indexnow', $payload, ['Content-Type: application/json; charset=utf-8']);
            $good = in_array($code, [200, 202], true);
            say('IndexNow 批次 ' . count($batch) . ' 条 -> HTTP ' . $code . ' ' . ($good ? 'OK' : substr($resp, 0, 200)));
            $good ? $okCount += count($batch) : $failCount += count($batch);
            $st = $db->prepare('INSERT INTO nf_push_log(url,engine,pushed_at,http_code,note) VALUES(?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE pushed_at=VALUES(pushed_at),http_code=VALUES(http_code),note=VALUES(note)');
            foreach ($batch as $u) {
                $st->execute([$u, 'indexnow', $good ? time() : 0, $code, $good ? '' : substr($resp, 0, 200)]);
            }
            sleep(1);
        }
    } elseif ($e === 'baidu') {
        $token = trim((string)($pushConf['baidu_token'] ?? ''));
        if ($token === '') {
            say('百度 token 未配置：在 tools/push.conf.php 写入 baidu_token（百度搜索资源平台 -> 普通收录 -> API 提交）');
            continue;
        }
        // 百度普通收录按天给配额（新站常见 10 条/天），超出整批会被拒，因此按额度截取
        $quota = max(1, (int)($pushConf['baidu_daily'] ?? 10));
        $batch = array_slice($fresh, 0, $quota);
        if (!$batch) {
            say('百度：无可推送 URL（均已推送过）');
            continue;
        }
        // site 参数必须是原始域名，urlencode 后百度会返回 400 site init fail
        list($code, $resp) = httpPost(
            'http://data.zz.baidu.com/urls?site=' . $site . '&token=' . rawurlencode($token),
            implode("\n", $batch),
            ['Content-Type: text/plain']
        );
        $json = json_decode($resp, true);
        $succ = is_array($json) ? (int)($json['success'] ?? 0) : 0;
        $remain = is_array($json) && isset($json['remain']) ? (int)$json['remain'] : null;
        $err = is_array($json) && isset($json['message']) ? (string)$json['message'] : '';
        $ok = $succ > 0;
        say('百度推送 ' . count($batch) . ' 条 -> HTTP ' . $code . ' success=' . $succ
            . ($remain !== null ? ' remain=' . $remain : '') . ($err !== '' ? ' msg=' . $err : ''));
        if ($ok) {
            $okCount += $succ;
        }
        $failCount += max(0, count($batch) - $succ);
        $st = $db->prepare('INSERT INTO nf_push_log(url,engine,pushed_at,http_code,note) VALUES(?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE pushed_at=VALUES(pushed_at),http_code=VALUES(http_code),note=VALUES(note)');
        foreach ($batch as $i => $u) {
            $okOne = $i < $succ;
            $st->execute([$u, 'baidu', $okOne ? time() : 0, $code,
                $okOne ? ('remain=' . (string)$remain) : substr($resp, 0, 200)]);
        }
    } else {
        say('未知 engine：' . $e);
    }
}

say('推送完成：成功 ' . $okCount . ' 条，失败 ' . $failCount . ' 条');
exit($failCount > 0 ? 1 : 0);
