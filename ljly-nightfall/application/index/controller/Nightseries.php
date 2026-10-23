<?php
namespace app\index\controller;

use think\Db;

/**
 * 系列片单（P2）：同名多部自动成系列
 *
 * 数据来自 tools/build-p2.php 生成的 nf_series（非电影大类，同名 >= 3 部）。
 * URL：/series.html、/series/{拼音}.html、分页 /series/{拼音}/2.html
 */
class Nightseries extends Base
{
    const PAGE_SIZE = 30;
    const INDEX_SIZE = 60;

    public function index()
    {
        $page = max(1, (int)input('param.page', 1));
        $ck   = 'nf_series_idx_' . $page;
        $data = cache($ck);
        if (!is_array($data) || !$data) {
            $cnt   = Db::query('SELECT COUNT(*) AS c FROM nf_series WHERE slug <> ?', ['']);
            $total = (int)$cnt[0]['c'];
            $rows  = Db::query(
                'SELECT s.name,s.slug,s.vod_count,t.type_name FROM nf_series s '
                . 'LEFT JOIN mac_type t ON t.type_id = s.type_id '
                . 'WHERE s.slug <> ? ORDER BY s.vod_count DESC, s.name ASC LIMIT '
                . (($page - 1) * self::INDEX_SIZE) . ',' . self::INDEX_SIZE,
                ['']
            );
            $data = compact('total', 'rows');
            cache($ck, $data, 21600);
        }
        extract($data);
        foreach ($rows as $k => $r) {
            $rows[$k]['url'] = \app\common\service\Nightfall::seriesUrl($r['slug']);
        }

        $pages = max(1, (int)ceil($total / self::INDEX_SIZE));
        $list  = [];
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        for ($i = $start; $i <= $end; $i++) {
            $list[] = ['n' => $i, 'url' => \app\common\service\Nightfall::seriesUrl('', $i), 'cur' => $i === $page];
        }

        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $list);
        $this->assign('nfUrlFirst', \app\common\service\Nightfall::seriesUrl('', 1));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::seriesUrl('', 1));
        $this->assign('nfUrlPrev', \app\common\service\Nightfall::seriesUrl('', max(1, $page - 1)));
        $this->assign('nfUrlNext', \app\common\service\Nightfall::seriesUrl('', min($pages, $page + 1)));
        $this->assign('nfUrlLast', \app\common\service\Nightfall::seriesUrl('', $pages));
        $this->assign('nfPageNum', $page);
        return $this->label_fetch('series/index');
    }

    public function detail()
    {
        $slug = strtolower(trim((string)input('param.name', '')));
        if ($slug === '' || !preg_match('/^[a-z0-9]{1,64}$/', $slug)) {
            return $this->notFound('系列地址不正确。');
        }

        $ck  = 'nf_series_' . md5($slug);
        $row = cache($ck);
        if ($row === null || $row === false) {
            $res = Db::query(
                'SELECT s.name,s.slug,s.vod_count,s.type_id,t.type_name,t.type_en FROM nf_series s '
                . 'LEFT JOIN mac_type t ON t.type_id = s.type_id WHERE s.slug = ? LIMIT 1',
                [$slug]
            );
            $row = $res ? $res[0] : false;
            cache($ck, $row, 21600);
        }
        if (!$row) {
            return $this->notFound('没有找到这个系列，或它的作品数量还不够成页。');
        }

        $page = max(1, (int)input('param.page', 1));
        $ckr  = 'nf_series_rows_' . md5($slug) . '_' . $page;
        $data = cache($ckr);
        if (!is_array($data) || !$data) {
            $cnt   = Db::query('SELECT COUNT(*) AS c FROM mac_vod WHERE vod_status = 1 AND vod_name = ?', [$row['name']]);
            $total = (int)$cnt[0]['c'];
            $rows  = Db::query(
                'SELECT vod_id,type_id,type_id_1,vod_name,vod_sub,vod_en,vod_letter,vod_pic,vod_remarks,'
                . 'vod_year,vod_area,vod_lang,vod_total,vod_serial,vod_score,vod_time_add '
                . 'FROM mac_vod WHERE vod_status = 1 AND vod_name = ? '
                . 'ORDER BY vod_year ASC, vod_id ASC LIMIT '
                . (($page - 1) * self::PAGE_SIZE) . ',' . self::PAGE_SIZE,
                [$row['name']]
            );
            $data = compact('total', 'rows');
            cache($ckr, $data, 7200);
        }
        extract($data);

        $rel = cache('nf_series_rel_' . md5($slug));
        if (!is_array($rel) || !$rel) {
            $rel = Db::query(
                'SELECT name,slug,vod_count FROM nf_series WHERE type_id = ? AND slug <> ? AND slug <> \'\' '
                . 'ORDER BY vod_count DESC LIMIT 14',
                [(int)$row['type_id'], $slug]
            );
            cache('nf_series_rel_' . md5($slug), $rel, 86400);
        }
        foreach ($rel as $k => $r) {
            $rel[$k]['url'] = \app\common\service\Nightfall::seriesUrl($r['slug']);
        }

        $pages = max(1, (int)ceil($total / self::PAGE_SIZE));
        $list  = [];
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        for ($i = $start; $i <= $end; $i++) {
            $list[] = ['n' => $i, 'url' => \app\common\service\Nightfall::seriesUrl($slug, $i), 'cur' => $i === $page];
        }

        if ($page > $pages) {
            return redirect(\app\common\service\Nightfall::seriesUrl($slug, $pages));
        }

        $this->assign('nfSeries', $row);
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfRelated', $rel);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $list);
        $this->assign('nfUrlFirst', \app\common\service\Nightfall::seriesUrl($slug, 1));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::seriesUrl());
        $this->assign('nfUrlPrev', \app\common\service\Nightfall::seriesUrl($slug, max(1, $page - 1)));
        $this->assign('nfUrlNext', \app\common\service\Nightfall::seriesUrl($slug, min($pages, $page + 1)));
        $this->assign('nfUrlLast', \app\common\service\Nightfall::seriesUrl($slug, $pages));
        $this->assign('nfPageNum', $page);
        return $this->label_fetch('series/detail');
    }

    private function notFound($msg)
    {
        $this->assign('nf404', $msg);
        $this->assign('nf404Back', \app\common\service\Nightfall::seriesUrl());
        $this->assign('nf404BackText', '系列片单');
        return response($this->label_fetch('public/404'), 404);
    }
}
