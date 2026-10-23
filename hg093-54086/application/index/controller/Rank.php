<?php
namespace app\index\controller;

use app\common\service\RankCatalog;
use think\Cache;

class Rank extends Base
{
    private $rankTypes;
    public function __construct()
    {
        // The framework's default string input filter throws on array parameters.
        // Reject malformed rank input before the inherited page initialization.
        if(array_key_exists('sort',$_GET)&&!is_string($_GET['sort']))abort(404,'榜单排序不存在');
        parent::__construct();
    }
    private function catalog()
    {
        return RankCatalog::connect(ROOT_PATH);
    }

    private function results($catalog, $board, $sort)
    {
        $key='rankings_v2_'.substr($catalog->revision(),0,8).'_'.hash('sha256',json_encode([$board,$sort]));
        $rows=Cache::get($key);
        if (!is_array($rows)) {$rows=$catalog->rows($board,$sort,10);Cache::set($key,$rows,21600);}
        if($this->rankTypes===null){$this->rankTypes=[];foreach($catalog->categories() as $type)$this->rankTypes[$type['type_id']]=$type;}
        foreach ($rows as &$row) {
            $row['type']=$this->rankTypes[$row['type_id']] ?? [];
            $row['type_1']=$this->rankTypes[$row['type_id_1']] ?? [];
            $row['url']=mac_url_vod_detail($row);
            $row['reason']=$sort==='manual'?($board['reasons'][$row['vod_id']] ?? ''):'';
            $row['vod_year']=(int)$row['vod_year']>0?$row['vod_year']:'';
        }
        unset($row);
        return $rows;
    }

    private function common($config, $active='')
    {
        $boards=array_values(array_filter($config['boards'],function($b){return $b['enabled'];}));
        $groups=[];
        foreach (['分类','题材','演员','地区','年份','导演','组合'] as $group) {
            $items=array_values(array_filter($boards,function($b)use($group){return $b['group']===$group;}));
            if ($items) $groups[]=['name'=>$group,'boards'=>$items];
        }
        $this->assign(['rank_groups'=>$groups,'rank_active'=>$active,'rank_home'=>'/rank.html']);
        return $boards;
    }

    public function index()
    {
        $catalog=$this->catalog();$config=$catalog->load();$boards=$this->common($config);
        $cards=[];
        foreach ($boards as $board) {
            $rows=$this->results($catalog,$board,$board['default_sort']);
            if (!$rows) continue;
            $board['rows']=array_slice($rows,0,$config['index']['preview']);
            $board['url']='/rank/'.$board['slug'].'.html';$cards[]=$board;
        }
        $this->assign(['rank_cards'=>$cards,'rank_heading'=>$config['index']['name'],'rank_intro'=>$config['index']['description'],
            'rank_meta'=>['title'=>$config['index']['title'],'keywords'=>$config['index']['keywords'],'description'=>$config['index']['description'],'path'=>'/rank.html','noindex'=>false,'image'=>'']]);
        return $this->fetch('rank/index');
    }

    // ThinkPHP's existing controller/action dispatch supports /rank/<slug>.html.
    // No edits to generated CMS routing, Nginx or the protected PC display logic.
    public function _empty($name='')
    {
        $slug=(string)request()->action();
        if (!preg_match('/^[a-z][a-z0-9-]{1,59}$/D',$slug)) abort(404,'榜单不存在');
        $catalog=$this->catalog();$config=$catalog->load();$boards=$this->common($config,$slug);$board=null;
        foreach ($boards as $item) if ($item['slug']===$slug) {$board=$item;break;}
        if (!$board) abort(404,'榜单不存在');
        $sort=request()->get('sort','');
        if(!is_string($sort))abort(404,'榜单排序不存在');
        $allowed=['updated'];if($board['manual_ids'])array_unshift($allowed,'manual');
        if($config['index']['enable_hits'])$allowed[]='hits';if($config['index']['enable_score'])$allowed[]='score';
        if ($sort!=='' && !in_array($sort,$allowed,true)) abort(404,'榜单排序不存在');
        $sort=$sort ?: $board['default_sort'];
        if (!in_array($sort,$allowed,true)) abort(404,'榜单暂不可用');
        $path='/rank/'.$slug.'.html';
        if (request()->get('sort','')===$board['default_sort']) return redirect($path,301);
        $rows=$this->results($catalog,$board,$sort);
        if (!$rows) abort(404,'暂无符合条件的影片');
        $labels=['manual'=>'编辑推荐','hits'=>'热门排行','updated'=>'最近更新','score'=>'评分排行'];
        $rules=['manual'=>'本站编辑推荐榜：按作品特点与观影体验人工选片、排列，不代表全网评分、票房或播放量排名。',
            'hits'=>'按本站记录的累计观看次数排列，不代表全网热度，也不代表本周或本月数据。',
            'updated'=>'按本站影片记录的更新时间由新到旧排列，不代表上映时间或口碑高低。',
            'score'=>'按本站有效评分从高到低排列，不冒充豆瓣评分或其他平台官方榜单。'];
        $tabs=[];foreach($allowed as $value)$tabs[]=['name'=>$labels[$value],'active'=>$sort===$value,'url'=>$path.($value===$board['default_sort']?'':'?sort='.$value)];
        $heading=$board['name'].($sort===$board['default_sort']&&count($rows)===10?'前十名':'');
        $title=$board['title'] ?: $board['name'];
        if(count($rows)<10)$title=str_replace(['前十名','十大'],['精选片单','精选'],$title);
        if($sort!==$board['default_sort']){$heading=$board['name'].' · '.$labels[$sort];$title=$board['name'].' - '.$labels[$sort];}
        $related=array_slice(array_values(array_filter($boards,function($b)use($board){return $b['slug']!==$board['slug']&&($b['channel']===$board['channel']||$b['group']===$board['group']);})),0,8);
        $timestamp=max(array_merge([$board['updated_at']],array_map(function($r){return min(time(),(int)$r['vod_time']);},$rows)));
        $intro=$board['intro'] ?: $board['description'];$guides=[];
        if($sort==='manual'){
            $byId=[];foreach($rows as $film)$byId[$film['vod_id']]=$film;
            foreach($board['guide_items'] as $guide){$films=[];foreach($guide['film_ids'] as $id)if(isset($byId[$id]))$films[]=$byId[$id];
                if($guide['film_ids']&&!$films)continue;$guide['films']=$films;$guides[]=$guide;
            }
        }else{
            $examples=array_map(function($film){return '《'.$film['vod_name'].'》';},array_slice($rows,0,3));
            $intro='想找'.str_replace(['排行榜','更新榜'],['',''],$board['name']).'看什么？本页汇集'.implode('、',$examples).'等作品，可从下方片单查看影片介绍和播放信息，继续寻找合自己口味的电影或剧集。';
        }
        $this->assign(['rank_board'=>$board,'rank_rows'=>$rows,'rank_heading'=>$heading,'rank_intro'=>$intro,'rank_guides'=>$guides,
            'rank_rule'=>$rules[$sort],'rank_updated'=>date('Y-m-d H:i',$timestamp),'rank_tabs'=>$tabs,'rank_related'=>$related,
            'rank_meta'=>['title'=>$title,'keywords'=>$board['keywords'],'description'=>$board['description'],'path'=>$path,'noindex'=>$sort!==$board['default_sort'],'image'=>$rows[0]['vod_pic']]]);
        return $this->fetch('rank/detail');
    }
}
