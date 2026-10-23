<?php
namespace app\index\controller;

use think\Db;

/**
 * 题材标签频道（P1）
 *
 * 数据来自 tools/build-relations.php 生成的 nf_tag / nf_vod_tag。
 * 标签优先取 vod_tag，为空时退回 vod_class。
 *
 * 建档门槛：>= 100 部作品（Nightfall::AGG_TAG_MIN），低于门槛 404。
 */
class Nighttag extends Base
{
    const PAGE_SIZE = 36;
    const MIN = 100;

    /* 标签总览：按作品数分档的标签云 */
    public function index()
    {
        $tags = cache('nf_tag_all');
        if (!is_array($tags) || !$tags) {
            $tags = Db::query('SELECT name,slug,vod_count FROM nf_tag WHERE vod_count >= ? ORDER BY vod_count DESC, name ASC', [self::MIN]);
            cache('nf_tag_all', $tags, 21600);
        }

        $buckets = [
            ['key' => 'huge', 'label' => '5000 部以上', 'min' => 5000, 'max' => 0, 'items' => []],
            ['key' => 'big', 'label' => '1000–4999 部', 'min' => 1000, 'max' => 4999, 'items' => []],
            ['key' => 'mid', 'label' => '300–999 部', 'min' => 300, 'max' => 999, 'items' => []],
            ['key' => 'small', 'label' => '100–299 部', 'min' => 100, 'max' => 299, 'items' => []],
        ];
        foreach ($tags as $k => $t) {
            $tags[$k]['url'] = \app\common\service\Nightfall::genreUrl($t['slug']);
            $t['url']        = $tags[$k]['url'];
            $c = (int)$t['vod_count'];
            foreach ($buckets as $i => $b) {
                if ($c >= $b['min'] && ($b['max'] === 0 || $c <= $b['max'])) {
                    $buckets[$i]['items'][] = $t;
                    break;
                }
            }
        }

        $this->assign('nfTags', $tags);
        $this->assign('nfBuckets', $buckets);
        $this->assign('nfTagTotal', count($tags));
        $this->assign('nfTagMin', self::MIN);
        return $this->label_fetch('tag/index');
    }

    /* 标签详情：作品列表 + 相关标签 + 类型分布 */
    public function detail()
    {
        $slug = strtolower(trim((string)input('param.name', '')));
        if ($slug === '' || !preg_match('/^[a-z0-9]{1,64}$/', $slug)) {
            return $this->notFound('标签地址不正确。');
        }

        $ck  = 'nf_tag_info_' . md5($slug);
        $tag = cache($ck);
        if ($tag === null || $tag === false) {
            $row = Db::query('SELECT name,slug,vod_count FROM nf_tag WHERE slug = ? AND vod_count >= ? LIMIT 1', [$slug, self::MIN]);
            $tag = $row ? $row[0] : false;
            cache($ck, $tag, 21600);
        }
        if (!$tag) {
            return $this->notFound('这个标签的作品数量还没有达到建档门槛，或地址有误。');
        }
        $name = $tag['name'];

        $page = max(1, (int)input('param.page', 1));
        $total = (int)$tag['vod_count'];

        $rows = [];
        if ($total > 0) {
            $rows = Db::query(
                'SELECT v.vod_id,v.type_id,v.type_id_1,v.vod_name,v.vod_sub,v.vod_en,v.vod_letter,v.vod_pic,v.vod_remarks,'
                . 'v.vod_year,v.vod_area,v.vod_lang,v.vod_total,v.vod_serial,v.vod_score,v.vod_time_add '
                . 'FROM nf_vod_tag a INNER JOIN mac_vod v ON v.vod_id = a.vod_id '
                . 'WHERE a.tag = ? AND v.vod_status = 1 ORDER BY a.vod_id DESC '
                . 'LIMIT ' . (($page - 1) * self::PAGE_SIZE) . ',' . self::PAGE_SIZE,
                [$name]
            );
        }
        $pages    = max(1, (int)ceil($total / self::PAGE_SIZE));
        $pageList = $this->pageList($page, $pages);
        $link     = function ($p) use ($slug) {
            return \app\common\service\Nightfall::genreUrl($slug, $p);
        };

        $related = cache('nf_tag_rel_' . md5($name));
        if (!is_array($related) || !$related) {
            // 先取同标签下的影片（限 3000 部，避免超大标签拖慢），再统计共现标签
            $related = Db::query(
                'SELECT b.tag AS name, f.slug, COUNT(*) AS c FROM nf_vod_tag a '
                . 'INNER JOIN nf_vod_tag b ON b.vod_id = a.vod_id AND b.tag <> a.tag '
                . 'INNER JOIN nf_tag f ON f.name = b.tag AND f.vod_count >= ? '
                . 'WHERE a.tag = ? GROUP BY b.tag, f.slug ORDER BY c DESC, b.tag ASC LIMIT 16',
                [self::MIN, $name]
            );
            cache('nf_tag_rel_' . md5($name), $related, 604800);
        }

        $types = cache('nf_tag_types_' . md5($name));
        if (!is_array($types) || !$types) {
            $types = Db::query(
                'SELECT v.type_id, v.type_id_1, t.type_name, t.type_mid, t.type_en, COUNT(*) AS c FROM nf_vod_tag a '
                . 'INNER JOIN mac_vod v ON v.vod_id = a.vod_id INNER JOIN mac_type t ON t.type_id = v.type_id '
                . 'WHERE a.tag = ? AND v.vod_status = 1 GROUP BY v.type_id ORDER BY c DESC LIMIT 10',
                [$name]
            );
            cache('nf_tag_types_' . md5($name), $types, 21600);
        }

        foreach ($related as $k => $r) {
            $related[$k]['url'] = \app\common\service\Nightfall::genreUrl($r['slug']);
        }

        $this->assign('nfTag', $tag);
        $this->assign('nfRows', $rows);
        $this->assign('nfTotal', $total);
        $this->assign('nfRelated', $related);
        $this->assign('nfTypes', $types);
        $this->assign('nfPageCur', $page);
        $this->assign('nfPageMax', $pages);
        $this->assign('nfPageList', $pageList);
        $this->assign('nfUrlFirst', $link(1));
        $this->assign('nfUrlPrev', $link(max(1, $page - 1)));
        $this->assign('nfUrlNext', $link(min($pages, $page + 1)));
        $this->assign('nfUrlLast', $link($pages));
        $this->assign('nfUrlIndex', \app\common\service\Nightfall::genreUrl());
        $this->assign('nfPageNum', $page);
        return $this->label_fetch('tag/detail');
    }

    private function pageUrl($p)
    {
        return \app\common\service\Nightfall::genreUrl('', $p);
    }

    private function pageList($page, $pages)
    {
        $start = max(1, $page - 3);
        $end   = min($pages, $start + 6);
        $start = max(1, $end - 6);
        $out   = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = ['n' => $i, 'url' => $this->pageUrl($i), 'cur' => $i === $page];
        }
        return $out;
    }

    private function notFound($msg)
    {
        $this->assign('nf404', $msg);
        $this->assign('nf404Back', \app\common\service\Nightfall::genreUrl());
        $this->assign('nf404BackText', '题材标签');
        return response($this->label_fetch('public/404'), 404);
    }
}
