<?php
namespace app\index\controller;

use think\Db;

/**
 * 浏览中心：字母索引、完结专区、连载专区、长篇榜。
 * 全部使用站内已有数据，不依赖评分/播放量（当前均为 0）。
 */
class Nightbrowse extends Base
{
    const PAGE_SIZE = 24;

    /* 浏览总览 */
    public function index()
    {
        $letters = cache('nf_letters');
        if (!is_array($letters) || !$letters) {
            $rows    = Db::name('vod')->field('vod_letter, COUNT(*) AS c')
                ->where('vod_status', 1)->where('vod_letter', '<>', '')
                ->group('vod_letter')->order('vod_letter asc')->select();
            $letters = [];
            foreach ($rows as $r) {
                $l = strtoupper(trim($r['vod_letter']));
                if ($l === '' || mb_strlen($l) > 1) {
                    continue;
                }
                $letters[] = ['letter' => $l, 'count' => (int)$r['c']];
            }
            cache('nf_letters', $letters, 3600);
        }

        $stats = cache('nf_stats');
        if (!is_array($stats)) {
            $stats = [
                'total'    => (int)Db::name('vod')->where('vod_status', 1)->count(),
                'finished' => (int)Db::name('vod')->where('vod_status', 1)->where('vod_isend', 1)->count(),
                'serial'   => (int)Db::name('vod')->where('vod_status', 1)->where('vod_isend', 0)->count(),
            ];
            cache('nf_stats', $stats, 3600);
        }

        $long = cache('nf_long');
        if (!is_array($long) || !$long) {
            $long = Db::name('vod')->field($this->fields())
                ->where('vod_status', 1)->where('vod_total', '>=', 40)
                ->order('vod_total desc')->limit(12)->select();
            cache('nf_long', $long, 21600);
        }

        $new = cache('nf_new');
        if (!is_array($new) || !$new) {
            $new = Db::name('vod')->field($this->fields())
                ->where('vod_status', 1)->order('vod_id desc')->limit(12)->select();
            cache('nf_new', $new, 600);
        }

        $this->assign('nfLetters', $letters);
        $this->assign('nfStats', $stats);
        $this->assign('nfLong', $long);
        $this->assign('nfNew', $new);
        return $this->label_fetch('browse/index');
    }

    /* 字母索引：/nightbrowse/letter/l/S.html */
    public function letter()
    {
        $l = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string)input('param.l', '')), 0, 1));
        if ($l === '') {
            $this->redirect('index/nightbrowse/index');
            return;
        }
        return $this->listPage(
            [['vod_letter', '=', $l]],
            $l . ' 开头的影片',
            '共 {total} 部片名以 ' . $l . ' 开头的影片。',
            'letter',
            ['l' => $l]
        );
    }

    /* 完结 / 连载：/nightbrowse/state/s/1.html */
    public function state()
    {
        $s = (string)input('param.s', '1');
        $s = $s === '0' ? '0' : '1';
        return $this->listPage(
            [['vod_isend', '=', (int)$s]],
            $s === '1' ? '已完结影片' : '连载中影片',
            $s === '1' ? '共 {total} 部已完结作品，可以一口气看完。' : '共 {total} 部仍在更新的作品。',
            'state',
            ['s' => $s]
        );
    }

    /* 长篇榜：/nightbrowse/long.html */
    public function long()
    {
        return $this->listPage(
            [['vod_total', '>=', 40]],
            '长篇与短剧全集',
            '共 {total} 部 40 集以上的长篇作品，按集数从多到少排列。',
            'long',
            [],
            'vod_total desc'
        );
    }

    private function listPage($where, $heading, $descTpl, $route, $params, $order = 'vod_id desc')
    {
        $page = max(1, (int)input('param.page', 1));

        $countQuery = Db::name('vod')->where('vod_status', 1);
        foreach ($where as $w) {
            $countQuery->where($w[0], $w[1], $w[2]);
        }
        $ck    = 'nf_cnt_' . md5($route . '|' . serialize($where));
        $total = cache($ck);
        if ($total === null || $total === false || $total === '') {
            $total = (int)$countQuery->count();
            cache($ck, $total, 21600);
        }
        $total = (int)$total;

        $rows = [];
        if ($total > 0) {
            $q = Db::name('vod')->field($this->fields())->where('vod_status', 1);
            foreach ($where as $w) {
                $q->where($w[0], $w[1], $w[2]);
            }
            $rows = $q->order($order)->limit(($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE)->select();
        }
        $pages = max(1, (int)ceil($total / self::PAGE_SIZE));

        $link = function ($p) use ($route, $params) {
            $q = $params;
            if ($p > 1) {
                $q['page'] = $p;
            }
            return (string)url('index/nightbrowse/' . $route, $q);
        };
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        $pageList = [];
        for ($i = $start; $i <= $end; $i++) {
            $pageList[] = ['n' => $i, 'url' => $link($i), 'cur' => $i === $page];
        }

        $this->assign('nfHeading', $heading);
        $this->assign('nfDesc', str_replace('{total}', number_format($total), $descTpl));
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $pageList);
        $this->assign('nfUrlFirst', $link(1));
        $this->assign('nfUrlPrev', $link(max(1, $page - 1)));
        $this->assign('nfUrlNext', $link(min($pages, $page + 1)));
        $this->assign('nfUrlLast', $link($pages));
        $this->assign('nfCanonical', \app\common\service\Nightfall::absolute($link($page), \app\common\service\Nightfall::load()));
        $this->assign('nfRoute', $route);
        $this->assign('nfParams', $params);
        return $this->label_fetch('browse/list');
    }

    private function fields()
    {
        return 'vod_id,type_id,type_id_1,vod_name,vod_sub,vod_en,vod_letter,vod_pic,vod_remarks,vod_year,vod_area,vod_lang,vod_total,vod_serial,vod_score,vod_time_add';
    }
}
