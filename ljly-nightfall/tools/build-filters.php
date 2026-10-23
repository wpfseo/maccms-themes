<?php
/**
 * 生成筛选页 sitemap（分类 × 地区/语言/年份）
 *
 * 用法：php tools/build-filters.php /www/wwwroot/www.ljly.net
 *
 * 规则：
 *   - 只收结果 >= MIN 部的组合，避免薄页
 *   - URL 全部为拼音 slug（与页面筛选条一致）
 *   - 分页不收录（canonical 指向第 1 页）
 */
$root = $argv[1] ?? '/www/wwwroot/www.ljly.net';
$c = include $root . '/application/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['hostname'], $c['hostport'] ?: '3306', $c['database']),
    $c['username'],
    $c['password']
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$MIN = 50;
$site = rtrim($c['site_url'] ?? 'https://www.ljly.net', '/');
$today = date('Y-m-d');
$terms = include $root . '/application/data/nightfall/terms.php';

/* 分类：只取有作品的子分类（顶级分类的数量=子类之和，页面是子类） */
$types = [];
foreach ($pdo->query('SELECT type_id, type_name, type_pid FROM mac_type WHERE type_status=1 ORDER BY type_id') as $r) {
    $types[(int)$r['type_id']] = $r;
}

/* 一次 GROUP BY 拿到「分类 × 维度值」分布，多值字段在 PHP 端拆分累加 */
function dist($pdo, $field, $split)
{
    $out = [];
    $st = $pdo->query("SELECT type_id, {$field} v, COUNT(*) c FROM mac_vod WHERE vod_status=1 AND {$field}<>'' GROUP BY type_id, {$field}");
    foreach ($st as $r) {
        foreach ($split((string)$r['v']) as $one) {
            $k = (int)$r['type_id'] . '|' . $one;
            $out[$k] = ($out[$k] ?? 0) + (int)$r['c'];
        }
    }
    return $out;
}

$byArea = dist($pdo, 'vod_area', function ($v) {
    return array_filter(array_map('trim', preg_split('/[,，\/、]+/u', $v)), 'strlen');
});
$byLang = dist($pdo, 'vod_lang', function ($v) {
    return array_filter(array_map('trim', preg_split('/[,，\/、]+/u', $v)), 'strlen');
});
$byYear = dist($pdo, 'vod_year', function ($v) {
    $v = trim($v);
    return preg_match('/^(19|20)\d{2}$/', $v) ? [$v] : [];
});

/* 按 12 段位置参数拼 URL：id-area-by-class-lang-letter-level-order-page-state-tag-year */
function filterUrl($id, $slot, $value)
{
    $arr = array_fill(0, 12, '');
    $arr[0] = (string)$id;
    $idx = ['area' => 1, 'lang' => 4, 'year' => 11];
    $arr[$idx[$slot]] = (string)$value;
    return implode('-', $arr);
}

$urls = ["\t<url><loc>" . $site . "/vodshow/1-----------.html</loc><lastmod>" . $today . "</lastmod><priority>0.8</priority></url>"];
$seen = [];
$n = 0;

foreach ($types as $tid => $t) {
    /* 顶级分类自身没有直属影片，跳过（其筛选入口由子分类承载） */
    foreach ([['area', $byArea, $terms['area'] ?? []], ['lang', $byLang, $terms['lang'] ?? []], ['year', $byYear, []]] as $one) {
        list($slot, $dist, $list) = $one;
        if ($slot === 'year') {
            for ($y = (int)date('Y'); $y >= 2000; $y--) {
                $cnt = $dist[$tid . '|' . $y] ?? 0;
                if ($cnt < $MIN) continue;
                $u = filterUrl($tid, 'year', $y);
                if (isset($seen[$u])) continue;
                $seen[$u] = 1;
                $urls[] = "\t<url><loc>" . $site . "/vodshow/" . $u . ".html</loc><lastmod>" . $today . "</lastmod><priority>0.6</priority></url>";
                $n++;
            }
            continue;
        }
        foreach ($list as $it) {
            $cnt = $dist[$tid . '|' . $it['v']] ?? 0;
            if ($cnt < $MIN) continue;
            $u = filterUrl($tid, $slot, $it['s']);
            if (isset($seen[$u])) continue;
            $seen[$u] = 1;
            $urls[] = "\t<url><loc>" . $site . "/vodshow/" . $u . ".html</loc><lastmod>" . $today . "</lastmod><priority>0.6</priority></url>";
            $n++;
        }
    }
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
    . implode("\n", $urls) . "\n</urlset>";
file_put_contents($root . '/sitemap-filter.xml', $xml);
printf("filter sitemap: %d URL, %.1f KB\n", $n + 1, strlen($xml) / 1024);

/* 并入 sitemap.xml 索引 */
$indexFile = $root . '/sitemap.xml';
if (is_file($indexFile)) {
    $idx = file_get_contents($indexFile);
    $entry = '<sitemap><loc>' . $site . '/sitemap-filter.xml</loc><lastmod>' . $today . '</lastmod></sitemap>';
    if (strpos($idx, 'sitemap-filter.xml') === false) {
        $idx = str_replace('</sitemapindex>', $entry . '</sitemapindex>', $idx);
        file_put_contents($indexFile, $idx);
        echo "sitemap.xml 已加入 sitemap-filter.xml\n";
    } else {
        echo "sitemap.xml 已包含 sitemap-filter.xml\n";
    }
}
