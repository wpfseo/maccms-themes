<?php
$__root = dirname(__DIR__, 4);
$dbConfig = @include $__root . '/application/database.php';
if (!is_array($dbConfig)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'msg' => 'config missing']);
    exit;
}
$dsn = "mysql:host={$dbConfig['hostname']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'msg' => 'db error']);
    exit;
}
$vodDetailOpen = 1;
$themeCacheFile = $__root . '/template/hl093/admin/theme_cache.php';
if (is_file($themeCacheFile)) {
    $themeData = file_get_contents($themeCacheFile);
    if (preg_match('/"vod_detail_open"\s*:\s*"?(\d+)"?/', $themeData, $m)) {
        $vodDetailOpen = intval($m[1]);
    }
}
$useRoute = false;
$detailRule = '';
$playRule = '';
$routeSuffix = 'html';
$suffixHide = false;
$maccmsFile = $__root . '/application/extra/maccms.php';
if (is_file($maccmsFile)) {
    $maccms = @include $maccmsFile;
    if (is_array($maccms)) {
        if (isset($maccms['app']['suffix']) && $maccms['app']['suffix'] !== '') {
            $routeSuffix = $maccms['app']['suffix'];
        }
        if (isset($maccms['rewrite']) && is_array($maccms['rewrite'])) {
            $rw = $maccms['rewrite'];
            if (isset($rw['route_status']) && $rw['route_status'] == 1) {
                $useRoute = true;
            }
            if (isset($rw['suffix_hide']) && $rw['suffix_hide'] == 1) {
                $suffixHide = true;
            }
            if (isset($rw['suffix']) && $rw['suffix'] !== '') {
                $routeSuffix = $rw['suffix'];
            }
            if ($useRoute && isset($rw['route']) && is_string($rw['route'])) {
                $lines = explode("\n", $rw['route']);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $parts = preg_split('/\s*=>\s*/', $line);
                    if (count($parts) < 2) continue;
                    $expr = trim($parts[0]);
                    $addr = trim($parts[1]);
                    if ($addr === 'vod/detail' && $detailRule === '') $detailRule = $expr;
                    if ($addr === 'vod/play' && $playRule === '') $playRule = $expr;
                }
            }
        }
    }
}
if ($useRoute && ($detailRule === '' || $playRule === '')) {
    $routeFile = $__root . '/application/route.php';
    if (is_file($routeFile)) {
        $routes = @include $routeFile;
        if (is_array($routes)) {
            foreach ($routes as $expr => $info) {
                if ($expr === '__pattern__' || $expr === '__alias__' || $expr === '__domain__') continue;
                $addr = is_array($info) ? (isset($info[0]) ? $info[0] : '') : $info;
                if ($addr === 'vod/detail' && $detailRule === '') $detailRule = $expr;
                if ($addr === 'vod/play' && $playRule === '') $playRule = $expr;
            }
        }
    }
}
if ($suffixHide) $routeSuffix = '';
function buildRouteUrl($expr, $params) {
    $url = preg_replace('/<([a-z]+)\?>/i', '', $expr);
    foreach ($params as $k => $v) {
        $url = str_replace('<' . $k . '>', $v, $url);
    }
    $url = preg_replace('/<[a-z]+>/i', '', $url);
    $url = '/' . ltrim($url, '/');
    return $url;
}
$table = $dbConfig['prefix'] . 'vod';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
if ($action == 'count') {
    $stmt = $pdo->query("SELECT vod_weekday, COUNT(*) as cnt FROM {$table} WHERE type_id > 0 AND vod_weekday IN ('一','二','三','四','五','六','日') GROUP BY vod_weekday");
    $counts = [];
    $weekMap = ['一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '日' => 7];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($weekMap[$row['vod_weekday']])) {
            $counts[$weekMap[$row['vod_weekday']]] = $row['cnt'];
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($counts);
    exit;
}
$day = isset($_GET['day']) ? intval($_GET['day']) : 1;
$weekMap = [1 => '一', 2 => '二', 3 => '三', 4 => '四', 5 => '五', 6 => '六', 7 => '日'];
$dayName = isset($weekMap[$day]) ? $weekMap[$day] : '';
if (!$dayName) exit;
$stmt = $pdo->prepare("SELECT vod_id, vod_name, vod_pic, vod_serial, vod_remarks, type_id_1 FROM {$table} WHERE vod_weekday = ? AND type_id > 0 ORDER BY vod_time DESC LIMIT 30");
$stmt->execute([$dayName]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($list as $vo) {
    $status = '';
    if (!empty($vo['vod_remarks'])) {
        $status = $vo['vod_remarks'];
    } elseif ($vo['vod_serial'] > 0) {
        $status = '第' . $vo['vod_serial'] . '集';
    } else {
        $status = '已完结';
    }
    if ($vodDetailOpen == 1) {
        if ($useRoute && $detailRule !== '') {
            $url = buildRouteUrl($detailRule, ['id' => $vo['vod_id']]);
            if ($routeSuffix !== '') $url .= '.' . $routeSuffix;
        } else {
            $url = '/index.php/vod/detail/id/' . $vo['vod_id'] . '.html';
        }
    } else {
        if ($useRoute && $playRule !== '') {
            $url = buildRouteUrl($playRule, ['id' => $vo['vod_id'], 'sid' => 1, 'nid' => 1]);
            if ($routeSuffix !== '') $url .= '.' . $routeSuffix;
        } else {
            $url = '/index.php/vod/play/id/' . $vo['vod_id'] . '/sid/1/nid/1.html';
        }
    }
    echo '<a href="' . $url . '" class="calendar-movie-item" data-category="' . $vo['type_id_1'] . '" style="display:flex;" title="' . htmlspecialchars($vo['vod_name']) . '">';
    $pic = $vo['vod_pic'];
    if (strpos($pic, '/') !== 0) $pic = '/' . $pic;
    echo '<div class="poster"><img loading="lazy" decoding="async" src="' . htmlspecialchars($pic) . '" alt="' . htmlspecialchars($vo['vod_name']) . '"></div>';
    echo '<div class="info"><h4>' . htmlspecialchars($vo['vod_name']) . '</h4><p>' . htmlspecialchars($status) . '</p></div>';
    echo '</a>';
}
