<?php
// 推送榜单 URL：IndexNow 批量 + 百度按配额
$root='/www/wwwroot/www.ljly.net';
$conf=@include $root.'/tools/push.conf.php';
if(!is_array($conf)){fwrite(STDERR,"push.conf.php missing\n");exit(1);}
$site=rtrim($conf['site']??'https://www.ljly.net','/');
$key=$conf['indexnow_key']??'';
$token=$conf['baidu_token']??'';
$daily=(int)($conf['baidu_daily']??10);

$xml=@file_get_contents($root.'/sitemap-rank.xml');
if(!$xml){fwrite(STDERR,"sitemap-rank.xml missing\n");exit(1);}
preg_match_all('~<loc>([^<]+)</loc>~',$xml,$m);
$urls=array_values(array_unique(array_map('html_entity_decode',$m[1])));
echo "榜单 URL: ".count($urls)."\n";

$cfg=@include $root.'/application/database.php';
$db=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$cfg['hostname'],$cfg['hostport']?:'3306',$cfg['database']),$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE IF NOT EXISTS nf_push_log (url varchar(255) NOT NULL, engine varchar(20) NOT NULL, pushed_at int NOT NULL, http_code smallint NOT NULL DEFAULT 0, note varchar(200) NOT NULL DEFAULT '', PRIMARY KEY(url,engine)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$chk=$db->prepare("SELECT pushed_at FROM nf_push_log WHERE url=? AND engine=?");
$mark=$db->prepare("INSERT INTO nf_push_log(url,engine,pushed_at,http_code,note) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE pushed_at=VALUES(pushed_at),http_code=VALUES(http_code),note=VALUES(note)");

function post($url,$body,$headers){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>0]);
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return [$c,$r];
}

// ---- IndexNow ----
$fresh=[];
foreach($urls as $u){ $chk->execute([$u,'indexnow']); $t=$chk->fetchColumn(); if(!$t||time()-$t>30*86400)$fresh[]=$u; }
echo "IndexNow 待推: ".count($fresh)."\n";
if($fresh&&$key!==''){
    $batch=array_slice($fresh,0,10000);
    $body=json_encode(['host'=>parse_url($site,PHP_URL_HOST),'key'=>$key,'keyLocation'=>$site.'/'.$key.'.txt','urlList'=>$batch],JSON_UNESCAPED_SLASHES);
    [$c,$r]=post('https://api.indexnow.org/indexnow',$body,['Content-Type: application/json; charset=utf-8']);
    echo "IndexNow HTTP $c ".substr((string)$r,0,120)."\n";
    if($c>=200&&$c<300)foreach($batch as $u)$mark->execute([$u,'indexnow',time(),$c,'rank batch']);
}

// ---- 百度 ----
$bq=$db->prepare("SELECT COUNT(*) FROM nf_push_log WHERE engine='baidu' AND pushed_at>?");
$bq->execute([strtotime('today 00:00')]);$used=(int)$bq->fetchColumn();
$quota=max(0,$daily-$used);
echo "百度今日已用 $used / 配额 $daily，剩余 $quota\n";
$burls=[];
foreach($urls as $u){ $chk->execute([$u,'baidu']); $t=$chk->fetchColumn(); if(!$t||time()-$t>30*86400)$burls[]=$u; }
$burls=array_slice($burls,0,$quota);
if($burls&&$token!==''){
    $api='http://data.zz.baidu.com/urls?site='.$site.'&token='.$token;
    foreach($burls as $u){
        $ch=curl_init($api);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$u,CURLOPT_TIMEOUT=>20]);
        $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        echo "  百度 $u -> $c ".substr((string)$r,0,80)."\n";
        $mark->execute([$u,'baidu',time(),$c,'rank']);
        usleep(300000);
    }
}else echo "百度跳过（无待推或配额用尽）\n";
echo "DONE\n";
