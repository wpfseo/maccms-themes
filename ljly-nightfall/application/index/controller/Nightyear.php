<?php
namespace app\index\controller;

use think\Db;

/**
 * 年份榜单（P2）：年份 + 类型交叉的年度片单
 *
 * URL：/year.html（年份总览）、/year/2026.html、/year/2026-action-movie.html、分页 …/2.html
 * 类型的英文段直接用 mac_type.type_en，无需额外映射
 */
class Nightyear extends Base
{
    const PAGE_SIZE = 36;
    const YMIN = 1990;
    const YMAX = 2035;

    public function index()
    {
        $years = cache('nf_years');
        if (!is_array($years) || !$years) {
            $rows  = Db::query(
                'SELECT vod_year, COUNT(*) AS c FROM mac_vod '
                . 'WHERE vod_status = 1 AND vod_year BETWEEN ? AND ? '
                . 'GROUP BY vod_year ORDER BY vod_year DESC',
                [self::YMIN, self::YMAX]
            );
            $years = [];
            foreach ($rows as $r) {
                if (!preg_match('/^\d{4}$/', (string)$r['vod_year'])) continue;
                $years[] = ['year' => $r['vod_year'], 'count' => (int)$r['c'], 'url' => \app\common\service\Nightfall::yearUrl($r['vod_year'])];
            }
            cache('nf_years', $years, 21600);
        }
        $this->assign('nfYears', $years);
        $this->assign('nfYearTotal', count($years));
        return $this->label_fetch('year/index');
    }

    public function detail()
    {
        /* URL 段形如 2026 或 2026-action-movie */
        $key   = trim((string)input('param.k', ''));
        $year  = substr($key, 0, 4);
        $tslug = strlen($key) > 5 ? strtolower(substr($key, 5)) : '';
        if (!preg_match('/^(19|20)\d{2}$/', $year) || (int)$year < self::YMIN || (int)$year > self::YMAX) {
            return $this->notFound('年份参数不正确。');
        }
        if ($tslug !== '' && !preg_match('/^[a-z0-9\-]{1,40}$/', $tslug)) {
            return $this->notFound('分类参数不正确。');
        }

        $type = null;
        if ($tslug !== '') {
            $ck = 'nf_type_en_' . md5($tslug);
            $type = cache($ck);
            if ($type === null || $type === false) {
                $res  = Db::query('SELECT type_id,type_name,type_en,type_pid FROM mac_type WHERE type_en = ? AND type_status = 1 LIMIT 1', [$tslug]);
                $type = $res ? $res[0] : false;
                cache($ck, $type, 86400);
            }
            if (!$type) {
                return $this->notFound('这个年份下没有该分类的片单。');
            }
        }

        $page = max(1, (int)input('param.page', 1));
        $ckd  = 'nf_year_' . $year . '_' . ($tslug === '' ? 'all' : $tslug) . '_' . $page;
        $data = cache($ckd);
        if (!is_array($data) || !$data) {
            $where = 'vod_status = 1 AND vod_year = ?';
            $bind  = [$year];
            if ($type) {
                /* 大类（pid=0）按其下所有子类算，子类直接命中 */
                if ((int)$type['type_pid'] === 0) {
                    $where .= ' AND type_id_1 = ?';
                    $bind[] = (int)$type['type_id'];
                } else {
                    $where .= ' AND type_id = ?';
                    $bind[] = (int)$type['type_id'];
                }
            }
            $cnt   = Db::query('SELECT COUNT(*) AS c FROM mac_vod WHERE ' . $where, $bind);
            $total = (int)$cnt[0]['c'];
            $rows  = Db::query(
                'SELECT vod_id,type_id,type_id_1,vod_name,vod_sub,vod_en,vod_letter,vod_pic,vod_remarks,'
                . 'vod_year,vod_area,vod_lang,vod_total,vod_serial,vod_score,vod_time_add '
                . 'FROM mac_vod WHERE ' . $where . ' ORDER BY vod_id DESC '
                . 'LIMIT ' . (($page - 1) * self::PAGE_SIZE) . ',' . self::PAGE_SIZE,
                $bind
            );
            $types = Db::query(
                'SELECT t.type_id,t.type_name,t.type_en,COUNT(*) AS c FROM mac_vod v '
                . 'INNER JOIN mac_type t ON t.type_id = v.type_id '
                . 'WHERE v.vod_status = 1 AND v.vod_year = ? GROUP BY t.type_id ORDER BY c DESC LIMIT 20',
                [$year]
            );
            $data = compact('total', 'rows', 'types');
            cache($ckd, $data, 3600);
        }
        extract($data);

        foreach ($types as $k => $t) {
            $types[$k]['url'] = \app\common\service\Nightfall::yearUrl($year, $t['type_en']);
        }

        $pages = max(1, (int)ceil($total / self::PAGE_SIZE));
        $list  = [];
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        for ($i = $start; $i <= $end; $i++) {
            $list[] = ['n' => $i, 'url' => \app\common\service\Nightfall::yearUrl($year, $tslug, $i), 'cur' => $i === $page];
        }

        if ($total === 0) {
            return $this->notFound('这一年还没有收录作品。');
        }
        if ($page > $pages) {
            return redirect(\app\common\service\Nightfall::yearUrl($year, $tslug, $pages));
        }
        $yt = $tslug === '' ? '' : $tslug;
        $this->assign('nfYear', $year);
        $this->assign('nfType', $type);
        $this->assign('nfTypeSlug', $tslug);
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfTypes', $types);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $list);
        $this->assign('nfUrlFirst', \app\common\service\Nightfall::yearUrl($year, $yt, 1));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::yearUrl());
        $this->assign('nfUrlPrev', \app\common\service\Nightfall::yearUrl($year, $yt, max(1, $page - 1)));
        $this->assign('nfUrlNext', \app\common\service\Nightfall::yearUrl($year, $yt, min($pages, $page + 1)));
        $this->assign('nfUrlLast', \app\common\service\Nightfall::yearUrl($year, $yt, $pages));
        $this->assign('nfPageNum', $page);
        $this->assign('nfPrevYear', (int)$year - 1 >= self::YMIN ? \app\common\service\Nightfall::yearUrl((string)((int)$year - 1)) : '');
        $this->assign('nfNextYear', (int)$year + 1 <= self::YMAX ? \app\common\service\Nightfall::yearUrl((string)((int)$year + 1)) : '');
        return $this->label_fetch('year/detail');
    }

    private function notFound($msg)
    {
        $this->assign('nf404', $msg);
        $this->assign('nf404Back', \app\common\service\Nightfall::yearUrl());
        $this->assign('nf404BackText', '年份片单');
        return response($this->label_fetch('public/404'), 404);
    }
}
