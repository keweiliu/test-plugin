<?php

defined('MBQ_IN_IT') or exit;

/**
 * application environment class
 * 
 * @since  2012-7-2
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqAppEnv extends MbqBaseAppEnv {
    
    /* this class fully relys on the application,so you can define the properties what you need come from the application. */
    private $exttApiBaseUrl;    /* api base url without method path */
    private $exttApiRootUrl;    /* api root url */
    private $exttApiUrl;
    private $exttTrackFlag;
    private $exttCachePath;     /* files cache root path */
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * application environment init
     */
    public function init() {
        set_time_limit(180);
        global $mbqExttLithiumSiteConfig;
        $this->config = $mbqExttLithiumSiteConfig;
        $this->exttApiBaseUrl = $this->config['siteUrl'].'/'.$this->config['communityName'].'/restapi/'.$this->config['apiVersion'].'/';    //with '/' ending
        $this->exttApiRootUrl = $this->config['siteUrl'].'/'.$this->config['communityName'].'/restapi/'.$this->config['apiVersion'];    //without '/' ending
        $this->exttTrackFlag = base64_encode(microtime());
        $this->exttCachePath = MBQ_PARENT_PATH.'mbqCache'.MBQ_DS;
        if (!isset($this->config['useCache']) || $this->config['useCache']) {
            $this->config['useCache'] = true;
        } else {
            $this->config['useCache'] = false;
        }
        if (!$this->config['cacheTimeLimit']) {
            $this->config['cacheTimeLimit'] = 3600;
        }
    }
    
    /**
     * call api
     *
     * @param  String  $methodPath  without '/' beginning
     * @param  Array  $mbqOpt
     * $mbqOpt['postData'] Array post data array 
     * $mbqOpt['echoError'] Boolean whether directly echo api error,default is true
     * @return  Mixed  api result
     */
    public function exttApiCall($methodPath, $mbqOpt = array()) {
        //setcookie('liSessionId', 'test123', time()+60*60*1);    //test:set for return to app and must send to api when call api 
        //$_COOKIE['liSessionId'] = 'test123';  //test:should must needed send to api when call api
        //$methodPath = 'boards/id/Speakers';
        $extra = '&restapi.format_detail=full_list_element&restapi.response_style=view&restapi.response_format=json';
        if (strpos($methodPath, '?') === false) {
            $this->exttApiUrl = $this->exttApiBaseUrl.$methodPath.'?'.$extra;
        } else {
            $this->exttApiUrl = $this->exttApiBaseUrl.$methodPath.$extra;
        }
        if ($mbqOpt['postData']) {
            $postData = $mbqOpt['postData'];
        } else {
            $postData = array();    //ref php manual
        }
        if (!isset($mbqOpt['echoError']) || $mbqOpt['echoError']) {
            $echoError = true;
        } else {
            $echoError = false;
        }
        //MbqCm::writeLog($this->exttApiUrl."\n", true);  //track api calling
        //MbqCm::writeLog($this->exttTrackFlag.'--'.time().'--'.$this->exttApiUrl."\n", true);  //track api calling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->exttApiUrl);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        if ($_COOKIE) {
            foreach($_COOKIE as $k => $v) {
                if( $cookies) {
                    $cookies .= ";".$k."=".urlencode($v);
                } else {
                    $cookies = $k."=".urlencode($v);
                }
            }
            //curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            //curl_setopt($ch, CURLOPT_COOKIEFILE, "cookiefile"); 
            //curl_setopt($ch, CURLOPT_COOKIEJAR, "cookiefile");
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        //'Accept: */*',
        //'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
        //'Connection: Keep-Alive'));
        if ($ip = $this->exttGetClientIp()) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('CLIENT-IP:'.$ip, 'X-FORWARDED-FOR:'.$ip));
        }
        //curl_setopt($ch, CURLOPT_HEADER, true);
        //curl_setopt($ch, CURLOPT_REFERER, "http://test.com");
        if ($this->config['authUser'] && $this->config['authPass']) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['authUser'].':'.$this->config['authPass']);
        }
        $strRet = curl_exec($ch);
        curl_close ($ch);
        if ($strRet === false) {
            MbqError::alert('', "Sorry!Calling api ".$this->exttApiUrl." failed!Please try later!");
        } else {
            $apiResult['apiUrl'] = $this->exttApiUrl;
            //TODO:get and set cookie from api result
            $apiResult['data'] = json_decode($strRet, true);
            if ($echoError) {
                if ($this->exttApiHasError($apiResult)) {
                    $this->exttEchoApiError($apiResult);
                } else {
                    return $apiResult;
                }
            } else {
                return $apiResult;
            }
        }
    }
    
    /**
     * judge api call return error
     *
     * @param  Mixed  api result
     * @return  Boolean
     */
    public function exttApiHasError($apiResult) {
        if ($apiResult['data']['response']['status'] == 'error') {
            return true;
        }
        return false;
    }
    
    /**
     * echo api error
     *
     * @param  Mixed  api result
     */
    public function exttEchoApiError($apiResult) {
        MbqError::alert('', $apiResult['data']['response']['error']['message'].'Error code:'.$apiResult['data']['response']['error']['code'].'.Error path:'.$this->exttApiUrl.'.');
    }
    
    /**
     * get client ip
     */
    private function exttGetClientIp() {
        if (!empty($_SERVER["HTTP_CLIENT_IP"]))
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"]; 
        else if (!empty($_SERVER["REMOTE_ADDR"]))
            $ip = $_SERVER["REMOTE_ADDR"];
        else
            $ip = '';
        return $ip;
    }  
    
    /**
     * make cache key
     *
     * @param  String  $mainKey
     * @return String
     */
    private function makeCacheKey($mainKey) {
        if (MbqMain::hasLogin()) {
            $userId = MbqMain::$oCurMbqEtUser->userId->oriValue;
        } else {
            $userId = 0;
        }
        $cmd = MbqMain::$cmd;
        if ($mainKey) {
            return $userId.'-'.md5($cmd.$mainKey);
        } else {
            return $userId.'-'.md5($cmd);
        }
    }
    
    /**
     * set a cache value
     *
     * @param  String  $key  cache key
     * @param  String  $value  cache value
     */
    public function exttSetCache($key, $value) {
        $dirName = $this->config['siteId'];
        if (!is_dir($this->exttCachePath.$dirName)) {
            if (!mkdir($this->exttCachePath.$dirName)) {
                MbqError::alert('', 'Sorry!Can not create cache dir!');
            }
        }
        $fileName = $this->makeCacheKey($key).'.cache';
        $filePath = $this->exttCachePath.$dirName.MBQ_DS.$fileName;
        if ($hd = fopen($filePath, 'wb')) {
            if (false === fwrite($hd, $value)) {
                MbqError::alert('', 'Sorry!Can not write cache file!');
            } else {
                if (!fclose($hd)) {
                    MbqError::alert('', 'Sorry!Can not close cache file!');
                }
            }
        } else {
            MbqError::alert('', 'Sorry!Can not create cache file!');
        }
    }
    
    /**
     * get a cache value
     *
     * @param  String  $key  cache key
     * @return mixed
     */
    public function exttGetCache($key) {
        clearstatcache();
        $dirName = $this->config['siteId'];
        $fileName = $this->makeCacheKey($key).'.cache';
        $filePath = $this->exttCachePath.$dirName.MBQ_DS.$fileName;
        if (is_file($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime !== false) {
                if (time() - $mtime <= $this->config['cacheTimeLimit']) {
                    $str = file_get_contents($filePath);
                    if ($str !== false) {
                        return $str;
                    }
                }
            }
        }
        return false;
    }
    
}

?>