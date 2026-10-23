<?php
namespace app\common\service;

use think\Db;

/**
 * 详情页关联内容取数（演员 / 导演 / 系列 / 标签）
 * 性能策略：优先在同一个分类内查（type_id 有索引，毫秒级），
 *          不足时用演员名前缀查询（vod_actor 有 191 前缀索引）；
 *          结果按 vod_id 缓存 7 天，避免详情页反复全表扫描。
 */
class Related
{
    const TTL = 604800;

    private static $fields = 'vod_id,type_id,type_id_1,vod_name,vod_sub,vod_en,vod_letter,vod_pic,vod_remarks,vod_year,vod_area,vod_lang,vod_total,vod_serial,vod_score,vod_time_add';

    /* 一次性取齐四个模块，模板里直接调用 */
    public static function forVod($vod, $num = 6)
    {
        $id = isset($vod['vod_id']) ? intval($vod['vod_id']) : 0;
        if ($id <= 0) {
            return ['actor' => [], 'director' => [], 'series' => [], 'tag' => [], 'labels' => []];
        }
        $key    = 'nf_rel_' . $id . '_' . intval($num);
        $cached = cache($key);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }
        $data = [
            'actor'    => self::byActor($vod, $num),
            'director' => self::byDirector($vod, $num),
            'series'   => self::bySeries($vod, $num),
            'tag'      => self::byTag($vod, $num),
            'labels'   => self::labels($vod),
        ];
        cache($key, $data, self::TTL);
        return $data;
    }

    /* 把 "A,B / C" 这类混写拆成干净的名单 */
    public static function names($str, $limit = 2)
    {
        $str = trim((string)$str);
        if ($str === '') {
            return [];
        }
        $parts = preg_split('/[,，、\/|;；]+/u', $str);
        $out   = [];
        foreach ($parts as $p) {
            $p = trim($p, " \t\n\r\0\x0B·.");
            if ($p === '' || mb_strlen($p, 'UTF-8') < 2 || mb_strlen($p, 'UTF-8') > 20) {
                continue;
            }
            $out[$p] = true;
        }
        return array_slice(array_keys($out), 0, $limit);
    }

    public static function labels($vod, $limit = 6)
    {
        $cls = self::names(isset($vod['vod_class']) ? $vod['vod_class'] : '', 12);
        return array_slice($cls, 0, $limit);
    }

    private static function byActor($vod, $num)
    {
        $names = self::names(isset($vod['vod_actor']) ? $vod['vod_actor'] : '', 2);
        if (!$names) {
            return [];
        }
        $id   = intval($vod['vod_id']);
        $type = intval(isset($vod['type_id']) ? $vod['type_id'] : 0);

        $rows = self::likeQuery($id, $type, 'vod_actor', $names, '%s');
        if (count($rows) < $num && count($rows) < 3) {
            $rows = self::mergeRows($rows, self::likeQuery($id, 0, 'vod_actor', $names, 's%'), $num);
        }
        return array_slice($rows, 0, $num);
    }

    private static function byDirector($vod, $num)
    {
        $names = self::names(isset($vod['vod_director']) ? $vod['vod_director'] : '', 2);
        if (!$names) {
            return [];
        }
        $id   = intval($vod['vod_id']);
        $type = intval(isset($vod['type_id']) ? $vod['type_id'] : 0);
        $rows = self::likeQuery($id, $type, 'vod_director', $names, '%s');
        if (count($rows) < 3) {
            $rows = self::mergeRows($rows, self::likeQuery($id, 0, 'vod_director', $names, 's%'), $num);
        }
        return array_slice($rows, 0, $num);
    }

    /* 系列/续作：去掉「第X季 第X部 数字 年份」等后缀后按前缀匹配（走 vod_name 索引） */
    private static function bySeries($vod, $num)
    {
        $name = isset($vod['vod_name']) ? $vod['vod_name'] : '';
        $base = self::baseName($name);
        if (mb_strlen($base, 'UTF-8') < 2 || $base === $name) {
            return [];
        }
        $safe = str_replace(['%', '_'], ['\%', '\_'], $base);
        $rows = Db::name('vod')->field(self::$fields)
            ->where('vod_status', 1)
            ->where('vod_id', '<>', intval($vod['vod_id']))
            ->where('vod_name', 'like', $safe . '%')
            ->order('vod_id desc')->limit($num)->select();
        return $rows ? $rows : [];
    }

    private static function byTag($vod, $num)
    {
        $labels = self::labels($vod, 1);
        if (!$labels) {
            return [];
        }
        $id   = intval($vod['vod_id']);
        $type = intval(isset($vod['type_id']) ? $vod['type_id'] : 0);
        $rows = self::likeQuery($id, $type, 'vod_class', [$labels[0]], '%s');
        return array_slice($rows, 0, $num);
    }

    /**
     * @param string $mode '%s' 表示 %name%（全表扫描风险，需配合 type 限定）
     *                     's%' 表示 name%（可走前缀索引）
     */
    private static function likeQuery($id, $type, $field, $names, $mode)
    {
        $q = Db::name('vod')->field(self::$fields)
            ->where('vod_status', 1)
            ->where('vod_id', '<>', $id);
        if ($type > 0) {
            $q->where('type_id', $type);
        }
        $q->where(function ($w) use ($field, $names, $mode) {
            foreach ($names as $n) {
                $safe = str_replace(['%', '_'], ['\%', '\_'], $n);
                $w->whereOr($field, 'like', ($mode === '%s') ? '%' . $safe . '%' : $safe . '%');
            }
        });
        /* 不加 ORDER BY：LIKE 无法走索引，排序会退化成全类型扫描+filesort（实测 0.6s → 0.02s） */
        $rows = $q->limit(12)->select();
        return $rows ? $rows : [];
    }

    private static function mergeRows($a, $b, $num)
    {
        $seen = [];
        foreach ($a as $r) {
            $seen[intval($r['vod_id'])] = true;
        }
        foreach ($b as $r) {
            $vid = intval($r['vod_id']);
            if (isset($seen[$vid])) {
                continue;
            }
            $seen[$vid] = true;
            $a[]        = $r;
            if (count($a) >= $num) {
                break;
            }
        }
        return $a;
    }

    public static function baseName($name)
    {
        $n = preg_replace('/[（(【\[].*?[)）】\]]/u', '', (string)$name);
        $n = preg_replace('/(第[0-9一二三四五六七八九十百]+[季部集期篇])/u', '', $n);
        $n = preg_replace('/(特别篇|剧场版|完结篇|番外|特辑|全集)/u', '', $n);
        $n = preg_replace('/[\s\-—_·:：.。]*[0-9]{1,4}\s*$/u', '', $n);
        return trim($n, " \t\n\r\0\x0B·.:：-_/");
    }
}
