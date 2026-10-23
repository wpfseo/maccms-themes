#!/bin/bash
# ljly.net 定期数据刷新：倒排表 -> P2 组合/系列 -> 补拼音 slug -> sitemap -> 筛选页 sitemap
# 由 cron 调用，也可手动执行： sudo -u www bash /www/wwwroot/www.ljly.net/tools/refresh.sh
#
# 失败处理：
#   - 任一步骤非 0 退出 -> 写 ALERT 日志、可选 webhook 推送、脚本以 1 退出（cron MAILTO 可收到）
#   - sitemap 生成后做数量体检，数量异常（例如 slug 被清空导致演员页消失）同样触发告警

set -u

ROOT=/www/wwwroot/www.ljly.net
PHP=/www/server/php/73/bin/php
PY=/opt/ljly_slug/venv/bin/python
SEED=/opt/ljly_slug/seed
LOG=/www/wwwlogs/ljly_refresh.log
ALERTLOG=/www/wwwlogs/ljly_refresh_alert.log
LOCK=/tmp/ljly_refresh.lock
# 告警 webhook（企业微信/钉钉/飞书机器人地址），留空则只写日志
WEBHOOK=""

STATUS=0
MSG=""

exec 9>"$LOCK"
if ! flock -n 9; then
    echo "[$(date '+%F %T')] 上一轮仍在执行，本次跳过" >>"$LOG"
    exit 0
fi

note() {
    MSG="$MSG$1\n"
    STATUS=1
}

alert() {
    local body
    body=$(printf "%b" "$MSG")
    echo "[$(date '+%F %T')] ALERT ljly refresh 失败：" >>"$ALERTLOG"
    printf "%b\n" "$MSG" >>"$ALERTLOG"
    if [ -n "$WEBHOOK" ]; then
        local json
        json=$(printf '%s' "$body" | sed 's/\\/\\\\/g; s/"/\\"/g' | tr '\n' ' ')
        curl -s -m 10 -H 'Content-Type: application/json' \
            -d "{\"msgtype\":\"text\",\"text\":{\"content\":\"[ljly.net] 数据刷新失败：$json\"}}" \
            "$WEBHOOK" >/dev/null 2>&1 || true
    fi
}

{
    echo "===== $(date '+%F %T') 刷新开始 ====="

    "$PHP" "$ROOT/tools/build-relations.php" "$ROOT"
    RC=$?
    echo "--- build-relations 退出码: $RC ---"
    if [ "$RC" -ne 0 ]; then note "build-relations 失败（退出码 $RC）"; fi

    if [ "$RC" -eq 0 ]; then
        # P2：组合标签 + 系列片单（依赖上面的倒排表）
        "$PHP" "$ROOT/tools/build-p2.php" "$ROOT"
        RC2=$?
        echo "--- build-p2 退出码: $RC2 ---"
        if [ "$RC2" -ne 0 ]; then note "build-p2 失败（退出码 $RC2）"; fi

        "$PY" "$ROOT/tools/fill-slugs.py" "$ROOT" --seed "$SEED"
        RC3=$?
        echo "--- fill-slugs 退出码: $RC3 ---"
        if [ "$RC3" -ne 0 ]; then note "fill-slugs 失败（退出码 $RC3）"; fi
    else
        echo "--- 倒排表构建失败，跳过补 slug，避免产出不完整 sitemap ---"
    fi

    if [ "$RC" -eq 0 ]; then
        "$PHP" "$ROOT/tools/build-sitemap.php" "$ROOT"
        RC4=$?
        echo "--- build-sitemap 退出码: $RC4 ---"
        if [ "$RC4" -ne 0 ]; then note "build-sitemap 失败（退出码 $RC4）"; fi

        "$PHP" "$ROOT/tools/build-filters.php" "$ROOT"
        RC5=$?
        echo "--- build-filters 退出码: $RC5 ---"
        if [ "$RC5" -ne 0 ]; then note "build-filters 失败（退出码 $RC5）"; fi

        rm -rf "$ROOT/runtime/cache/facet"/*
        echo "--- 清理 facet 计数缓存 ---"

        # 搜索收录推送（IndexNow：必应/Yandex 等；百度 token 需自行填入 tools/push.conf.php）
        if [ -f "$ROOT/tools/push.conf.php" ]; then
            "$PHP" "$ROOT/tools/push-search.php" "$ROOT" --since=8 --limit=5000 --engine=indexnow
            RC6=$?
            echo "--- push-search(indexnow) 退出码: $RC6 ---"
            if [ "$RC6" -ne 0 ]; then note "IndexNow 推送失败（退出码 $RC6，详见 ljly_push.log）"; fi
        fi

        # sitemap 体检：数量骤降说明数据链路出问题（历史上出现过 slug 被清空）
        cast_n=$(grep -o '<loc>' "$ROOT/sitemap-cast.xml" 2>/dev/null | wc -l)
        dir_n=$(grep -o '<loc>' "$ROOT/sitemap-director.xml" 2>/dev/null | wc -l)
        idx_n=$(grep -o '<loc>' "$ROOT/sitemap.xml" 2>/dev/null | wc -l)
        echo "--- sitemap 体检：演员 $cast_n / 导演 $dir_n / 索引 $idx_n ---"
        if [ "${cast_n:-0}" -lt 20000 ]; then note "sitemap-cast 只有 $cast_n 条（预期 ≥20000），slug 可能丢失"; fi
        if [ "${dir_n:-0}" -lt 3000 ]; then note "sitemap-director 只有 $dir_n 条（预期 ≥3000）"; fi
        if [ "${idx_n:-0}" -lt 7 ]; then note "sitemap 索引只有 $idx_n 个分片（预期 7 个）"; fi
    fi

    if [ "$STATUS" -eq 0 ]; then
        echo "===== $(date '+%F %T') 刷新结束（正常）====="
    else
        echo "===== $(date '+%F %T') 刷新结束（有失败，见 ALERT 日志）====="
    fi
} >>"$LOG" 2>&1

if [ "$STATUS" -ne 0 ]; then
    alert
fi

# 日志超过 5MB 时只保留最后 1MB
if [ -f "$LOG" ]; then
    SIZE=$(stat -c%s "$LOG")
    if [ "$SIZE" -gt 5242880 ]; then
        tail -c 1048576 "$LOG" >"$LOG.tmp" && mv "$LOG.tmp" "$LOG"
    fi
fi

exit "$STATUS"
