<?php
namespace app\common\service;

use think\Db;

/** Original, domain-independent theme support. No remote configuration or licensing. */
class Nightfall
{
    const GUARD = "<?php http_response_code(404); exit; ?>\n";
    /* 聚合页建档门槛：演员>=N 部作品、标签>=M 部作品、导演>=K 部作品才允许建页 */
    const AGG_MIN = 10;
    const AGG_TAG_MIN = 100;
    const AGG_DIR_MIN = 10;
    public static function root() { return dirname(__DIR__, 3); }
    public static function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function text($s, $max=600) {
        if (!is_scalar($s) && $s!==null) throw new \InvalidArgumentException('文本格式错误');
        $s=trim(preg_replace('/\s+/u',' ',strip_tags((string)$s)));
        if(mb_strlen($s,'UTF-8')>$max)$s=mb_substr($s,0,$max,'UTF-8');
        return $s;
    }
    public static function defaults() {
        return ['version'=>1,'brand'=>'夜映','origin'=>'','tagline'=>'把好故事，留给今晚。',
            'home_title'=>'电影电视剧推荐 - 高清影视与精选片单',
            'home_keywords'=>'电影推荐,电视剧推荐,影视排行榜,精选专题',
            'home_description'=>'发现值得看的电影与电视剧，按题材、地区和年份挑选作品，浏览编辑精选专题和排行榜。',
            'detail_pattern'=>'《{name}{year}》免费全集在线观看高清 - {type} - {site}',
            'play_pattern'=>'《{name}{year}》{episode}免费高清在线观看 - {type} - {site}',
            'list_pattern'=>'{year}{area}{genre}{type}{lang} - 高清免费在线观看 - {site}',
            'footer'=>'内容由站点管理者维护。请依法使用拥有传播授权的影片与图片。',
            'logo'=>'','share_image'=>'','movie_type_ids'=>[],'nav'=>[],
            'home_type_ids'=>[],'hero_ids'=>[],'featured_ids'=>[],'show_topics'=>true,'show_rankings'=>true,
            'index_filtered'=>true,'boards'=>[]];
    }
    public static function ids($v,$max=50) {
        if(!is_array($v))$v=explode(',',(string)$v);
        if(count($v)>$max)throw new \InvalidArgumentException('ID数量超限');
        $out=[];foreach($v as $id){if($id==='')continue;if(!ctype_digit((string)$id)||(int)$id<1)throw new \InvalidArgumentException('ID须为正整数');$out[]=(int)$id;}
        return array_values(array_unique($out));
    }
    public static function link($value,$image=false) {
        $s=trim((string)$value);
        if($s==='')return '';
        if(preg_match('/[\x00-\x20\\\\<>"\']/',$s))throw new \InvalidArgumentException('链接包含非法字符');
        if(strpos($s,'/')===0 && strpos($s,'//')!==0)return $s;
        if(filter_var($s,FILTER_VALIDATE_URL)&&in_array(strtolower(parse_url($s,PHP_URL_SCHEME)),['http','https'],true))return $s;
        throw new \InvalidArgumentException('链接只接受站内绝对路径或HTTP(S)地址');
    }
    public static function normalize($input) {
        if(!is_array($input))throw new \InvalidArgumentException('设置必须为对象');
        $c=self::defaults();
        foreach(['brand','tagline','home_title','home_keywords','home_description','detail_pattern','play_pattern','list_pattern','footer'] as $k)
            if(isset($input[$k]))$c[$k]=self::text($input[$k],$k==='brand'?60:600);
        foreach(['logo','share_image'] as $k)$c[$k]=self::link($input[$k]??'',true);
        $origin=rtrim(trim((string)($input['origin']??'')),'/');
        if($origin!==''&&(!filter_var($origin,FILTER_VALIDATE_URL)||!preg_match('~^https?://[^/?#]+$~iD',$origin)||parse_url($origin,PHP_URL_USER)))throw new \InvalidArgumentException('站点地址须为不带路径的HTTP(S)域名');
        $c['origin']=$origin;
        foreach(['movie_type_ids','home_type_ids','hero_ids','featured_ids'] as $k)$c[$k]=self::ids($input[$k]??[],$k==='hero_ids'?8:50);
        foreach(['show_topics','show_rankings','index_filtered'] as $k)if(array_key_exists($k,$input))$c[$k]=!empty($input[$k]);
        if(!is_array($input['nav']??[])||count($input['nav']??[])>12)throw new \InvalidArgumentException('导航最多12项');
        foreach(($input['nav']??[]) as $n){$name=self::text($n['name']??'',30);$url=self::link($n['url']??'');if($name===''||$url==='')throw new \InvalidArgumentException('导航名称和地址不能为空');$c['nav'][]=['name'=>$name,'url'=>$url];}
        if(!is_array($input['boards']??[])||count($input['boards']??[])>100)throw new \InvalidArgumentException('榜单最多100个');
        $slugs=[];
        foreach(($input['boards']??[]) as $b){
            $slug=(string)($b['slug']??'');if(!preg_match('/^[a-z][a-z0-9-]{1,59}$/D',$slug)||isset($slugs[$slug]))throw new \InvalidArgumentException('榜单地址须唯一、小写英文及短横线');$slugs[$slug]=true;
            $ids=self::ids($b['ids']??[],10);$name=self::text($b['name']??'',80);if(!$ids||$name==='')throw new \InvalidArgumentException('榜单须有名称和1至10个新站影片ID');
            $row=['slug'=>$slug,'name'=>$name,'title'=>self::text($b['title']??$name.' - 编辑推荐片单',160),'keywords'=>self::text($b['keywords']??$name,300),'intro'=>self::text($b['intro']??'',600),'ids'=>$ids,'notes'=>[],'guides'=>[]];
            foreach(($b['notes']??[]) as $id=>$note)if(in_array((int)$id,$ids,true))$row['notes'][(int)$id]=self::text($note,160);
            if(count($b['guides']??[])>6)throw new \InvalidArgumentException('每榜导读最多6组');
            foreach(($b['guides']??[]) as $g){$gids=self::ids($g['ids']??[],3);if(array_diff($gids,$ids))throw new \InvalidArgumentException('导读只能引用本榜影片');$row['guides'][]=['title'=>self::text($g['title']??'',80),'body'=>self::text($g['body']??'',400),'ids'=>$gids];}
            $c['boards'][]=$row;
        }
        return $c;
    }
    public static function path(){return self::root().'/application/data/nightfall/settings.php';}
    public static function revision(){return is_file(self::path())?hash_file('sha256',self::path()):'';}
    public static function load(){
        if(!is_file(self::path()))return self::defaults();
        $raw=file_get_contents(self::path());if(strpos($raw,self::GUARD)!==0)throw new \RuntimeException('配置文件格式损坏');
        $data=json_decode(substr($raw,strlen(self::GUARD)),true);if(json_last_error()!==JSON_ERROR_NONE)throw new \RuntimeException('配置JSON损坏');return self::normalize($data);
    }
    public static function save($data,$revision){
        $data=self::normalize($data);$path=self::path();$dir=dirname($path);
        if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new \RuntimeException('无法建立配置目录');
        $lock=fopen($dir.'/settings.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('无法锁定配置');$tmp=null;
        try{clearstatcache(true,$path);if(!hash_equals(self::revision(),(string)$revision))throw new \RuntimeException('设置已被修改，请刷新后重试');
            $raw=self::GUARD.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_HEX_TAG)."\n";
            $tmp=tempnam($dir,'.night-');if(file_put_contents($tmp,$raw,LOCK_EX)!==strlen($raw))throw new \RuntimeException('写入失败');chmod($tmp,0640);
            if(is_file($path)&&!copy($path,$dir.'/settings.previous.php'))throw new \RuntimeException('备份失败');
            if(!rename($tmp,$path))throw new \RuntimeException('发布失败');$tmp=null;
        }finally{if($tmp&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}return $data;
    }
    public static function absolute($url,$c){
        if(!$url)return '';try{$url=self::link($url);}catch(\Exception $e){return '';}
        return strpos($url,'/')===0?($c['origin']?$c['origin'].$url:''):$url;
    }
    public static function image($url,$c){
        if(function_exists('mac_url_img'))$url=mac_url_img($url);
        if($url&&strpos($url,'//')===0)$url='https:'.$url;
        if($url&&strpos($url,'/')!==0&&!preg_match('~^https?://~i',$url))$url='/'.$url;
        return self::absolute($url,$c);
    }
    public static function route($action,$params=[]){return (string)url($action,$params);}
    /* 演员库 / 题材频道的固定路径（对应 application/route.php 的 cast、genre 规则）
       slug 为拼音，只含 a-z0-9，不含连字符（- 留给页码分隔） */
    public static function castUrl($slug='',$page=1){
        if($slug==='')return $page>1?'/cast/page/'.$page.'.html':'/cast.html';
        return $page>1?'/cast/'.$slug.'-'.$page.'.html':'/cast/'.$slug.'.html';
    }
    public static function genreUrl($slug='',$page=1){
        if($slug==='')return $page>1?'/genre/page/'.$page.'.html':'/genre.html';
        return $page>1?'/genre/'.$slug.'-'.$page.'.html':'/genre/'.$slug.'.html';
    }
    public static function directorUrl($slug='',$page=1){
        if($slug==='')return $page>1?'/director/page/'.$page.'.html':'/director.html';
        return $page>1?'/director/'.$slug.'-'.$page.'.html':'/director/'.$slug.'.html';
    }
    /* 垂直频道：/duanju.html、/duanju/page/2.html */
    public static function channelUrl($key,$page=1){
        return $page>1?'/'.$key.'/page/'.$page.'.html':'/'.$key.'.html';
    }
    /* 组合标签：/combo.html、/combo/juqing-aiqing.html、/combo/juqing-aiqing/2.html
       （topic 被 maccms 自带专题模块占用，这里用 combo） */
    public static function comboUrl($a='',$b='',$page=1){
        if($a===''||$b==='')return $page>1?'/combo/page/'.$page.'.html':'/combo.html';
        return '/combo/'.$a.'-'.$b.($page>1?'-'.$page:'').'.html';
    }
    /* 年份榜单：/year.html、/year/2026.html、/year/2026-dongzuopian.html、…/2.html */
    public static function yearUrl($year='',$typeSlug='',$page=1){
        if($year==='')return $page>1?'/year/page/'.$page.'.html':'/year.html';
        $p='/year/'.$year.($typeSlug!==''?'-'.$typeSlug:'');
        return $p.($page>1?'-'.$page:'').'.html';
    }
    /* 系列片单：/series.html、/series/xxx.html、/series/xxx/2.html */
    public static function seriesUrl($slug='',$page=1){
        if($slug==='')return $page>1?'/series/page/'.$page.'.html':'/series.html';
        return '/series/'.$slug.($page>1?'-'.$page:'').'.html';
    }
    /* ---------- 筛选维度：slug <-> 库内中文值 ----------
       terms.php 由 tools/gen-terms.py 生成，含 area/class/lang 三个维度 */
    private static $terms = null;
    public static function terms(){
        if(self::$terms===null){
            $f=APP_PATH.'data/nightfall/terms.php';
            self::$terms=is_file($f)?(include $f):[];
        }
        return self::$terms;
    }
    public static function termSlug($dim,$value){
        static $m=[];
        if(!isset($m[$dim])){$m[$dim]=[];foreach((self::terms()[$dim]??[]) as $it){$m[$dim][$it['v']]=$it['s'];}}
        return isset($m[$dim][$value])?$m[$dim][$value]:'';
    }
    public static function termValue($dim,$slug){
        static $r=[];
        if(!isset($r[$dim])){$r[$dim]=[];foreach((self::terms()[$dim]??[]) as $it){$r[$dim][$it['s']]=$it['v'];}}
        return isset($r[$dim][$slug])?$r[$dim][$slug]:'';
    }
    /* 把参数字典里的中文筛选值统一换成 slug（避免生成的 URL 又出现中文） */
    public static function paramToSlug($param){
        $q=$param;unset($q['page']);
        foreach(['area','class','lang'] as $d){
            if(!empty($q[$d])){$s=self::termSlug($d,$q[$d]);$q[$d]=$s!==''?$s:'';}
        }
        return $q;
    }
    /* 把 URL 里的中文筛选值替换成 slug（maccms 生成的分页链接会回退成中文） */
    public static function urlSlug($url){
        if($url==='')return $url;
        if(!preg_match('/[\x{4e00}-\x{9fa5}]/u',$url)&&strpos($url,'%')===false)return $url; // 已编码的 URL 也要过一遍
        foreach(['area','class','lang'] as $dim){
            foreach((self::terms()[$dim]??[]) as $it){
                $slug=$it['s'];if($slug==='')continue;
                $url=str_replace([rawurlencode($it['v']),urlencode($it['v']),$it['v']],$slug,$url);
            }
        }
        return $url;
    }
    /* 当前筛选条件下的作品数（用于判断空结果页，空页不收录） */
    public static function filterTotal($obj,$param){
        static $cache=[];
        $tid=(int)($obj['type_id']??0);
        $k=md5($tid.'|'.($param['area']??'').'|'.($param['year']??'').'|'.($param['class']??'').'|'.($param['lang']??''));
        if(isset($cache[$k]))return $cache[$k];
        /* 文件缓存 6 小时：多维组合走 index_merge 较慢，缓存后为 0 成本 */
        $cf=RUNTIME_PATH.'cache/facet/'.substr($k,0,2).'/'.$k.'.php';
        if(is_file($cf)&&time()-filemtime($cf)<21600)return $cache[$k]=(int)include $cf;
        $ids=[$tid];
        try{
            foreach((array)model('Type')->getCache('type_list') as $t){
                if((int)$t['type_id']===$tid||(int)$t['type_pid']===$tid)$ids[]=(int)$t['type_id'];
            }
        }catch(\Exception $e){}
        $ids=array_values(array_unique(array_filter($ids)));
        if(!$ids)return $cache[$k]=0;
        $sql='SELECT COUNT(*) c FROM mac_vod WHERE vod_status=1 AND type_id IN ('.implode(',',$ids).')';
        $q=function($s){return "'".str_replace(['\\',"'"],['\\\\',"\\'"],(string)$s)."'";};
        foreach(['area'=>'vod_area','year'=>'vod_year','lang'=>'vod_lang'] as $pk=>$field){
            $v=trim((string)($param[$pk]??''));
            if($v==='')continue;
            $arr=array_values(array_filter(array_map('trim',explode(',',$v)),'strlen'));
            if(!$arr)continue;
            $quoted=[];
            foreach($arr as $one){$quoted[]=$q($one);}
            $sql.=' AND '.$field.' IN ('.implode(',',$quoted).')';
        }
        $cls=trim((string)($param['class']??''));
        if($cls!==''&&$cls!=='-'){
            $sql.=' AND vod_class LIKE '.$q('%'.$cls.'%');
        }
        try{$rows=Db::query($sql);$n=(int)($rows[0]['c']??0);}catch(\Exception $e){$n=-1;}
        if($n>=0){
            $dir=dirname($cf);
            if(!is_dir($dir))@mkdir($dir,0755,true);
            @file_put_contents($cf,'<?php return '.$n.';');
        }
        return $cache[$k]=$n;
    }
    /* 筛选条：地区/年份/题材/语言，URL 一律拼音 slug；超出 limit 的收进「更多」折叠 */
    public static function filters($obj,$param){
        $dims=[['area','地区',12],['year','年份',12],['lang','语言',10]]; // 题材走独立频道页，LIKE 查询太慢
        $html='';
        foreach($dims as $one){
            list($key,$label,$limit)=$one;
            $html.='<div class="filter-row"><strong>'.self::h($label).'</strong><div class="chips">';
            $base=self::paramToSlug($param);$base[$key]='';
            $html.='<a class="chip'.(empty($param[$key])?' active':'').'" href="'.self::h(mac_url_type($obj,$base,'show')).'">全部</a>';
            $i=0;$more='';
            if($key==='year'){
                $cur=(int)date('Y');
                for($y=$cur;$y>=$cur-11;$y--){
                    $v=(string)$y;$q=$base;$q[$key]=$v;
                    $chip='<a class="chip'.((($param[$key]??'')===$v)?' active':'').'" href="'.self::h(mac_url_type($obj,$q,'show')).'">'.$v.'</a>';
                    if($i<$limit){$html.=$chip;}else{$more.=$chip;}
                    $i++;
                }
            }else{
                foreach((self::terms()[$key]??[]) as $it){
                    $q=$base;$q[$key]=$it['s'];
                    $chip='<a class="chip'.((($param[$key]??'')===$it['v'])?' active':'').'" href="'.self::h(mac_url_type($obj,$q,'show')).'">'.self::h($it['n']).'</a>';
                    if($i<$limit){$html.=$chip;}else{$more.=$chip;}
                    $i++;
                }
            }
            if($more!==''){$html.='<details class="chip-more"><summary>更多 '.(($key==='year')?($cur-12).' 及更早':($i-$limit)).'</summary><div class="chips">'.$more.'</div></details>';}
            $html.='</div></div>';
            if($key==='year'){$html.='<div class="filter-row"><strong>题材</strong><div class="chips"><a class="chip" href="'.self::h(self::genreUrl()).'">全部题材 ↗</a>';$n=0;foreach((self::terms()['class']??[]) as $it){if($n++>=14)break;$html.='<a class="chip" href="'.self::h(self::genreUrl($it['s'])).'">'.self::h($it['n']).'</a>';}$html.='</div></div>';}
        }
        return $html;
    }
    public static function films($ids){
        if(!$ids)return [];$rows=model('Vod')->where('vod_id','in',self::ids($ids))->where('vod_status',1)->select();
        if(is_object($rows)&&method_exists($rows,'toArray'))$rows=$rows->toArray();$map=[];
        foreach($rows as $v){if(is_object($v))$v=$v->toArray();$type=model('Type')->where('type_id',(int)$v['type_id'])->find();if(!$type||(int)$type['type_status']!==1)continue;if(!empty($type['type_pid'])){$parent=model('Type')->where('type_id',(int)$type['type_pid'])->find();if(!$parent||(int)$parent['type_status']!==1)continue;}$map[(int)$v['vod_id']]=$v;}
        $out=[];foreach($ids as $id)if(isset($map[$id]))$out[]=$map[$id];return $out;
    }
    /* ===== 榜单：按「分类 × 年份」自动生成，依据豆瓣评分 ===== */
    const RANK_MIN = 12;
    const RANK_SHOW = 30;
    const RANK_BAN = [37,38,67,68,69,70,71,72,56];
    public static function rankBoards(){
        $rows=Db::name('vod')
            ->field('type_id,vod_year,COUNT(*) c,ROUND(AVG(vod_douban_score),1) avg_rate,MAX(vod_douban_score) max_rate')
            ->where('vod_status',1)->where('vod_douban_score','>=',3)
            ->whereBetween('vod_year',[2020,2030])->whereNotIn('type_id',self::RANK_BAN)
            ->group('type_id,vod_year')->having('c>='.self::RANK_MIN)->select();
        if(is_object($rows)&&method_exists($rows,'toArray'))$rows=$rows->toArray();
        if(!$rows)return [];
        $types=[];
        foreach(Db::name('type')->field('type_id,type_name,type_en,type_pid,type_status')->select() as $t){
            if(is_object($t)&&method_exists($t,'toArray'))$t=$t->toArray();
            $types[(int)$t['type_id']]=$t;
        }
        $out=[];
        foreach($rows as $r){
            if(is_object($r)&&method_exists($r,'toArray'))$r=$r->toArray();
            $tid=(int)$r['type_id'];$y=(int)$r['vod_year'];
            $t=$types[$tid]??null;
            if(!$t||(int)$t['type_status']!==1||trim((string)($t['type_en']??''))==='')continue;
            $en=trim($t['type_en']);$tn=$t['type_name'];$c=(int)$r['c'];
            $out[]=['slug'=>$en.'-'.$y,'type_id'=>$tid,'year'=>$y,'type_en'=>$en,'type_name'=>$tn,'cnt'=>$c,
                'avg'=>$r['avg_rate'],'max'=>$r['max_rate'],
                'name'=>$y.'年'.$tn.'排行榜',
                'title'=>$y.'年'.$tn.'排行榜前十名 - 豆瓣高分推荐',
                'intro'=>$y.'年'.$tn.'中豆瓣评分较高的'.$c.'部，按评分从高到低排列，平均 '.$r['avg_rate'].' 分，最高 '.$r['max_rate'].' 分。',
                'keywords'=>$y.$tn.'排行榜,'.$tn.'推荐,高分'.$tn.','.$y.$tn,
                'url'=>'/rank/'.$en.'-'.$y.'.html'];
        }
        usort($out,function($a,$b){ if($a['year']!==$b['year'])return $b['year']-$a['year']; return $b['cnt']-$a['cnt']; });
        return $out;
    }
    public static function rankBoard($slug){
        $slug=(string)$slug;
        if(!preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/D',$slug))return null;
        foreach(self::rankBoards() as $b)if($b['slug']===$slug)return $b;
        return null;
    }
    public static function rankRows($board,$limit=0){
        if(!$board)return [];
        $limit=$limit>0?$limit:self::RANK_SHOW;
        $rows=Db::name('vod')->where('vod_status',1)
            ->where('type_id',$board['type_id'])->where('vod_year',$board['year'])
            ->where('vod_douban_score','>=',3)
            ->order('vod_douban_score','desc')->order('vod_hits','desc')->order('vod_time_add','desc')
            ->limit($limit*4)->select();
        if(is_object($rows)&&method_exists($rows,'toArray'))$rows=$rows->toArray();
        // 同一部片常以「国语版/英文版/粤语」重复入库，按基础片名去重
        $seen=[];$out=[];
        foreach($rows as $v){
            if(is_object($v)&&method_exists($v,'toArray'))$v=$v->toArray();
            $base=preg_replace('/[（(【\[][^）)】\]]*[）)】\]]/u','',(string)$v['vod_name']);
            $base=preg_replace('/(国语|粤语|英语|原声|中字|HD|BD|高清|完整版|超清|版)$/u','',$base);
            $k=mb_strtolower(preg_replace('/[\s\x{3000}\p{P}\p{S}]+/u','',$base),'UTF-8');
            if($k==='')$k=(string)$v['vod_id'];
            if(isset($seen[$k]))continue;
            $seen[$k]=1;$out[]=$v;
            if(count($out)>=$limit)break;
        }
        return $out;
    }
    public static function episode($obj,$param){$sid=max(1,(int)($param['sid']??1));$nid=max(1,(int)($param['nid']??1));return trim(strip_tags($obj['vod_play_list'][$sid]['urls'][$nid]['name']??''));}
    public static function movie($obj,$c){
        $id=(int)($obj['type_id_1']??0);$tid=(int)($obj['type_id']??0);
        if($c['movie_type_ids'])return in_array($id,$c['movie_type_ids'],true)||in_array($tid,$c['movie_type_ids'],true);
        return preg_match('/电影|影片/u',(string)($obj['type_1']['type_name']??$obj['type']['type_name']??''))===1;
    }
    public static function splitPeople($str,$max=30){
        $str=trim((string)$str);if($str==='')return [];
        $parts=preg_split('/\s*[,，、\/|;；]+\s*/u',$str);$out=[];
        foreach($parts as $p){$p=trim(preg_replace('/\s+/u',' ',$p));if($p===''||mb_strlen($p,'UTF-8')>$max)continue;$out[$p]=1;}
        return array_keys($out);
    }
    /* 详情页演员名 → 有独立演员页的加链接，其余保持纯文本 */
    public static function actorLinks($str){
        return self::aggLinks('nf_actor',self::AGG_MIN,$str,'castUrl');
    }
    /* 详情页导演名 → 有独立导演页的加链接，其余保持纯文本 */
    public static function directorLinks($str){
        return self::aggLinks('nf_director',self::AGG_DIR_MIN,$str,'directorUrl');
    }
    private static function aggLinks($table,$min,$str,$urlMethod){
        $names=self::splitPeople($str);if(!$names)return '';
        $ok=[];
        try{
            $ph=implode(',',array_fill(0,count($names),'?'));
            $rows=Db::query("SELECT name,slug FROM `$table` WHERE vod_count>=? AND name IN ($ph)",array_merge([$min],$names));
            foreach($rows as $r)if($r['slug']!=='')$ok[$r['name']]=$r['slug'];
        }catch(\Exception $e){}
        $out=[];
        foreach($names as $n){
            $out[]=isset($ok[$n])?'<a href="'.self::h(self::$urlMethod($ok[$n])).'" title="'.self::h($n).'的全部作品">'.self::h($n).'</a>':self::h($n);
        }
        return implode(', ',$out);
    }
    /* 详情页头部标签：分类/年份/地区/语言/备注。空值一律不渲染；年份/地区/语言有对应 slug 时输出筛选页链接（拼音 URL），否则退化为纯文本 chip */
    public static function heroChips($obj){
        $o=is_object($obj)?$obj->toArray():(array)$obj;
        $t=(isset($o['type'])&&is_array($o['type']))?$o['type']:[];
        $out='';
        if(!empty($t['type_name']))$out.='<a class="chip" href="'.self::h(mac_url_type($t)).'">'.self::h($t['type_name']).'</a>';
        $year=trim((string)($o['vod_year']??''));
        if($year!==''&&$year!=='0'&&!empty($t))$out.='<a class="chip" href="'.self::h(mac_url_type($t,['year'=>$year],'show')).'">'.self::h($year).'</a>';
        foreach([['area',$o['vod_area']??''],['lang',$o['vod_lang']??'']] as $one){
            $v=trim((string)$one[1]);if($v==='')continue;
            $s=self::termSlug($one[0],$v);
            if($s!==''&&!empty($t))$out.='<a class="chip" href="'.self::h(mac_url_type($t,[$one[0]=>$s],'show')).'">'.self::h($v).'</a>';
            else $out.='<span class="chip">'.self::h($v).'</span>';
        }
        $rm=trim((string)($o['vod_remarks']??''));
        if($rm!=='')$out.='<span class="chip accent">'.self::h($rm).'</span>';
        return $out;
    }
    /* 详情页演职员表：导演/演员/语言一项都没有时不输出整块。
       此前模板只做逐项判断，三项全空会留下一个空的 <dl class="credits">，
       被 CSS 的 margin 撑出一块空白区块（全站约 1.25 万页受影响）。 */
    public static function creditsSection($obj){
        $o=is_object($obj)?$obj->toArray():(array)$obj;
        $rows='';
        $dir=trim((string)($o['vod_director']??''));
        if($dir!==''){
            $html=self::directorLinks($dir);
            if($html==='')$html=self::h($dir);
            $rows.='<div><dt>导演</dt><dd>'.$html.'</dd></div>';
        }
        $act=trim((string)($o['vod_actor']??''));
        if($act!==''){
            $html=self::actorLinks($act);
            if($html==='')$html=self::h($act);
            $rows.='<div><dt>演员</dt><dd>'.$html.'</dd></div>';
        }
        $lang=trim((string)($o['vod_lang']??''));
        if($lang!=='')$rows.='<div><dt>语言</dt><dd>'.self::h($lang).'</dd></div>';
        if($rows==='')return '';
        return '<dl class="credits">'.$rows.'</dl>';
    }
    public static function topicUrl($en=''){ return '/topic/'.trim((string)$en).'.html'; }
    public static function topicList($limit=0){
        $q=Db::name('topic')->where('topic_status',1)->order('topic_sort','desc')->order('topic_time','desc');
        if($limit>0)$q=$q->limit($limit);
        $out=[];
        foreach($q->select() as $t){
            $t=is_object($t)&&method_exists($t,'toArray')?$t->toArray():$t;
            $t['url']=self::topicUrl($t['topic_en']??$t['topic_id']);
            $out[]=$t;
        }
        return $out;
    }
    public static function topicDetail($key){
        if($key==='')return null;
        $t=Db::name('topic')->where('topic_en',$key)->where('topic_status',1)->find();
        if(!$t)return null;
        $t=is_object($t)&&method_exists($t,'toArray')?$t->toArray():$t;
        $t['url']=self::topicUrl($t['topic_en']);
        $ids=[];
        foreach(preg_split('/[,，\s]+/',trim((string)($t['topic_rel_vod']??''))) as $v){
            $v=(int)$v; if($v>0&&!in_array($v,$ids,true))$ids[]=$v;
        }
        $t['vod_list']=[];
        if($ids){
            $rows=Db::name('vod')->where('vod_id','in',$ids)->where('vod_status',1)
                ->orderRaw('FIELD(vod_id,'.implode(',',$ids).')')->select();
            foreach($rows as $v)$t['vod_list'][]=is_object($v)&&method_exists($v,'toArray')?$v->toArray():$v;
        }
        return $t;
    }
    public static function ogtype($page,$obj,$c){
        $t1=(int)($obj['type_id_1']??0);$total=(int)($obj['vod_total']??0);$film=false;$series=false;
        if($t1===2){$film=$total<2;$series=!$film;}
        elseif(in_array($t1,[1,3,4,5,20],true)){$series=true;}
        elseif($t1===0){$film=self::movie($obj,$c);$series=!$film;}
        if($page==='play')return $film?'video.movie':($series?'video.episode':'video.other');
        return $film?'video.movie':($series?'video.tv_show':'video.other');
    }
    public static function meta($page,$site,$obj=[],$param=[],$extra=[]){
        $c=self::load();$brand=$c['brand'];$name=self::text($obj['vod_name']??$obj['topic_name']??'',160);$year=trim((string)($obj['vod_year']??''));$year=preg_match('/^(19|20)\d{2}$/D',$year)?$year:'';
        $type=self::text($obj['type']['type_name']??$obj['type_name']??'',80);$baseType=$type;
        $episode=self::movie($obj,$c)?'':self::episode($obj,$param);
        $replacements=['{name}'=>$name,'{year}'=>$year?'（'.$year.'）':'','{episode}'=>$episode,'{type}'=>$type,'{site}'=>$brand];
        $title=$c['home_title'].' - '.$brand;$description=$c['home_description'];$keywords=$c['home_keywords'];$url='/';$ogtype='website';$image=self::image($c['share_image']?:$c['logo'],$c);$noindex=false;$schema=null;$heading=$c['tagline'];
        if(in_array($page,['detail','play'],true)){
            $title=strtr($c[$page==='detail'?'detail_pattern':'play_pattern'],$replacements);$heading=$name.($episode?' · '.$episode:'');
            /* 有豆瓣评分时把评分前置，作为差异化的点击诱因；无评分不加任何前缀 */
            $nfScore=round((float)($obj['vod_douban_score']??0),1);
            $description=($nfScore>0?'豆瓣'.$nfScore.'分。':'').self::text(mb_substr(self::synopsis($obj),0,170,'UTF-8'),400);
            $keywords=implode(',',array_filter([$name,$type,$year]));$url=$page==='detail'?mac_url_vod_detail($obj):mac_url_vod_play($obj,['sid'=>(int)($param['sid']??1),'nid'=>(int)($param['nid']??1)]);
            $image=self::image($obj['vod_pic']??'',$c)?:$image;$ogtype=self::ogtype($page,$obj,$c);
        }elseif(in_array($page,['type','show'],true)){
            $type=$obj['type_name']??$type;$area=self::text($param['area']??'',40);$filterYear=self::text($param['year']??'',20);$genre=self::text($param['class']??'',30);$flang=self::text($param['lang']??'',20);
            /* 非法/脏参数不参与标题，避免出现「-年zhongguodalu-」这类脏标题 */
            if($area!==''&&self::termSlug('area',$area)==='')$area='';
            if($filterYear!==''&&!preg_match('/^(19|20)\d{2}$/',$filterYear))$filterYear='';
            if($genre!==''&&self::termSlug('class',$genre)==='')$genre='';
            if($flang!==''&&self::termSlug('lang',$flang)==='')$flang='';
            if($genre===$type)$genre='';
            $heading=($filterYear?$filterYear.'年':'').$area.$genre.$type.($flang?'（'.$flang.'）':'');
            $title=strtr($c['list_pattern'],['{year}'=>$filterYear?$filterYear.'年':'','{area}'=>$area,'{genre}'=>$genre,'{type}'=>$type,'{lang}'=>$flang?'（'.$flang.'）':'','{site}'=>$brand]);
            $description=$heading.'影片与剧集推荐，按地区、年份、题材和语言查找作品，查看简介及播放列表。';$keywords=implode(',',array_filter([$type,$area,$filterYear,$genre,$flang]));
            $slugParam=self::paramToSlug($param);$url=$page==='type'?mac_url_type($obj):mac_url_type($obj,$slugParam,'show');
            $filtered=count(array_filter(array_intersect_key($param,array_flip(['area','year','class','lang','letter','by','order']))))>0;$noindex=$filtered&&!$c['index_filtered'];
            if($filtered&&!$noindex&&self::filterTotal($obj,$param)===0)$noindex=true; // 空结果页不收录
            if(!$filtered){if(!empty($obj['type_title']))$title=strtr(self::text($obj['type_title'],160),['{site}'=>$brand,'{type}'=>$type]);if(!empty($obj['type_key']))$keywords=self::text($obj['type_key'],300);if(!empty($obj['type_des']))$description=self::text($obj['type_des'],400);}
        }elseif($page==='search'){$heading='搜索：'.self::text($param['wd']??'',80);$title=$heading.' - '.$brand;$description='站内影视搜索结果。';$url=mac_url('vod/search',['wd'=>$param['wd']??'']);$noindex=true;
        }elseif($page==='catalog'){$heading='发现影视';$title='电影电视剧大全 - 按分类找片 - '.$brand;$url=mac_url('vod/index');
        }elseif($page==='topics'){$heading='精选专题';$title='精选影视专题 - 电影电视剧片单推荐 - '.$brand;$description='按题材母题整理的影视片单，从复仇、卧底到时间循环，每个专题按豆瓣评分筛出值得先看的那几部。';$url='/topic.html';
        }elseif($page==='topic'){$heading=$name;$title=(!empty($obj['topic_title'])?$obj['topic_title']:$name).' - '.$brand;$description=self::text(mb_substr(strip_tags($obj['topic_content']??''),0,180,'UTF-8'),400);$url=self::topicUrl($obj['topic_en']??'');$image=self::image($obj['topic_pic']??'',$c)?:$image;$keywords=self::text($obj['topic_key']??'',200);$items=[];foreach((array)($obj['vod_list']??[]) as $row){$items[]=['@type'=>'ListItem','position'=>count($items)+1,'name'=>$row['vod_name'],'url'=>self::absolute(mac_url_vod_detail($row),$c)];}
        }elseif($page==='ranks'){$heading='按分类和年份找到评分最高的作品';$title='影视排行榜 - 按分类和年份看豆瓣高分片 - '.$brand;$description='按分类和年份整理的影视榜单，收录各分类各年份豆瓣评分较高的电影、剧集与动漫，可直接进入播放页。';$url='/rank.html';
        }elseif($page==='rank'){$b=$extra['board'];$heading=$b['name'];$title=$b['title'].' - '.$brand;$description=$b['intro'];$keywords=$b['keywords'];$url=$b['url'];$items=[];
            foreach($extra['rows'] as $row){$entry=['@type'=>'ListItem','position'=>count($items)+1,'name'=>$row['vod_name'],'url'=>self::absolute(mac_url_vod_detail($row),$c)];$items[]=$entry;}
            if($c['origin'])$schema=['@context'=>'https://schema.org','@type'=>'ItemList','name'=>$b['name'],'description'=>'按豆瓣评分从高到低排列，仅收录本站已收录且有评分的作品。','itemListOrder'=>'https://schema.org/ItemListOrderDescending','numberOfItems'=>count($items),'itemListElement'=>$items];
            if(!empty($extra['rows'][0]['vod_pic']))$image=self::image($extra['rows'][0]['vod_pic'],$c)?:$image;
        }elseif($page==='browse'){$heading='浏览全部影片';$title='全部影片 - 按字母与完结状态浏览 - '.$brand;$description='按片名首字母、完结状态和集数浏览站内全部影片与剧集。';$keywords=implode(',',array_filter(['全部影片','影视大全','字母索引',$brand]));$url=self::route('index/nightbrowse/index');}elseif($page==='letter'){$l=strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',(string)($param['l']??'')),0,1));$pg=max(1,(int)($param['page']??1));$heading=($l!==''?$l.' 开头的影片':'全部影片');$title=($l!==''?$l.' 开头影片大全':'全部影片').' - '.$brand;$description='片名以 '.($l!==''?$l:'任意字母').' 开头的电影与剧集，可按分类与完结状态继续筛选。';$keywords=implode(',',array_filter([$l.'开头影片','影视大全',$brand]));$url=self::route('index/nightbrowse/letter',array_filter(['l'=>$l,'page'=>$pg>1?$pg:null]));}elseif($page==='actorindex'){$heading='演员库';$title='演员库 - 按作品数量浏览演员 - '.$brand;$description='收录本站作品数达到 '.self::AGG_MIN.' 部以上的演员，可按作品数量浏览，进入演员主页查看全部参演作品与合作演员。';$keywords=implode(',',array_filter(['演员库','演员作品','演员大全',$brand]));$url=self::castUrl();}elseif($page==='actordetail'){$a=$extra['actor']??[];$nm=self::text($a['name']??'',60);$cnt=(int)($a['cnt']??0);$heading=$nm;$title=$nm.'的全部作品（'.$cnt.'部） - 演员 - '.$brand;$description=$nm.'参演的 '.$cnt.' 部影视作品，按收录时间排列，可查看年份、类型并直接进入在线播放页。';$keywords=implode(',',array_filter([$nm,$nm.'作品',$nm.'演过什么',$brand]));$url=!empty($a['slug'])?self::castUrl($a['slug']):'';}elseif($page==='tagindex'){$heading='题材标签';$title='题材标签大全 - 按标签找片 - '.$brand;$description='按题材标签浏览影视作品，每个标签收录 '.self::AGG_TAG_MIN.' 部以上同类影片，适合按口味找片。';$keywords=implode(',',array_filter(['题材标签','影视标签','按题材找片',$brand]));$url=self::genreUrl();}elseif($page==='tagdetail'){$t=$extra['tag']??[];$tn=self::text($t['name']??'',30);$tc=(int)($t['cnt']??0);$heading=$tn.'影片';$title=$tn.'影片大全（'.$tc.'部） - 按题材找片 - '.$brand;$description='本站收录的'.$tc.'部'.$tn.'题材影视作品，可按年份与收录时间浏览，含剧集与影片在线播放入口。';$keywords=implode(',',array_filter([$tn,$tn.'影片',$tn.'电影',$tn.'电视剧',$brand]));$url=!empty($t['slug'])?self::genreUrl($t['slug']):'';}elseif($page==='directorindex'){$heading='导演库';$title='导演库 - 按执导作品数量浏览导演 - '.$brand;$description='收录本站执导作品达到 '.self::AGG_DIR_MIN.' 部以上的导演，可按作品数量浏览，进入导演主页查看全部执导作品与常合作的演员。';$keywords=implode(',',array_filter(['导演库','导演作品','导演大全',$brand]));$url=self::directorUrl();}elseif($page==='directordetail'){$d=$extra['director']??[];$dn=self::text($d['name']??'',60);$dc=(int)($d['cnt']??0);$heading=$dn;$title=$dn.'执导的全部作品（'.$dc.'部） - 导演 - '.$brand;$description=$dn.'执导的 '.$dc.' 部影视作品，按收录时间排列，可查看年份、类型并直接进入在线播放页。';$keywords=implode(',',array_filter([$dn,$dn.'导演',$dn.'作品',$brand]));$url=!empty($d['slug'])?self::directorUrl($d['slug']):'';}elseif($page==='message'){$heading='访问提示';$title='访问提示 - '.$brand;$noindex=true;$url='';}
        $p=max(1,(int)($param['page']??1));if($p>1&&in_array($page,['catalog','topics','type','show','search','browse','letter','actorindex','tagindex','directorindex'],true)){$title.=' - 第'.$p.'页';if(in_array($page,['type','show'],true))$url=mac_url_type($obj,$page==='show'?$slugParam:$param,$page==='show'?'show':'type');elseif($page==='catalog')$url=mac_url('vod/index',['page'=>$p]);elseif($page==='topics')$url=mac_url('topic/index',['page'=>$p]);elseif($page==='search')$url=mac_url('vod/search',['wd'=>$param['wd']??'','page'=>$p]);elseif($page==='actorindex')$url=self::castUrl('',$p);elseif($page==='tagindex')$url=self::genreUrl('',$p);elseif($page==='directorindex')$url=self::directorUrl('',$p);}
        if(isset($param['sort'])||isset($param['order'])&&$param['order']!=='')$noindex=true;
        // 人物聚合页用 profile，分享卡片语义更准
        if(in_array($page,['actordetail','directordetail'],true))$ogtype='profile';
        $canonical=self::absolute($url,$c);
        if($schema===null)$schema=self::schemaFor($page,$obj,$extra,$param,$c,$canonical,$name,$heading,$image,$brand);
        return compact('c','page','title','description','keywords','canonical','ogtype','image','noindex','schema','heading','episode','baseType');
    }
    /* ---------- 结构化数据（JSON-LD）----------
       详情页 VideoObject + BreadcrumbList；人物页 Person；列表/聚合页 CollectionPage；
       首页 WebSite + Organization。不含评分/播放量等无真实来源的字段，避免结构化数据造假。 */
    /* 无简介影片的兜底文案：只用库里已有的事实（年份/地区/分类/语言/导演/演员/集数）组织句子，
       不虚构剧情，避免 3.8 万页重复同一句模板话术。 */
    public static function synopsis($obj){
        $o=is_object($obj)?$obj->toArray():(array)$obj;
        $name=self::text($o['vod_name']??'',120);
        // 30 字以下的多半是「全75集」这类备注，不当作简介
        foreach(['vod_content','vod_blurb'] as $k){
            $raw=trim(strip_tags(html_entity_decode((string)($o[$k]??''),ENT_QUOTES,'UTF-8')));
            if(mb_strlen($raw,'UTF-8')>=30)return $raw;
        }
        $year=trim((string)($o['vod_year']??''));
        $year=preg_match('/^(19|20)\d{2}$/',$year)?$year:'';
        $area=self::text($o['vod_area']??'',20);
        $type=self::text($o['type']['type_name']??$o['type_name']??'',40);
        $lang=self::text($o['vod_lang']??'',20);
        $eps=self::text($o['vod_remarks']??'',20);
        // 只认「全75集 / 更新至2集 / 第3期」这类集数备注，HD、抢先版之类画质备注不进句子
        if(!preg_match('/\d+\s*(集|期|话|回)/u',$eps))$eps='';
        if($eps===''&&(int)($o['vod_total']??0)>0)$eps='共'.(int)$o['vod_total'].'集';
        $dir=self::text($o['vod_director']??'',30);
        $actors=array_slice(array_filter(self::splitPeople((string)($o['vod_actor']??''))),0,3);
        $s='《'.$name.'》';
        $kind=$area.$type;
        if($kind!=='')$s.='是一部'.($year!==''?$year.'年':'') . $kind . '作品';
        elseif($year!=='')$s.='是'.$year.'年的影视作品';
        else $s.='是一部影视作品';
        if($dir!=='')$s.='，由'.$dir.'执导';
        if($actors)$s.='，'.implode('、',$actors).'主演';
        if($lang!=='')$s.='，'.$lang.'对白';
        if($eps!=='')$s.='，'.$eps;
        $s.='。';
        $s.='本页提供《'.$name.'》的播放列表、演职员信息与同类作品推荐。';
        return $s;
    }

    /* 详情页「故事简介」区块：有正文则按段落切分，没正文用 synopsis 兜底，两者皆无则不输出整块 */
    public static function storySection($obj){
        $o=is_object($obj)?$obj->toArray():(array)$obj;
        $raw='';
        foreach(['vod_content','vod_blurb'] as $k){
            $t=trim(strip_tags(html_entity_decode((string)($o[$k]??''),ENT_QUOTES,'UTF-8')));
            if(mb_strlen($t,'UTF-8')>=30){$raw=$t;break;}
        }
        $out='';
        if($raw!==''){
            $parts=preg_split('/\r\n|\r|\n+/',$raw);
            foreach($parts as $one){
                $one=trim($one);
                if($one!=='')$out.='<p>'.self::h($one).'</p>';
            }
        }
        if($out===''){
            $fallback=self::synopsis($o);
            if($fallback==='')return '';
            $out='<p>'.self::h($fallback).'</p>';
        }
        return '<section class="section story"><span class="eyebrow">ABOUT THE STORY</span><h2>故事简介</h2>'.$out.'</section>';
    }

    public static function schemaFor($page,$obj,$extra,$param,$c,$canonical,$name,$heading,$image,$brand){
        $site=self::absolute('/',$c);
        $crumb=function($trail) use ($c){
            $el=[];$i=1;
            foreach($trail as $label=>$u){if($label===''||$u==='')continue;$el[]=['@type'=>'ListItem','position'=>$i++,'name'=>$label,'item'=>self::absolute($u,$c)];}
            return $el?['@type'=>'BreadcrumbList','itemListElement'=>$el]:null;
        };
        $graph=[];$home=$brand;
        if($page==='detail'){
            $typeName=self::text($obj['type']['type_name']??'',60);
            $typeUrl=!empty($obj['type'])?mac_url_type($obj['type']):'';
            $b=$crumb([$home=>'/',$typeName=>$typeUrl,$name=>$canonical]);
            if($b)$graph[]=$b;
            $desc=self::text(mb_substr(trim(strip_tags(html_entity_decode((string)($obj['vod_content']??$obj['vod_blurb']??''),ENT_QUOTES,'UTF-8'))),0,200,'UTF-8'),300);
            $v=['@type'=>'VideoObject','name'=>$name,'url'=>$canonical];
            if($desc!=='')$v['description']=$desc;
            if($image!=='')$v['thumbnailUrl']=[$image];
            $t=(int)($obj['vod_time']??0);if($t>0)$v['uploadDate']=date('c',$t);
            $y=trim((string)($obj['vod_year']??''));if(preg_match('/^(19|20)\d{2}$/',$y))$v['datePublished']=$y;
            if($typeName!=='')$v['genre']=$typeName;
            $lang=trim((string)($obj['vod_lang']??''));if($lang!=='')$v['inLanguage']=$lang;
            try{$v['embedUrl']=self::absolute(mac_url_vod_play($obj,['sid'=>1,'nid'=>1]),$c);}catch(\Exception $e){}
            $dur=trim((string)($obj['vod_duration']??''));
            if(preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/',$dur,$m))$v['duration']='PT'.(int)$m[1].'H'.(int)$m[2].'M'.(int)$m[3].'S';
            $ppl=function($str,$max) use ($c){$out=[];foreach(array_slice(self::splitPeople((string)$str),0,$max) as $n){if($n!=='')$out[]=['@type'=>'Person','name'=>$n];}return $out;};
            $ac=$ppl($obj['vod_actor']??'',6);if($ac)$v['actor']=$ac;
            $di=$ppl($obj['vod_director']??'',3);if($di)$v['director']=$di;
            $graph[]=$v;
        }elseif(in_array($page,['actordetail','directordetail'],true)){
            $p=$page==='actordetail'?($extra['actor']??[]):($extra['director']??[]);
            $nm=self::text($p['name']??'',$page==='actordetail'?60:60);$cnt=(int)($p['cnt']??0);
            $idxUrl=$page==='actordetail'?self::castUrl():self::directorUrl();
            $idxName=$page==='actordetail'?'演员库':'导演库';
            $b=$crumb([$home=>'/',$idxName=>$idxUrl,$nm=>$canonical]);if($b)$graph[]=$b;
            if($nm!==''){
                $per=['@type'=>'Person','name'=>$nm,'url'=>$canonical,'mainEntityOfPage'=>$canonical];
                $per['description']=$nm.($page==='actordetail'?'参演的':'执导的').($cnt>0?$cnt.' 部':'全部').'影视作品，按收录时间排列。';
                if($image!=='')$per['image']=$image;
                $graph[]=$per;
            }
        }elseif($page==='home'){
            $graph[]=['@type'=>'WebSite','name'=>$brand,'url'=>$site,'inLanguage'=>'zh-CN'];
            if($image!=='')$graph[]=['@type'=>'Organization','name'=>$brand,'url'=>$site,'logo'=>$image];
        }elseif(in_array($page,['type','show','catalog','browse','letter','actorindex','tagindex','directorindex','ranks','topics','yearindex','yeardetail','comboindex','combodetail','seriesindex','seriesdetail','channel','tagdetail','topic'],true)){
            $label=($heading!==''&&$heading!==$c['tagline'])?$heading:$name;
            if($label==='')return null;
            $b=$crumb([$home=>'/',$label=>$canonical]);if($b)$graph[]=$b;
            $cp=['@type'=>'CollectionPage','name'=>$label,'url'=>$canonical,'isPartOf'=>['@type'=>'WebSite','name'=>$brand,'url'=>$site]];
            if($image!=='')$cp['primaryImageOfPage']=$image;
            $graph[]=$cp;
        }else{
            return null; // 未定义类型的页面（搜索/提示等）不输出结构化数据
        }
        if(!$graph)return null;
        return ['@context'=>'https://schema.org','@graph'=>array_values(array_filter($graph))];
    }
    public static function head($m){
        $out='<title>'.self::h($m['title']).'</title>';
        foreach(['description','keywords'] as $k)$out.='<meta name="'.$k.'" content="'.self::h($m[$k]).'">';
        if($m['noindex'])$out.='<meta name="robots" content="noindex,follow">';
        if($m['canonical'])$out.='<link rel="canonical" href="'.self::h($m['canonical']).'">';
        $og=['type'=>$m['ogtype'],'title'=>$m['title'],'description'=>$m['description'],'url'=>$m['canonical'],'image'=>$m['image'],'image:alt'=>$m['image']!==''?$m['title']:'','site_name'=>$m['c']['brand'],'locale'=>'zh_CN'];
        foreach($og as $k=>$v)if($v!=='')$out.='<meta property="og:'.$k.'" content="'.self::h($v).'">';
        if($m['image']!==''){
            foreach(['card'=>'summary_large_image','title'=>$m['title'],'description'=>$m['description'],'image'=>$m['image']] as $k=>$v)if($v!=='')$out.='<meta name="twitter:'.$k.'" content="'.self::h($v).'">';
        }else{$out.='<meta name="twitter:card" content="summary">';$out.='<meta name="twitter:title" content="'.self::h($m['title']).'">';$out.='<meta name="twitter:description" content="'.self::h($m['description']).'">';}
        $schema=$m['schema'];
        if(!$schema&&empty($m['noindex'])&&$m['canonical']!==''&&!in_array($m['page']??'',['search','message'],true)){
            $schema=['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$m['title'],'url'=>$m['canonical'],'description'=>$m['description'],'inLanguage'=>'zh-CN'];
        }
        if($schema)$out.='<script type="application/ld+json">'.json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
        return $out;
    }
}
