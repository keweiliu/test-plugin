<?php

defined('IN_MOBIQUO') or exit;

/**
 * application environment class
 * 
 * @since  2013-2-19
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqAppEnv {
    
    /* this class fully relys on the application,so you can define the properties what you need come from the application. */
    public static $isIos;
    public static $isAndroid;
    
    public static $mbqReturn;   /* plugin return data */
    
    /**
     * application environment init
     */
    public static function init() {
        if ($_SERVER['HTTP_MOBIQUO_ID'] == 2 || $_SERVER['HTTP_MOBIQUO_ID'] == 3 || $_SERVER['HTTP_MOBIQUO_ID'] == 10 || $_SERVER['HTTP_MOBIQUO_ID'] == 11) {
            self::$isIos = true;
        } elseif ($_SERVER['HTTP_MOBIQUO_ID'] == 4 || $_SERVER['HTTP_MOBIQUO_ID'] == 5) {
            self::$isAndroid = true;
        }
        self::$mbqReturn = array();
    }
    
}

?>