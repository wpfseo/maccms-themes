# nightfall · www.ljly.net（黄豆影视）

自建 maccms 主题。相比商业主题，重点在**聚合页体系**和**内容质量兜底**，而不是视觉堆料。

## 目录

```
ljly-nightfall/
├── template/
│   ├── nightfall/                  # 主题本体
│   │   ├── assets/                 # nightfall.css / nightfall.js / og-default.jpg / mark.svg
│   │   └── html/
│   │       ├── public/             # head / header / footer / card / paging / boot / 404 / password
│   │       ├── index/              # 首页
│   │       ├── vod/                # 详情页（评分徽标、演职员条件渲染）
│   │       ├── rank/               # 排行榜（列表 + 单榜，含 ItemList 结构化数据）
│   │       ├── topic/              # 专题（列表 + 详情）
│   │       ├── actor/ director/    # 演员库、导演库
│   │       ├── tag/ combo/         # 题材标签、组合标签
│   │       ├── year/ series/       # 年份片单、系列
│   │       ├── browse/ channel/    # 筛选页、垂直频道
│   ├── _shared/                    # PB 主题统一底座（kit/twig/starter）
├── application/
│   ├── route.php                   # 主题路由（rank / topic 等，变量规则含连字符）
│   ├── common/service/
│   │   ├── Nightfall.php           # 主题核心服务：文本安全截断、演职员板块、榜单/专题取数、meta 拼装
│   │   └── Related.php             # 相关影片推荐
│   ├── index/controller/
│   │   ├── Nightrank.php           # 排行榜（总览 + 单榜）
│   │   ├── Nighttopic.php          # 专题详情
│   │   ├── Nightactor / Nightdirector / Nighttag / Nightcombo / Nightyear / Nightseries
│   │   ├── Nightbrowse / Nightchannel
│   │   └── Topic.php / Actor.php
└── tools/                          # 构建与推送脚本
```

## 页面体系规模

| 类型 | 数量 | 说明 |
|---|---|---|
| 演员库 | 29,908 | `/actor/{slug}.html` |
| 导演库 | 4,743 | `/director/{slug}.html` |
| 题材标签 | 66 | `/tag/{slug}.html` |
| 组合标签 | 119 | `/combo/{slug}.html` |
| 年份片单 | 391 | `/year/{year}.html` |
| 系列 | 817 | `/series/{slug}.html` |
| 排行榜 | 129 + 总览 | `/rank.html`、`/rank/{slug}.html` |
| 专题 | 12 | `/topic/{slug}.html`（复仇、卧底、杀手、谍战、律政、悬疑、医患、穿越、拳击、赛车、丧尸、时间循环） |

## 关键实现点

**`Nightfall::text()` 安全截断**
旧实现遇到超长文本会抛 `InvalidArgumentException`，被框架渲染成整页 404。现改为 `mb_substr` 静默截断，一并救活了存量坏页。

**演职员板块条件渲染**
`creditsSection($obj)` 在导演、演员、语言全空时返回空串，模板侧不再输出空行空壳。

**评分展示**
详情页 `score-row` 徽标（64×26）+ 列表卡片 `film-score` 角标（52×22），数据来自 `vod_douban_score`，**为 0 或空时不渲染**，不会出现一堆 0.0。16 个模板共用 `public/card.html`，改一处全站生效。

**结构化数据**
`Movie` / `TVSeries` / `ItemList` 三类 JSON-LD 全站覆盖。豆瓣无评分人数字段，因此不输出 `aggregateRating`。

## 构建脚本

| 脚本 | 用途 |
|---|---|
| `build-sitemap.php` | 生成 sitemap 索引 + 12 个分片；详情页分片按「有简介或有评分」过滤，共 191,851 条。跑满约 8 分钟，需 `nohup` 后台执行 |
| `build-filters.php` | 生成筛选组合页 |
| `build-relations.php` | 生成相关影片关系 |
| `fill-metadata.php` | 补全空字段（年份、地区、语言等） |
| `push-rank.php` / `push-topic.php` / `push-search.php` | 百度普通收录 + IndexNow 推送 |
| `refresh.sh` | 串联构建流程 |

## 部署要点

1. `_shared/` 是主题底座，**改公共组件只动这里**；主题里 `page-builder-components/` 是 `PageBuilderKit::syncToTheme()` 的产物，会被覆盖
2. 改 CSS 必须升级引用版本号 `nightfall.css?v=x.y.z`，否则 CDN 按完整 URL 缓存会命中旧文件
3. `tools/push.conf.php.example` → `push.conf.php` 后填 token
4. TP5.0 不支持模板 `|raw`，需要原样输出 HTML 时用 `{php}echo htmlspecialchars_decode(...){/php}`
