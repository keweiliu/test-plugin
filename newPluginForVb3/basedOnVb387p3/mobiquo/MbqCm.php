<?php

defined('MBQ_IN_IT') or exit;

/**
 * common method class
 * 
 * @since  2012-7-2
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqCm extends MbqBaseCm {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * convert native string content to utf8 for displaying in app
     *
     * @param  String  $str
     */
    public function exttNativeStrToUtf8($str) {
        if (function_exists('mobiquo_encode')) return mobiquo_encode($str, '', false);
        else return $str;
    }
    
    /**
     * transform timestamp to iso8601 format
     *
     * @param  Integer  $timeStamp
     * TODO:need to be made more general.
     */
    public function datetimeIso8601Encode($timeStamp) {
        //return date("c", $timeStamp);
        return date('Ymd\TH:i:s', $timeStamp).'+00:00';
    }
    
    /**
     * get attachment ids from content
     *
     * @params  String  $content
     * @return  Array
     */
    public function getAttIdsFromContent($content) {
        preg_match_all('/\[attach\](.*?)\[\/attach\]/i', $content, $mat);
        if ($mat[1]) {
            return $mat[1];
        } else {
            return array();
        }
    }
    
}

?>