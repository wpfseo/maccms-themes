<?php
/**
 * P1 聚合页 sitemap 生成器
 *
 * 产出:
 *   sitemap.xml            sitemap 索引
 *   sitemap-cast.xml       演员库（建档演员全量 URL）
 *   sitemap-genre.xml      题材标签频道
 *   sitemap-director.xml   导演库（P2）
 *   sitemap-combo.xml      标签组合（P2）
 *   sitemap-year.xml       年份片单（P2）
 *   sitemap-series.xml     系列片单（P2）
 *
 * 重新生成: php tools/build-sitemap.php /path/to/root
 * 建议在 build-relations.php 跑完后执行一次。
 */

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);
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

$site = 'https://www.ljly.net';
$today = date('Y-m-d');

function xmlOut($path, $body)
{
    file_put_contents($path, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $body . "\n");
    echo basename($path), ' ', number_format(round(filesize($path) / 1024)), "KB\n";
}

/* ---- 演员库 ---- */
$urls = "\t<url><loc>" . $site . "/cast.html</loc><lastmod>" . $today . "</lastmod><priority>0.8</priority></url>";
$st = $db->query('SELECT slug FROM nf_actor WHERE slug <> \'\' AND vod_count >= 10 ORDER BY vod_count DESC');
$n = 0;
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $urls .= "\t<url><loc>" . $site . '/cast/' . htmlspecialchars($r['slug']) . ".html</loc><lastmod>" . $today . "</lastmod></url>";
    $n++;
}
xmlOut($root . '/sitemap-cast.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");

/* ---- 题材标签 ---- */
$urls = "\t<url><loc>" . $site . "/genre.html</loc><lastmod>" . $today . "</lastmod><priority>0.8</priority></url>";
$st = $db->query('SELECT slug FROM nf_tag WHERE slug <> \'\' AND vod_count >= 100 ORDER BY vod_count DESC');
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $urls .= "\t<url><loc>" . $site . '/genre/' . htmlspecialchars($r['slug']) . ".html</loc><lastmod>" . $today . "</lastmod></url>";
}
xmlOut($root . '/sitemap-genre.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");

/* ---- 导演库（P2） ---- */
$dirn = 0;
try {
    $urls = "\t<url><loc>" . $site . "/director.html</loc><lastmod>" . $today . "</lastmod><priority>0.8</priority></url>";
    $st = $db->query("SELECT slug FROM nf_director WHERE slug <> '' AND vod_count >= 10 ORDER BY vod_count DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $urls .= "\t<url><loc>" . $site . '/director/' . htmlspecialchars($r['slug']) . ".html</loc><lastmod>" . $today . "</lastmod></url>";
        $dirn++;
    }
    if ($dirn > 0) {
        xmlOut($root . '/sitemap-director.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");
    }
} catch (Exception $e) {
    fwrite(STDERR, 'director sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 标签组合（P2） ---- */
$combon = 0;
try {
    $urls = "\t<url><loc>" . $site . "/combo.html</loc><lastmod>" . $today . "</lastmod><priority>0.7</priority></url>";
    $st = $db->query("SELECT a_slug,b_slug FROM nf_combo WHERE vod_count >= 300 ORDER BY vod_count DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $urls .= "\t<url><loc>" . $site . '/combo/' . htmlspecialchars($r['a_slug'] . '-' . $r['b_slug']) . ".html</loc><lastmod>" . $today . "</lastmod></url>";
        $combon++;
    }
    if ($combon > 0) {
        xmlOut($root . '/sitemap-combo.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");
    }
} catch (Exception $e) {
    fwrite(STDERR, 'combo sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 年份片单（P2）：年份页 + 该年类型页（>=200 部才出） ---- */
$yearn = 0;
try {
    $urls = "\t<url><loc>" . $site . "/year.html</loc><lastmod>" . $today . "</lastmod><priority>0.7</priority></url>";
    $st = $db->query("SELECT vod_year FROM mac_vod WHERE vod_status=1 AND vod_year REGEXP '^(19|20)[0-9]{2}$' GROUP BY vod_year HAVING COUNT(*)>=50 ORDER BY vod_year DESC");
    $years = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $years[] = $r['vod_year']; }
    foreach ($years as $y) {
        $urls .= "\t<url><loc>" . $site . "/year/" . $y . ".html</loc><lastmod>" . $today . "</lastmod></url>";
        $yearn++;
        $st2 = $db->query("SELECT t.type_en, COUNT(*) c FROM mac_vod v INNER JOIN mac_type t ON t.type_id=v.type_id "
            . "WHERE v.vod_status=1 AND v.vod_year=" . (int)$y . " AND t.type_en<>'' GROUP BY t.type_id HAVING c>=200");
        foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $urls .= "\t<url><loc>" . $site . '/year/' . $y . '-' . htmlspecialchars($t['type_en']) . ".html</loc><lastmod>" . $today . "</lastmod></url>";
            $yearn++;
        }
    }
    xmlOut($root . '/sitemap-year.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");
} catch (Exception $e) {
    fwrite(STDERR, 'year sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 系列片单（P2） ---- */
$seriesn = 0;
try {
    $urls = "\t<url><loc>" . $site . "/series.html</loc><lastmod>" . $today . "</lastmod><priority>0.7</priority></url>";
    $st = $db->query("SELECT slug FROM nf_series WHERE slug <> '' ORDER BY vod_count DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $urls .= "\t<url><loc>" . $site . '/series/' . htmlspecialchars($r['slug']) . ".html</loc><lastmod>" . $today . "</lastmod></url>";
        $seriesn++;
    }
    xmlOut($root . '/sitemap-series.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");
} catch (Exception $e) {
    fwrite(STDERR, 'series sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 榜单（分类 × 年份，按豆瓣评分） ---- */
$rankn = 0;
try {
    $urls = "\t<url><loc>" . $site . "/rank.html</loc><lastmod>" . $today . "</lastmod><priority>0.8</priority></url>";
    $te = [];
    foreach ($db->query("SELECT type_id,type_en,type_status FROM mac_type") as $t) {
        $te[(int)$t['type_id']] = $t;
    }
    $st = $db->query("SELECT type_id,vod_year,COUNT(*) c FROM mac_vod
        WHERE vod_status=1 AND vod_douban_score>=3 AND vod_year BETWEEN 2020 AND 2030
        AND type_id NOT IN (37,38,67,68,69,70,71,72,56)
        GROUP BY type_id,vod_year HAVING c>=12 ORDER BY vod_year DESC,c DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $t = $te[(int)$r['type_id']] ?? null;
        if (!$t || (int)$t['type_status'] !== 1 || trim((string)$t['type_en']) === '') continue;
        $urls .= "\t<url><loc>" . $site . '/rank/' . htmlspecialchars(trim($t['type_en'])) . '-' . (int)$r['vod_year'] . ".html</loc><lastmod>" . $today . "</lastmod><priority>0.7</priority></url>";
        $rankn++;
    }
    xmlOut($root . '/sitemap-rank.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");
} catch (Exception $e) {
    fwrite(STDERR, 'rank sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 专题（题材母题片单，URL 为 /topic/{拼音}.html） ---- */
$topicn = 0;
try {
    $urls = "\t<url><loc>" . $site . "/topic.html</loc><lastmod>" . $today . "</lastmod><priority>0.8</priority></url>";
    $st = $db->query("SELECT topic_en,topic_time FROM mac_topic WHERE topic_status=1 AND topic_en<>'' ORDER BY topic_sort DESC");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $lm = date('Y-m-d', (int)$r['topic_time'] > 0 ? (int)$r['topic_time'] : time());
        $urls .= "\t<url><loc>" . $site . '/topic/' . htmlspecialchars(trim($r['topic_en'])) . ".html</loc><lastmod>" . $lm . "</lastmod><priority>0.7</priority></url>";
        $topicn++;
    }
    xmlOut($root . '/sitemap-topic.xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>");
} catch (Exception $e) {
    fwrite(STDERR, 'topic sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 详情页分片 ----
   只收录「有简介(>=30字) 或 有评分」的页面，约 19 万条。
   其余 15 万无简介无评分的壳页不进 sitemap，避免用空内容摊薄抓取预算。
   按 5 万 URL/片分片（单文件上限 5 万）。用非缓冲查询逐行处理，避免 34 万行一次性入内存。 */
$vodParts = 0; $vodCount = 0;
try {
    $perPart = 50000;
    $vpart = 1; $vn = 0;
    $head = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $buf = $head;
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $stmt = $db->query("SELECT vod_id,vod_time FROM mac_vod
        WHERE vod_status=1 AND (CHAR_LENGTH(vod_content)>=30 OR vod_douban_score>0)
        ORDER BY vod_id");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vt = (int)($r['vod_time'] ?? 0);
        $lm = $vt > 0 ? date('Y-m-d', $vt) : $today;
        $buf .= "\t<url><loc>" . $site . "/voddetail/" . (int)$r['vod_id'] . ".html</loc>"
            . "<lastmod>" . $lm . "</lastmod><priority>0.6</priority></url>\n";
        $vn++; $vodCount++;
        if ($vn >= $perPart) {
            xmlOut($root . '/sitemap-vod-' . $vpart . '.xml', $buf . "</urlset>");
            $vodParts = $vpart; $vpart++; $vn = 0; $buf = $head;
        }
    }
    if ($vn > 0) {
        xmlOut($root . '/sitemap-vod-' . $vpart . '.xml', $buf . "</urlset>");
        $vodParts = $vpart;
    }
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
} catch (Exception $e) {
    fwrite(STDERR, 'vod sitemap skipped: ' . $e->getMessage() . "\n");
}

/* ---- 索引 ---- */
$vodIdx = '';
for ($i = 1; $i <= $vodParts; $i++) {
    $vodIdx .= "<sitemap><loc>" . $site . "/sitemap-vod-" . $i . ".xml</loc><lastmod>" . $today . "</lastmod></sitemap>";
}
/* build-filters.php 会把筛选页写进索引，但本脚本重建索引时会把它覆盖掉，这里补回 */
$filterIdx = is_file($root . '/sitemap-filter.xml')
    ? "<sitemap><loc>" . $site . "/sitemap-filter.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "";

$idx = "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">"
    . "<sitemap><loc>" . $site . "/sitemap-cast.xml</loc><lastmod>" . $today . "</lastmod></sitemap>"
    . "<sitemap><loc>" . $site . "/sitemap-genre.xml</loc><lastmod>" . $today . "</lastmod></sitemap>"
    . ($dirn > 0 ? "<sitemap><loc>" . $site . "/sitemap-director.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "")
    . ($combon > 0 ? "<sitemap><loc>" . $site . "/sitemap-combo.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "")
    . ($yearn > 0 ? "<sitemap><loc>" . $site . "/sitemap-year.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "")
    . ($seriesn > 0 ? "<sitemap><loc>" . $site . "/sitemap-series.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "")
    . ($rankn > 0 ? "<sitemap><loc>" . $site . "/sitemap-rank.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "")
    . ($topicn > 0 ? "<sitemap><loc>" . $site . "/sitemap-topic.xml</loc><lastmod>" . $today . "</lastmod></sitemap>" : "")
    . $filterIdx
    . $vodIdx
    . "</sitemapindex>";
xmlOut($root . '/sitemap.xml', $idx);

echo "actors: $n\ndirectors: $dirn\ncombo: $combon\nyear: $yearn\nseries: $seriesn\nrank: $rankn\ntopic: $topicn\ndetail: $vodCount ($vodParts parts)\n";
