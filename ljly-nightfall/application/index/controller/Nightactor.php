<?php
namespace app\index\controller;

use think\Db;

/**
 * 演员库（P1）
 *
 * 数据来自 tools/build-relations.php 生成的倒排表：
 *   nf_actor(name, vod_count)     演员 → 作品数
 *   nf_vod_actor(vod_id, actor)   演员 → 作品
 *
 * 建档门槛：参演作品 >= 10 部（Nightfall::AGG_MIN），低于门槛一律 404，
 * 避免出现大量只有一两部作品、内容空洞的薄页面。
 */
class Nightactor extends Base
{
    const PAGE_SIZE = 36;
    const MIN = 10;

    /* 演员库首页：作品数档位 + 代表人物 + 全部演员分页 */
    public function index()
    {
        $page = max(1, (int)input('param.page', 1));
        $tier = (string)input('param.tier', '');
        $tiers = $this->tiers();
        if (!isset($tiers[$tier])) {
            $tier = '';
        }
        list($min, $max) = $this->bounds($tier);

        $where = 'vod_count >= ' . $min;
        if ($max > 0) {
            $where .= ' AND vod_count <= ' . $max;
        }

        $ck    = 'nf_actor_cnt_' . md5($where);
        $total = cache($ck);
        if ($total === null || $total === false) {
            $row   = Db::query('SELECT COUNT(*) AS c FROM nf_actor WHERE ' . $where);
            $total = (int)$row[0]['c'];
            cache($ck, $total, 21600);
        }
        $total = (int)$total;

        // 第一页顶部放「代表作最多」榜单，分页列表顺延，避免和列表重复
        $top       = [];
        $topCount  = 0;
        $isDefault = $tier === '';
        if ($page === 1 && $isDefault) {
            $top = cache('nf_actor_top12');
            if (!is_array($top) || !$top) {
                $top = Db::query('SELECT name,slug,vod_count FROM nf_actor WHERE vod_count >= ? ORDER BY vod_count DESC, name ASC LIMIT 12', [self::MIN]);
                cache('nf_actor_top12', $top, 21600);
            }
            $topCount = count($top);
        }

        $start = ($page - 1) * self::PAGE_SIZE + $topCount;
        $list  = Db::query('SELECT name,slug,vod_count FROM nf_actor WHERE ' . $where . ' ORDER BY vod_count DESC, name ASC LIMIT ' . (int)$start . ',' . self::PAGE_SIZE);

        $pages    = max(1, (int)ceil(max(0, $total - $topCount) / self::PAGE_SIZE));
        $pageList = $this->pageList($page, $pages, $tier);
        $stat     = $this->stats();
        $tierList = [];
        foreach ($tiers as $k => $label) {
            $tierList[] = [
                'key'   => $k,
                'label' => $label,
                'count' => $k === '' ? $stat['total'] : (int)($stat[$k] ?? 0),
                'url'   => $this->pageUrl(1, $k),
            ];
        }
        $tierLabel = $tiers[$tier];

        foreach ($top as $k => $r) {
            $top[$k]['url'] = \app\common\service\Nightfall::castUrl($r['slug']);
        }
        foreach ($list as $k => $r) {
            $list[$k]['url'] = \app\common\service\Nightfall::castUrl($r['slug']);
        }

        $this->assign('nfTiers', $tierList);
        $this->assign('nfTier', $tier);
        $this->assign('nfTierLabel', $tierLabel);
        $this->assign('nfStat', $stat);
        $this->assign('nfTop', $top);
        $this->assign('nfList', $list);
        $this->assign('nfTotal', $total);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $pageList);
        $this->assign('nfUrlFirst', $this->pageUrl(1, $tier));
        $this->assign('nfUrlPrev', $this->pageUrl(max(1, $page - 1), $tier));
        $this->assign('nfUrlNext', $this->pageUrl(min($pages, $page + 1), $tier));
        $this->assign('nfUrlLast', $this->pageUrl($pages, $tier));
        $this->assign('nfCanonical', $this->pageUrl($page, $tier));
        return $this->label_fetch('actor/index');
    }

    /* 演员主页：全部作品 + 合作演员 + 类型分布 */
    public function detail()
    {
        $slug = strtolower(trim((string)input('param.name', '')));
        if ($slug === '' || !preg_match('/^[a-z0-9]{1,64}$/', $slug)) {
            return $this->notFound('演员地址不正确。');
        }

        $ck  = 'nf_actor_info_' . md5($slug);
        $act = cache($ck);
        if ($act === null || $act === false) {
            $row = Db::query('SELECT name,slug,vod_count FROM nf_actor WHERE slug = ? AND vod_count >= ? LIMIT 1', [$slug, self::MIN]);
            $act = $row ? $row[0] : false;
            cache($ck, $act, 21600);
        }
        if (!$act) {
            return $this->notFound('这位演员的作品数量还没有达到建档门槛，或地址有误。');
        }
        $name = $act['name'];

        $page = max(1, (int)input('param.page', 1));

        $ckc   = 'nf_actor_vods_' . md5($name);
        $total = cache($ckc);
        if ($total === null || $total === false) {
            $row   = Db::query(
                'SELECT COUNT(*) AS c FROM nf_vod_actor a INNER JOIN mac_vod v ON v.vod_id = a.vod_id WHERE a.actor = ? AND v.vod_status = 1',
                [$name]
            );
            $total = (int)$row[0]['c'];
            cache($ckc, $total, 21600);
        }
        $total = (int)$total;

        $rows = [];
        if ($total > 0) {
            $rows = Db::query(
                'SELECT v.vod_id,v.type_id,v.type_id_1,v.vod_name,v.vod_sub,v.vod_en,v.vod_letter,v.vod_pic,v.vod_remarks,'
                . 'v.vod_year,v.vod_area,v.vod_lang,v.vod_total,v.vod_serial,v.vod_score,v.vod_time_add '
                . 'FROM nf_vod_actor a INNER JOIN mac_vod v ON v.vod_id = a.vod_id '
                . 'WHERE a.actor = ? AND v.vod_status = 1 ORDER BY a.vod_id DESC '
                . 'LIMIT ' . (($page - 1) * self::PAGE_SIZE) . ',' . self::PAGE_SIZE,
                [$name]
            );
        }
        $pages    = max(1, (int)ceil($total / self::PAGE_SIZE));
        $pageList = $this->pageList($page, $pages, '');
        $link     = function ($p) use ($slug) {
            return \app\common\service\Nightfall::castUrl($slug, $p);
        };

        $mates = cache('nf_actor_mates_' . md5($name));
        if (!is_array($mates) || !$mates) {
            $mates = Db::query(
                'SELECT b.actor AS name, f.slug, COUNT(*) AS c FROM nf_vod_actor a '
                . 'INNER JOIN nf_vod_actor b ON b.vod_id = a.vod_id AND b.actor <> a.actor '
                . 'INNER JOIN nf_actor f ON f.name = b.actor AND f.vod_count >= ? '
                . 'WHERE a.actor = ? GROUP BY b.actor, f.slug ORDER BY c DESC, b.actor ASC LIMIT 12',
                [self::MIN, $name]
            );
            cache('nf_actor_mates_' . md5($name), $mates, 604800);
        }

        $types = cache('nf_actor_types_' . md5($name));
        if (!is_array($types) || !$types) {
            $types = Db::query(
                'SELECT v.type_id, v.type_id_1, t.type_name, t.type_mid, t.type_en, COUNT(*) AS c FROM nf_vod_actor a '
                . 'INNER JOIN mac_vod v ON v.vod_id = a.vod_id INNER JOIN mac_type t ON t.type_id = v.type_id '
                . 'WHERE a.actor = ? AND v.vod_status = 1 GROUP BY v.type_id ORDER BY c DESC LIMIT 10',
                [$name]
            );
            cache('nf_actor_types_' . md5($name), $types, 21600);
        }

        foreach ($mates as $k => $r) {
            $mates[$k]['url'] = \app\common\service\Nightfall::castUrl($r['slug']);
        }

        $this->assign('nfActor', $act);
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfMates', $mates);
        $this->assign('nfTypes', $types);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $pageList);
        $this->assign('nfUrlFirst', $link(1));
        $this->assign('nfUrlPrev', $link(max(1, $page - 1)));
        $this->assign('nfUrlNext', $link(min($pages, $page + 1)));
        $this->assign('nfUrlLast', $link($pages));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::castUrl());
        $this->assign('nfPageNum', $page);
        return $this->label_fetch('actor/detail');
    }

    /* 演员库总统计（缓存 6 小时） */
    private function stats()
    {
        $s = cache('nf_actor_stats');
        if (is_array($s) && $s) {
            return $s;
        }
        $min = self::MIN;
        $row = Db::query(
            'SELECT COUNT(*) AS total,'
            . " SUM(vod_count BETWEEN $min AND 19) AS a,"
            . ' SUM(vod_count BETWEEN 20 AND 49) AS b,'
            . ' SUM(vod_count BETWEEN 50 AND 199) AS c,'
            . ' SUM(vod_count >= 200) AS d'
            . " FROM nf_actor WHERE vod_count >= $min"
        );
        $r  = $row ? $row[0] : [];
        $s  = [
            'total'  => (int)($r['total'] ?? 0),
            'a'      => (int)($r['a'] ?? 0),
            'b'      => (int)($r['b'] ?? 0),
            'c'      => (int)($r['c'] ?? 0),
            'd'      => (int)($r['d'] ?? 0),
            'min'    => self::MIN,
        ];
        cache('nf_actor_stats', $s, 21600);
        return $s;
    }

    private function tiers()
    {
        return [
            ''  => '全部演员',
            'a' => '10–19 部',
            'b' => '20–49 部',
            'c' => '50–199 部',
            'd' => '200 部以上',
        ];
    }

    private function bounds($tier)
    {
        switch ($tier) {
            case 'a':
                return [self::MIN, 19];
            case 'b':
                return [20, 49];
            case 'c':
                return [50, 199];
            case 'd':
                return [200, 0];
            default:
                return [self::MIN, 0];
        }
    }

    private function pageUrl($p, $tier)
    {
        $q = [];
        if ($tier !== '') {
            $q['tier'] = $tier;
        }
        if ($p > 1) {
            $q['page'] = $p;
        }
        $u = \app\common\service\Nightfall::castUrl('', $p > 1 ? $p : 1);
        unset($q['page']);
        if ($q) {
            $u .= '?' . http_build_query($q);
        }
        return $u;
    }

    private function pageList($page, $pages, $tier)
    {
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        $out   = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = ['n' => $i, 'url' => $this->pageUrl($i, $tier), 'cur' => $i === $page];
        }
        return $out;
    }

    private function notFound($msg)
    {
        $this->assign('nf404', $msg);
        $this->assign('nf404Back', \app\common\service\Nightfall::castUrl());
        $this->assign('nf404BackText', '演员库');
        return response($this->label_fetch('public/404'), 404);
    }
}
