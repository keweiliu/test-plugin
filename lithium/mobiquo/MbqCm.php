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
     * get obj id by lithium api returned href info
     *
     * @param  String  $href
     */
    public function exttGetObjIdByHref($href) {
        return substr($href, strrpos($href, '/') + 1);
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
    
}

?>