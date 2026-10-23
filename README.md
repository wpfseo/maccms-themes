# MacCMS 站点模板与配套代码

两个 AppleCMS / 深蓝影视（maccms）站点的主题模板与自定义扩展代码。均基于 **ThinkPHP 5.0 + PHP 7.3**，不含数据库配置与任何密钥。

| 目录 | 站点 | 模板 | 定位 |
|---|---|---|---|
| [`ljly-nightfall/`](ljly-nightfall/) | www.ljly.net（黄豆影视） | `nightfall` + `_shared` 底座 | 自建主题，聚合页体系完整 |
| [`hg093-54086/`](hg093-54086/) | www.54086.com | `hg093` | 商业主题 + 自建榜单/专题扩展 |

## 仓库结构约定

每个站点目录都按 maccms 根目录的相对路径组织，可直接对照覆盖：

```
<site>/
├── template/<主题名>/        # 模板本体（html / assets / static）
├── application/
│   ├── route.php            # 路由规则（含主题专属路由）
│   ├── common/service/      # 主题服务类（数据装配、榜单目录、SEO 辅助）
│   ├── index/controller/    # 主题控制器（Night* / Rank / Topic）
│   └── data/                # 榜单等配置数据（仅 54086）
└── tools/                   # 运维脚本：构建、推送、预热
```

## 两个站的差异

**ljly.net · nightfall**
- 主题自带一套 `_shared` PB 底座（`twig/` 行为宏、`kit/` 组件库、`starter/` 新主题壳），改公共组件只动 `_shared`，不要改主题里 sync 出来的 `page-builder-components/`
- 页面体系：演员库 29,908 / 导演库 4,743 / 题材 66 / 组合标签 119 / 年份片单 391 / 系列 817 / 垂直频道 3
- 排行榜 129 个 + 专题 12 个，sitemap 分片 12 个（详情页 191,851 条）

**54086.com · hg093**
- 商业主题，模板本身较重（5.9 MB，含 `static/` 图片资源），扩展代码独立放在 `RankCatalog` + `Rank`/`Topic` 控制器
- 排行榜 37 个 + 专题 15 个，榜单配置在 `application/data/rankings.php`
- 性能上做了三件事：`mac_vod` 复合索引、`vod_weekday` 回填（首页 8s→0.9s）、榜单缓存 6h + 每小时预热

## 部署

1. 备份现有 `template/`、`application/route.php`、`application/common/service/`
2. 按目录结构覆盖文件
3. `tools/push.conf.php.example` 复制为 `push.conf.php`，填入百度 token 与 IndexNow key
4. 清 `runtime/cache/`，改 CSS 时记得升级 `?v=` 版本号（CDN 按完整 URL 缓存）

## 注意事项

- **PHP 7.3 / TP5.0 限制**：不支持 `|raw`（用 `{php}echo htmlspecialchars_decode(...){/php}`）、不支持箭头函数 `fn()=>`，模板里暂无 `|raw` 用法
- **路由变量规则**：TP5 默认 `\w+`，含连字符的 slug 必须显式声明 `'key'=>'[\w-]+'`
- 本仓库不含 `application/database.php`、`application/extra/*.php`（含站点密钥）、`runtime/`、上传目录
