<?php
$__cacheFile = __DIR__.'/theme_cache.php';
$__lockFile  = __DIR__.'/theme_cache.lock';
$__ttl = 86400;
$__adminUrl = 'https://域名/conch-vip';
if (is_file($__cacheFile) && (time()-filemtime($__cacheFile)) < $__ttl && strpos(file_get_contents($__cacheFile), '<?php') === 0 && strpos(file_get_contents($__cacheFile), '$__d=json_decode') !== false) {
    $__arr = include $__cacheFile;
    if (is_array($__arr)) extract($__arr);
} else {
    $__stale = is_file($__cacheFile);
    $__need = !is_file($__lockFile) || (time()-filemtime($__lockFile) > 20);
    if ($__need) {
        @file_put_contents($__lockFile, time());
        $__host = isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'127.0.0.1';
        $__arr = null;
        $__res = null;
        $__url = $__adminUrl.'/ThemeBridge.php?__bridge_code=1&raw=1&domain='.urlencode($__host);
        $__ch = curl_init($__url);
        curl_setopt_array($__ch, [
            CURLOPT_RETURNTRANSFER=>1,
            CURLOPT_TIMEOUT=>3,
            CURLOPT_CONNECTTIMEOUT=>2,
            CURLOPT_SSL_VERIFYPEER=>0,
            CURLOPT_SSL_VERIFYHOST=>0,
            CURLOPT_USERAGENT=>'Mozilla/5.0'
        ]);
        $__res = curl_exec($__ch);
        curl_close($__ch);
        if ($__res === false || $__res === '') {
            $__res = @file_get_contents($__url);
        }
        if ($__res !== false && $__res !== '') {
            $__res = ltrim($__res);
            if (strpos($__res, '$__d=json_decode') !== false) {
                if (strpos($__res, '<?php') !== 0) {
                    $__res = '<?php ' . $__res;
                }
                $__tempArr = @eval(substr($__res, 5));
                if (is_array($__tempArr) && !empty($__tempArr['conch_theme'])) {
                    $__arr = $__tempArr;
                    @file_put_contents($__cacheFile, $__res);
                }
            }
        }
        if (!$__arr) {
            $__localBridge = $_SERVER['DOCUMENT_ROOT'] . '/conch-vip/ThemeBridge.php';
            if (file_exists($__localBridge)) {
                $__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $__url = $__scheme . '://' . $__host . '/conch-vip/ThemeBridge.php?__bridge_code=1&raw=1&domain=' . urlencode($__host);
                $__ch = curl_init($__url);
                curl_setopt_array($__ch, [
                    CURLOPT_RETURNTRANSFER=>1,
                    CURLOPT_TIMEOUT=>3,
                    CURLOPT_CONNECTTIMEOUT=>2,
                    CURLOPT_SSL_VERIFYPEER=>0,
                    CURLOPT_SSL_VERIFYHOST=>0,
                    CURLOPT_USERAGENT=>'Mozilla/5.0'
                ]);
                $__res = curl_exec($__ch);
                curl_close($__ch);
                if ($__res === false || $__res === '') {
                    $__res = @file_get_contents($__url);
                }
                if ($__res !== false && $__res !== '') {
                    $__res = ltrim($__res);
                    if (strpos($__res, '$__d=json_decode') !== false) {
                        if (strpos($__res, '<?php') !== 0) {
                            $__res = '<?php ' . $__res;
                        }
                        $__tempArr = @eval(substr($__res, 5));
                        if (is_array($__tempArr) && !empty($__tempArr['conch_theme'])) {
                            $__arr = $__tempArr;
                            @file_put_contents($__cacheFile, $__res);
                        }
                    }
                }
            }
        }
        if ($__arr) {
            extract($__arr);
        } elseif ($__stale) {
            $__arr = include $__cacheFile;
            if (is_array($__arr)) extract($__arr);
        }
        @unlink($__lockFile);
    } else {
        $__arr = include $__cacheFile;
        if (is_array($__arr)) extract($__arr);
    }
}
if (!isset($conch_theme) || !is_array($conch_theme)) {
    $conch_theme = [];
}
