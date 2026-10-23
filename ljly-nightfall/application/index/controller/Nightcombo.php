<?php
namespace app\index\controller;

use think\Db;

/**
 * 组合标签（P2）：双标签交叉页
 *
 * 数据来自 tools/build-p2.php 生成的 nf_combo（共现 >= 300 部才建页）。
 * URL：/combo.html、/combo/juqing-aiqing.html、/combo/juqing-aiqing/2.html
 */
class Nightcombo extends Base
{
    const PAGE_SIZE = 36;
    const MIN = 300;

    /* 组合总览：按第一个标签分组 */
    public function index()
    {
        $all = cache('nf_combo_all');
        if (!is_array($all) || !$all) {
            $all = Db::query('SELECT a,b,a_slug,b_slug,vod_count FROM nf_combo WHERE vod_count >= ? ORDER BY a_slug, vod_count DESC', [self::MIN]);
            cache('nf_combo_all', $all, 21600);
        }

        $groups = [];
        foreach ($all as $r) {
            $k = $r['a_slug'];
            if (!isset($groups[$k])) {
                $groups[$k] = ['name' => $r['a'], 'slug' => $k, 'url' => \app\common\service\Nightfall::genreUrl($k), 'items' => []];
            }
            $groups[$k]['items'][] = [
                'label' => $r['a'] . ' + ' . $r['b'],
                'url'   => \app\common\service\Nightfall::comboUrl($r['a_slug'], $r['b_slug']),
                'count' => (int)$r['vod_count'],
            ];
        }
        /* 组合多的标签排前面 */
        usort($groups, function ($x, $y) { return count($y['items']) - count($x['items']); });

        $this->assign('nfGroups', $groups);
        $this->assign('nfComboTotal', count($all));
        $this->assign('nfComboMin', self::MIN);
        return $this->label_fetch('combo/index');
    }

    public function detail()
    {
        /* URL 段形如 juqing-aiqing，slug 本身不含连字符，按第一个 - 拆开 */
        $key   = strtolower(trim((string)input('param.k', '')));
        $parts = explode('-', $key, 2);
        $a = $parts[0];
        $b = isset($parts[1]) ? $parts[1] : '';
        if (!preg_match('/^[a-z0-9]{1,64}$/', $a) || !preg_match('/^[a-z0-9]{1,64}$/', $b)) {
            return $this->notFound('组合地址不正确。');
        }

        $ck  = 'nf_combo_' . md5($a . '-' . $b);
        $row = cache($ck);
        if ($row === null || $row === false) {
            $res = Db::query('SELECT a,b,a_slug,b_slug,vod_count FROM nf_combo WHERE a_slug=? AND b_slug=? LIMIT 1', [$a, $b]);
            $row = $res ? $res[0] : false;
            cache($ck, $row, 21600);
        }
        if (!$row) {
            return $this->notFound('这个标签组合收录的作品还不够多，或地址有误。');
        }

        $page  = max(1, (int)input('param.page', 1));
        $total = (int)$row['vod_count'];
        $rows  = Db::query(
            'SELECT v.vod_id,v.type_id,v.type_id_1,v.vod_name,v.vod_sub,v.vod_en,v.vod_letter,v.vod_pic,v.vod_remarks,'
            . 'v.vod_year,v.vod_area,v.vod_lang,v.vod_total,v.vod_serial,v.vod_score,v.vod_time_add '
            . 'FROM nf_vod_tag x '
            . 'INNER JOIN nf_vod_tag y ON y.vod_id = x.vod_id AND y.tag = ? '
            . 'INNER JOIN mac_vod v ON v.vod_id = x.vod_id '
            . 'WHERE x.tag = ? AND v.vod_status = 1 ORDER BY x.vod_id DESC '
            . 'LIMIT ' . (($page - 1) * self::PAGE_SIZE) . ',' . self::PAGE_SIZE,
            [$row['b'], $row['a']]
        );

        /* 相邻组合：换一个搭档 */
        $rel = cache('nf_combo_rel_' . md5($a . $b));
        if (!is_array($rel) || !$rel) {
            $rel = Db::query(
                'SELECT a,b,a_slug,b_slug,vod_count FROM nf_combo '
                . 'WHERE (a_slug = ? AND b_slug <> ?) OR (b_slug = ? AND a_slug <> ?) '
                . 'ORDER BY vod_count DESC LIMIT 14',
                [$a, $b, $a, $b]
            );
            cache('nf_combo_rel_' . md5($a . $b), $rel, 86400);
        }
        foreach ($rel as $k => $r) {
            $rel[$k]['label'] = $r['a'] . ' + ' . $r['b'];
            $rel[$k]['url']   = \app\common\service\Nightfall::comboUrl($r['a_slug'], $r['b_slug']);
        }

        $pages = max(1, (int)ceil($total / self::PAGE_SIZE));
        $list  = [];
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        for ($i = $start; $i <= $end; $i++) {
            $list[] = ['n' => $i, 'url' => \app\common\service\Nightfall::comboUrl($a, $b, $i), 'cur' => $i === $page];
        }

        if ($page > $pages) {
            return redirect(\app\common\service\Nightfall::comboUrl($a, $b, $pages));
        }

        $this->assign('nfCombo', $row);
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfRelated', $rel);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $list);
        $this->assign('nfUrlFirst', \app\common\service\Nightfall::comboUrl($a, $b, 1));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::comboUrl());
        $this->assign('nfUrlPrev', \app\common\service\Nightfall::comboUrl($a, $b, max(1, $page - 1)));
        $this->assign('nfUrlNext', \app\common\service\Nightfall::comboUrl($a, $b, min($pages, $page + 1)));
        $this->assign('nfUrlLast', \app\common\service\Nightfall::comboUrl($a, $b, $pages));
        $this->assign('nfPageNum', $page);
        return $this->label_fetch('combo/detail');
    }

    private function notFound($msg)
    {
        $this->assign('nf404', $msg);
        $this->assign('nf404Back', \app\common\service\Nightfall::comboUrl());
        $this->assign('nf404BackText', '标签组合');
        return response($this->label_fetch('public/404'), 404);
    }
}
