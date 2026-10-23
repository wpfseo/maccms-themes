# hg093 · www.54086.com

商业主题本体（未大改视觉），自定义部分集中在**榜单目录服务**、**榜单/专题控制器**和**性能优化**。

## 目录

```
hg093-54086/
├── template/hg093/
│   ├── html/
│   │   ├── block/              # vodbox（列表卡片）、weekbox（追剧日历）等碎片
│   │   ├── index/              # 首页
│   │   ├── vod/                # 详情页（评分板块读 vod_douban_score）
│   │   ├── rank/               # 排行榜模板
│   │   ├── topic/              # 专题模板（列表 num 8→20）
│   │   ├── label/ public/ user/ comment/ rss/
│   ├── admin/                  # 主题后台配置（theme.php / theme_cache.php）
│   ├── static/                 # css / js / img / font / emoji
│   └── shuoming.png
├── application/
│   ├── route.php
│   ├── common/service/RankCatalog.php    # 榜单目录：配置加载、revision、取数
│   ├── index/controller/
│   │   ├── Rank.php            # 榜单页（带版本化缓存）
│   │   └── Topic.php           # 专题页
│   └── data/rankings.php       # 37 个榜单的配置（slug / 分组 / 排序 / 启用）
└── tools/
    ├── warm-rank.sh            # 榜单缓存预热（每小时 cron）
    └── push-search.php         # 收录推送
```

## 规模

| 项 | 数量 |
|---|---|
| 影片 | 347,648 |
| 有豆瓣评分 | 8,297（2.4%，保守匹配，同名多版本一律跳过） |
| 排行榜 | 37 |
| 专题 | 15 |

## 性能优化记录

**1. `mac_vod` 复合索引**
`idx_type_status_rec`、`idx_t1_status_time`、`idx_type_status_time` 三条。
注意 MyISAM 的 `ADD INDEX` 是**整表重建**（Repair by sorting），1 GB 表约 13–18 分钟/条，务必低峰执行，且要留意采集进程抢元数据锁。

**2. 首页 8s → 0.9s**
根因是追剧日历的 `WHERE vod_status=1 AND vod_weekday LIKE '%三%' ORDER BY vod_time DESC LIMIT 18`，而 `vod_weekday` 全表 34.7 万行皆空——每次请求全表扫描 + 排序，返回 0 条。按 `vod_time` 的星期回填 34.7 万行后，查询降到 0.010s。
遗留：星期按入库时间推导，采集集中在周三，故分布偏斜。

**3. 榜单 1.56s → 0.21s**
`/rank.html` 需为全部 37 个榜各跑一次查询，原缓存 TTL 900s，访客常撞冷缓存。改为缓存 key 带 `RankCatalog::revision()`（配置一改自动失效）+ TTL 21600s，并由 `warm-rank.sh` 每小时预热全部 37 页（全程约 6.5s）。

**4. 专题详情 8–13s → 0.11s**
`mac_topic.topic_tag` 非空时，详情页会按 tag 对全库做 `LIKE` 关联合并影片。15 个专题的 `topic_tag` 全部置空（片单本身是人工精选，tag 合并只会稀释准确度），页面降到 0.1–0.4s。

## 部署注意

- `rankings.php` 是榜单配置，`RankCatalog` 读它并计算 revision；改配置后缓存自动失效，无需手动清
- `warm-rank.sh` 依赖站点 URL 与 spider UA（普通 PC UA 会被 `pc_human_content` 审核页拦截）
- 详情页评分只读 `vod_douban_score`，不要把豆瓣分写进 `vod_score`（那是站内投票分，语义需保持干净）
