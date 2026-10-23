<?php
namespace app\common\service;

/** Shared by the public controller, authenticated theme admin and CLI tests. */
class RankCatalog
{
    const GUARD = "<?php http_response_code(404); exit; ?>\n";
    private $pdo;
    private $prefix;
    private $path;

    public function __construct(\PDO $pdo, $prefix, $path)
    {
        if (!preg_match('/^[a-z0-9_]+$/iD', $prefix)) throw new \InvalidArgumentException('Invalid table prefix');
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->path = $path;
    }

    public static function connect($root)
    {
        $db = include $root . '/application/database.php';
        $pdo = new \PDO('mysql:host='.$db['hostname'].';port='.($db['hostport'] ?: 3306).';dbname='.$db['database'].';charset=utf8mb4', $db['username'], $db['password'], [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        return new self($pdo, $db['prefix'], $root . '/application/data/rankings.php');
    }

    public function load()
    {
        if (!is_file($this->path)) throw new \RuntimeException('榜单配置尚未安装');
        $raw = file_get_contents($this->path);
        if (strpos($raw, self::GUARD) !== 0) throw new \RuntimeException('榜单配置格式错误');
        return $this->normalize(json_decode(substr($raw, strlen(self::GUARD)), true, 512, JSON_THROW_ON_ERROR));
    }

    public function revision()
    {
        return is_file($this->path) ? hash_file('sha256', $this->path) : '';
    }

    public function save(array $config, $revision)
    {
        $config = $this->normalize($config);
        $dir = dirname($this->path);
        if (!is_dir($dir)) throw new \RuntimeException('榜单配置目录不存在');
        $lock = fopen($dir.'/rankings.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new \RuntimeException('无法锁定榜单配置');
        $tmp = null;
        try {
            clearstatcache(true, $this->path);
            if (!hash_equals($this->revision(), (string)$revision)) throw new \RuntimeException('配置已被其他窗口更新，请刷新后重试');
            $raw = self::GUARD . json_encode($config, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
            $tmp = tempnam($dir, '.rank-');
            if (!$tmp || file_put_contents($tmp, $raw, LOCK_EX) !== strlen($raw)) throw new \RuntimeException('无法写入榜单配置');
            chmod($tmp, 0644);
            if (is_file($this->path) && !copy($this->path, $dir.'/rankings.previous.php')) throw new \RuntimeException('无法备份原榜单配置');
            if (!rename($tmp, $this->path)) throw new \RuntimeException('无法发布榜单配置');
            $tmp = null;
        } finally {
            if ($tmp && is_file($tmp)) unlink($tmp);
            flock($lock, LOCK_UN); fclose($lock);
        }
        return $config;
    }

    private static function text($value, $max = 200)
    {
        if (!is_scalar($value) && $value !== null) throw new \InvalidArgumentException('文本字段格式错误');
        $value = trim(strip_tags((string)$value));
        if (mb_strlen($value, 'UTF-8') > $max) throw new \InvalidArgumentException('文本字段过长');
        return $value;
    }

    public static function tokens($value)
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,，、\/|;；\r\n]+/u', (string)$value)), 'strlen')));
    }

    private static function values($input, $integers = false)
    {
        if (!is_array($input)) $input = self::tokens($input);
        if (count($input) > 100) throw new \InvalidArgumentException('单项条件最多100个值');
        $out = [];
        foreach ($input as $v) {
            $v = self::text($v, 80);
            if ($v === '') continue;
            if ($integers) {
                if (!ctype_digit($v) || (int)$v < 1) throw new \InvalidArgumentException('分类和影片ID必须为正整数');
                $v = (int)$v;
            }
            $out[] = $v;
        }
        return array_values(array_unique($out));
    }

    public function normalize($config)
    {
        if (!is_array($config) || !isset($config['boards']) || !is_array($config['boards']) || count($config['boards']) > 100) throw new \InvalidArgumentException('榜单配置格式错误（最多100个榜）');
        $index = $config['index'] ?? [];
        $clean = ['version'=>1, 'index'=>[
            'name'=>self::text($index['name'] ?? '影视排行榜', 80),
            'title'=>self::text($index['title'] ?? '电影电视剧排行榜 - 好看的影视作品推荐', 160),
            'keywords'=>self::text($index['keywords'] ?? '电影排行榜,电视剧排行榜,影视推荐', 300),
            'description'=>self::text($index['description'] ?? '按分类、题材、演员、地区等条件整理电影和电视剧榜单。', 400),
            'enable_hits'=>!empty($index['enable_hits']), 'enable_score'=>!empty($index['enable_score']),
            'preview'=>max(3,min(8,(int)($index['preview'] ?? 5))),
        ], 'boards'=>[]];
        $slugs = [];
        foreach ($config['boards'] as $b) {
            if (!is_array($b)) throw new \InvalidArgumentException('榜单条目格式错误');
            $slug = self::text($b['slug'] ?? '', 60);
            if (!preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $slug) || in_array($slug, ['index','show','new','edit','delete']) || isset($slugs[$slug])) throw new \InvalidArgumentException('英文地址须唯一，仅限小写字母、数字和短横线');
            $slugs[$slug] = true;
            $name = self::text($b['name'] ?? '', 80);
            if ($name === '') throw new \InvalidArgumentException('榜单名称不能为空');
            $channel = (int)($b['channel'] ?? 0);
            if (!in_array($channel, [0,2,3], true)) throw new \InvalidArgumentException('第一版频道仅支持电影、电视剧或二者');
            $group = self::text($b['group'] ?? '分类', 10);
            if (!in_array($group, ['分类','题材','演员','地区','年份','导演','组合'], true)) throw new \InvalidArgumentException('不支持的榜单分组');
            $sort = $b['default_sort'] ?? 'manual';
            if (!in_array($sort, ['manual','updated','hits','score'], true)) throw new \InvalidArgumentException('不支持的榜单排序');
            $row = ['slug'=>$slug,'name'=>$name,'group'=>$group,'channel'=>$channel,'enabled'=>!empty($b['enabled']),'order'=>max(0,min(9999,(int)($b['order'] ?? 0))),
                'title'=>self::text($b['title'] ?? $name,160), 'keywords'=>self::text($b['keywords'] ?? '',300),
                'description'=>self::text($b['description'] ?? '',400), 'intro'=>self::text($b['intro'] ?? '',600),
                'default_sort'=>$sort,'updated_at'=>max(0,min(time(),(int)($b['updated_at'] ?? 0)))];
            foreach (['category_ids','exclude_ids','manual_ids'] as $key) $row[$key]=self::values($b[$key] ?? [],true);
            if(count($row['manual_ids'])>10)throw new \InvalidArgumentException('编辑推荐每榜最多10部');
            foreach (['genres','actors','directors','areas','years'] as $key) $row[$key]=self::values($b[$key] ?? []);
            foreach ($row['years'] as $year) if (!preg_match('/^(19|20)\d{2}$/D',$year)) throw new \InvalidArgumentException('年份须为四位数字');
            $row['reasons']=[];
            foreach (($b['reasons'] ?? []) as $id=>$reason) {
                if (!ctype_digit((string)$id) || !in_array((int)$id,$row['manual_ids'],true)) continue;
                $row['reasons'][(int)$id]=self::text($reason,140);
            }
            $row['guide_title']=self::text($b['guide_title'] ?? '',120);
            $row['guide_items']=[];
            $guides=$b['guide_items'] ?? [];
            if(!is_array($guides)||count($guides)>6)throw new \InvalidArgumentException('选片导读最多6组');
            foreach($guides as $guide){
                if(!is_array($guide))throw new \InvalidArgumentException('选片导读格式错误');
                $ids=self::values($guide['film_ids'] ?? [],true);
                if(count($ids)>3||array_diff($ids,$row['manual_ids']))throw new \InvalidArgumentException('导读每组最多关联3部本榜编辑推荐影片');
                $title=self::text($guide['title'] ?? '',80);$body=self::text($guide['text'] ?? '',300);
                if($title===''||$body==='')throw new \InvalidArgumentException('导读小标题和内容不能为空');
                $row['guide_items'][]=['title'=>$title,'text'=>$body,'film_ids'=>$ids];
            }
            if($row['guide_items']&&$row['guide_title']==='')$row['guide_title']=$name.'选片导读';
            if ($row['enabled'] && $sort==='manual' && !$row['manual_ids']) throw new \InvalidArgumentException($name.'：编辑推荐需要选择影片');
            if ($row['enabled'] && (($sort==='hits'&&!$clean['index']['enable_hits'])||($sort==='score'&&!$clean['index']['enable_score']))) throw new \InvalidArgumentException('请先核验并开启对应热度或评分数据');
            $clean['boards'][]=$row;
        }
        usort($clean['boards'], function($a,$b){return ($a['order']<=>$b['order']) ?: strcmp($a['slug'],$b['slug']);});
        return $clean;
    }

    public function categories()
    {
        return $this->pdo->query('SELECT type_id,type_pid,type_name,type_en,type_status FROM '.$this->prefix.'type WHERE type_mid=1')->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function rows(array $b, $sort, $limit=10)
    {
        if (!in_array($sort,['manual','hits','updated','score'],true)) throw new \InvalidArgumentException('Invalid sort');
        if ($sort==='manual' && empty($b['manual_ids'])) return [];
        $where=['v.vod_status=1','t.type_status=1','v.vod_play_url<>\'\'','v.vod_pic<>\'\'','v.type_id NOT IN (37,38,39,40)','(p.type_id IS NULL OR p.type_status=1)'];$args=[];
        if ($b['channel']) {$where[]='(v.type_id_1=? OR v.type_id=?)';$args[]=$b['channel'];$args[]=$b['channel'];}
        else $where[]='(v.type_id_1 IN (2,3) OR v.type_id IN (2,3))';
        if ($b['category_ids']) {$where[]='v.type_id IN ('.implode(',',array_fill(0,count($b['category_ids']),'?')).')';$args=array_merge($args,$b['category_ids']);}
        if ($b['exclude_ids']) {$where[]='v.vod_id NOT IN ('.implode(',',array_fill(0,count($b['exclude_ids']),'?')).')';$args=array_merge($args,$b['exclude_ids']);}
        foreach (['genres'=>'vod_class','actors'=>'vod_actor','directors'=>'vod_director','areas'=>'vod_area','years'=>'vod_year'] as $key=>$field) {
            if (!$b[$key]) continue;
            $clauses=[];
            foreach ($b[$key] as $v) {$clauses[]='v.'.$field.' LIKE ?';$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$v).'%';}
            $where[]='('.implode(' OR ',array_map(function($c){return $c." ESCAPE '!'";},$clauses)).')';
        }
        if ($sort==='manual') {$where[]='v.vod_id IN ('.implode(',',array_fill(0,count($b['manual_ids']),'?')).')';$args=array_merge($args,$b['manual_ids']);}
        $orders=['manual'=>'v.vod_id DESC','hits'=>'v.vod_hits DESC,v.vod_id DESC','updated'=>'v.vod_time DESC,v.vod_id DESC','score'=>'v.vod_score DESC,v.vod_id DESC'];
        if ($sort==='score') $where[]='v.vod_score>0 AND v.vod_score<=10';
        $sql='SELECT v.vod_id,v.vod_en,v.vod_name,v.type_id,v.type_id_1,v.vod_year,v.vod_area,v.vod_class,v.vod_actor,v.vod_director,v.vod_pic,v.vod_remarks,v.vod_time,v.vod_hits,v.vod_score,t.type_name FROM '.$this->prefix.'vod v JOIN '.$this->prefix.'type t ON t.type_id=v.type_id LEFT JOIN '.$this->prefix.'type p ON p.type_id=t.type_pid WHERE '.implode(' AND ',$where).' ORDER BY '.$orders[$sort].' LIMIT '.($sort==='manual'?100:300);
        $query=$this->pdo->prepare($sql);$query->execute($args);$rows=$query->fetchAll(\PDO::FETCH_ASSOC);
        $rows=array_values(array_filter($rows,function($v)use($b){
            foreach (['genres'=>'vod_class','actors'=>'vod_actor','directors'=>'vod_director','areas'=>'vod_area','years'=>'vod_year'] as $key=>$field) {
                if ($b[$key] && !array_intersect($b[$key],self::tokens($v[$field]))) return false;
            }
            return true;
        }));
        if ($sort==='manual') {$positions=array_flip($b['manual_ids']);usort($rows,function($a,$b)use($positions){return $positions[$a['vod_id']]<=>$positions[$b['vod_id']];});}
        // Avoid showing alternate imports of the same name/year twice on one chart.
        $seen=[];$out=[];
        foreach ($rows as $row) {$identity=mb_strtolower(trim($row['vod_name']),'UTF-8').'|'.$row['vod_year'];if(isset($seen[$identity]))continue;$seen[$identity]=true;$out[]=$row;if(count($out)>=max(1,min(30,$limit)))break;}
        return $out;
    }
}
