<?php
namespace app\index\controller;

use think\Db;

/**
 * 垂直频道（P2）：短剧 / 体育赛事 / 影视解说
 *
 * 三个频道共用一套逻辑，靠 $channel 配置区分数据源：
 *   - type_id_1 = 大类 ID（短剧=1，体育赛事=19）
 *   - tag       = 标签名（影视解说没有独立大类，用 tag 兜底）
 *
 * URL：/duanju.html、/sports.html、/jieshuo.html，分页 /duanju/page/2.html
 */
class Nightchannel extends Base
{
    const PAGE_SIZE = 30;

    /* 频道配置。key 即 URL 前缀，改这里就能加新频道 */
    const CH = [
        'duanju' => [
            'name' => '短剧',
            'en'   => 'SHORT DRAMA',
            'type' => 1,
            'tag'  => '',
            'lead' => '竖屏短剧、反转爽剧、女频恋爱与年代穿越，一集几分钟，一口气看完。',
        ],
        'sports' => [
            'name' => '体育赛事',
            'en'   => 'SPORTS',
            'type' => 19,
            'tag'  => '',
            'lead' => '足球、篮球、台球、网球赛事录像与集锦，按项目分类浏览。',
        ],
        'jieshuo' => [
            'name' => '影视解说',
            'en'   => 'COMMENTARY',
            'type' => 0,
            'tag'  => '影视解说',
            'lead' => '几十分钟看完一部电影，影视解说与剧情速览合集。',
        ],
    ];

    public function duanju()
    {
        return $this->render('duanju');
    }

    public function sports()
    {
        return $this->render('sports');
    }

    public function jieshuo()
    {
        return $this->render('jieshuo');
    }

    /* 三个频道共用同一套渲染逻辑 */
    private function render($key)
    {
        if (!isset(self::CH[$key])) {
            $this->assign('nf404', '频道地址不正确。');
            $this->assign('nf404Back', '/');
            $this->assign('nf404BackText', '首页');
            return response($this->label_fetch('public/404'), 404);
        }
        $ch    = self::CH[$key];
        $page  = max(1, (int)input('param.page', 1));
        $where = $ch['type'] > 0 ? 'v.type_id_1 = ' . (int)$ch['type'] : '1=1';
        $join  = $ch['tag'] !== ''
            ? " INNER JOIN nf_vod_tag gt ON gt.vod_id = v.vod_id AND gt.tag = '" . str_replace("'", "", $ch['tag']) . "'"
            : '';

        $ck = 'nf_ch_' . $key . '_' . $page;
        $data = cache($ck);
        if (!is_array($data) || !$data) {
            $cnt   = Db::query('SELECT COUNT(*) AS c FROM mac_vod v' . $join . ' WHERE ' . $where . ' AND v.vod_status = 1');
            $total = (int)$cnt[0]['c'];

            $rows = Db::query(
                'SELECT ' . $this->fields() . ' FROM mac_vod v' . $join
                . ' WHERE ' . $where . ' AND v.vod_status = 1 ORDER BY v.vod_id DESC'
                . ' LIMIT ' . (($page - 1) * self::PAGE_SIZE) . ',' . self::PAGE_SIZE
            );

            /* 子类分布：优先挂到已有题材页，没有对应标签就退回归属分类页 */
            $subs = [];
            if ($ch['type'] > 0) {
                // 子类片量：走 idx_type_status_time，不要和 nf_tag 做 OR 关联（会退化成笛卡尔积）
                $subs = Db::query(
                    'SELECT t.type_id, t.type_name, COUNT(v.vod_id) AS c'
                    . ' FROM mac_type t'
                    . ' LEFT JOIN mac_vod v ON v.type_id = t.type_id AND v.vod_status = 1'
                    . ' WHERE t.type_pid = ? GROUP BY t.type_id ORDER BY c DESC',
                    [(int)$ch['type']]
                );
                // 分类名 → 题材页 slug（短剧子类名比标签少一个「剧」字，做个兜底）
                $slugMap = [];
                foreach (Db::query('SELECT name, slug FROM nf_tag') as $tr) {
                    if ($tr['slug'] !== '') $slugMap[$tr['name']] = $tr['slug'];
                }
                foreach ($subs as $k => $sb) {
                    $nm = $sb['type_name'];
                    $subs[$k]['slug'] = $slugMap[$nm] ?? ($slugMap[mb_substr($nm, 0, -1)] ?? '');
                }
            }

            $hot = Db::query(
                'SELECT ' . $this->fields() . ' FROM mac_vod v' . $join
                . ' WHERE ' . $where . ' AND v.vod_status = 1 AND v.vod_total >= 30'
                . ' ORDER BY v.vod_total DESC, v.vod_id DESC LIMIT 12'
            );

            $latest = Db::query(
                'SELECT ' . $this->fields() . ' FROM mac_vod v' . $join
                . ' WHERE ' . $where . ' AND v.vod_status = 1 ORDER BY v.vod_id DESC LIMIT 12'
            );

            $data = compact('total', 'rows', 'subs', 'hot', 'latest');
            cache($ck, $data, 1800);
        }
        extract($data);

        $pages    = max(1, (int)ceil($total / self::PAGE_SIZE));
        $pageList = [];
        $start    = max(1, $page - 3);
        $end      = min($pages, $start + 6);
        $start    = max(1, $end - 6);
        for ($i = $start; $i <= $end; $i++) {
            $pageList[] = ['n' => $i, 'url' => \app\common\service\Nightfall::channelUrl($key, $i), 'cur' => $i === $page];
        }

        foreach ($subs as $k => $s) {
            $subs[$k]['url'] = $s['slug'] !== ''
                ? \app\common\service\Nightfall::genreUrl($s['slug'])
                : '/vodtype/' . (int)$s['type_id'] . '.html';
        }

        $this->assign('nfCh', $ch);
        $this->assign('nfChKey', $key);
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfSubs', $subs);
        $this->assign('nfHot', $hot);
        $this->assign('nfLatest', $latest);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $pageList);
        $this->assign('nfUrlFirst', \app\common\service\Nightfall::channelUrl($key, 1));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::channelUrl($key, 1));
        $this->assign('nfUrlPrev', \app\common\service\Nightfall::channelUrl($key, max(1, $page - 1)));
        $this->assign('nfUrlNext', \app\common\service\Nightfall::channelUrl($key, min($pages, $page + 1)));
        $this->assign('nfUrlLast', \app\common\service\Nightfall::channelUrl($key, $pages));
        $this->assign('nfPageNum', $page);
        return $this->label_fetch('channel/index');
    }

    private function fields()
    {
        return 'v.vod_id,v.type_id,v.type_id_1,v.vod_name,v.vod_sub,v.vod_en,v.vod_letter,v.vod_pic,v.vod_remarks,'
            . 'v.vod_year,v.vod_area,v.vod_lang,v.vod_total,v.vod_serial,v.vod_score,v.vod_time_add';
    }
}
