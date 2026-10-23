#!/bin/bash
# 预热 54086 榜单页缓存（rank 缓存 TTL 6h，每小时跑一次保证访客永远命中热缓存）
UA='Mozilla/5.0 (compatible; Baiduspider/2.0)'
BASE='https://127.0.0.1'
H='Host: www.54086.com'

curl -sk -H "$H" -A "$UA" -o /dev/null --max-time 120 "$BASE/rank.html"

cd /www/wwwroot/www.54086.com
php -r '
$raw = file_get_contents("application/data/rankings.php");
$j = json_decode(substr($raw, strpos($raw, "{")), true);
foreach ($j["boards"] as $b) { if (!empty($b["enabled"])) echo $b["slug"], "\n"; }
' | while read -r slug; do
  [ -n "$slug" ] && curl -sk -H "$H" -A "$UA" -o /dev/null --max-time 120 "$BASE/rank/${slug}.html"
done

echo "$(date '+%F %T') warm done"
