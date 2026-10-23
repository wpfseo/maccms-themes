<?php
namespace app\index\controller;
use app\common\service\Nightfall;

/**
 * 专题页：按「题材母题」组织的高分片单。
 * URL：/topic.html、/topic/fuchou.html
 * 数据来自 tools/build-topic.php 写入的 mac_topic。
 */
class Nighttopic extends Base
{
    public function index(){
        $topics=Nightfall::topicList();
        $this->assign('nfTopics',$topics);
        $this->assign('nfCount',count($topics));
        return $this->label_fetch('topic/index');
    }

    public function detail($key=''){
        try{ return $this->_detail($key); }
        catch(\Throwable $e){
            @file_put_contents('/tmp/topic_err.log', date('c')." key=$key\n".$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString()."\n\n", FILE_APPEND);
            abort(404,'专题不存在');
        }
    }
    public function _detail($key=''){
        if($key==='')$key=(string)input('param.key','');
        $key=preg_replace('/\.html$/i','',trim((string)$key));
        $topic=Nightfall::topicDetail($key);
        if(!$topic)abort(404,'专题不存在');
        if(empty($topic['vod_list']))abort(404,'专题不存在');
        $rel=[];
        foreach(Nightfall::topicList() as $t){
            if($t['topic_en']===$topic['topic_en'])continue;
            $rel[]=$t;
        }
        $this->assign('obj',$topic);
        $this->assign('nfTopic',$topic);
        $this->assign('nfRel',array_slice($rel,0,12));
        return $this->label_fetch('topic/detail');
    }

    public function sitemap(){
        $c=Nightfall::load();if(!$c['origin'])abort(503,'请先设置规范域名');
        $urls=[Nightfall::absolute('/topic.html',$c)];
        foreach(Nightfall::topicList() as $t)$urls[]=Nightfall::absolute($t['url'],$c);
        $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach(array_unique($urls) as $url)$xml.='<url><loc>'.htmlspecialchars($url,ENT_XML1,'UTF-8').'</loc></url>';
        return response($xml.'</urlset>',200,['Content-Type'=>'application/xml; charset=utf-8']);
    }
}
