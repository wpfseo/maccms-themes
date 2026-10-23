<?php
namespace app\index\controller;
use app\common\service\Nightfall;

/** 榜单页：按「分类 × 年份」自动生成，排序依据豆瓣评分 */
class Nightrank extends Base
{
    public function index(){
        $boards=Nightfall::rankBoards();
        $byYear=[];
        foreach($boards as $b)$byYear[$b['year']][]=$b;
        $groupList=[];
        foreach($byYear as $y=>$bs)$groupList[]=['year'=>$y,'boards'=>$bs];
        $this->assign('nfBoards',$boards);
        $this->assign('nfGroupList',$groupList);
        return $this->label_fetch('rank/index');
    }
    public function show($key=''){
        if($key==='')$key=(string)input('param.key','');
        $key=preg_replace('/\.html$/i','',trim((string)$key));
        $board=Nightfall::rankBoard($key);
        if(!$board)abort(404,'榜单不存在');
        $rows=Nightfall::rankRows($board);
        if(count($rows)<Nightfall::RANK_MIN)abort(404,'榜单不存在');
        $relYear=[];$relType=[];
        foreach(Nightfall::rankBoards() as $b){
            if($b['slug']===$board['slug'])continue;
            if($b['type_id']===$board['type_id'])$relYear[]=$b;
            elseif($b['year']===$board['year'])$relType[]=$b;
        }
        $this->assign('nfBoard',$board);
        $this->assign('nfRows',$rows);
        $this->assign('nfRelYear',$relYear);
        $this->assign('nfRelType',array_slice($relType,0,16));
        return $this->label_fetch('rank/detail');
    }
    public function sitemap(){
        $c=Nightfall::load();if(!$c['origin'])abort(503,'请先设置新站规范域名');
        $urls=[Nightfall::absolute('/', $c),Nightfall::absolute('/rank.html',$c)];
        foreach(Nightfall::rankBoards() as $b)$urls[]=Nightfall::absolute($b['url'],$c);
        $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach(array_unique($urls) as $url)$xml.='<url><loc>'.htmlspecialchars($url,ENT_XML1,'UTF-8').'</loc></url>';
        return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=utf-8']);
    }
}
