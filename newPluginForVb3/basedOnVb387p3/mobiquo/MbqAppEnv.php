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
    public $rootUrl;    /* site root url */
    public $exttAllForums;  //all forums one dimensional array
    public $currentUserInfo;
    
    public function __construct() {
        parent::__construct();
        $this->forumTree = array();
        $this->exttAllForums = array();
    }
    
    /**
     * application environment init
     */
    public function init() {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        //$vbulletin->userinfo //current user info
        //$vbulletin->options   //options
        if (MbqMain::$oMbqConfig->moduleIsEnable('user') && $vbulletin->userinfo['userid']) {
            $this->currentUserInfo = $vbulletin->userinfo;
            $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
            $oMbqRdEtUser->initOCurMbqEtUser();
        }
        
        $oMbqRdEtForum = MbqMain::$oClk->newObj('MbqRdEtForum');
        $this->forumTree = $oMbqRdEtForum->getForumTree();   //!!!
        
        if (MbqMain::isJsonProtocol()) {
            $this->rootUrl = (strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') === false ? 'http' : 'https').'://'.$_SERVER['SERVER_NAME'].str_ireplace('tapatalk.php', '', $_SERVER['SCRIPT_NAME']);
        } else {
            $this->rootUrl = (strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') === false ? 'http' : 'https').'://'.$_SERVER['SERVER_NAME'].str_ireplace('mobiquo.php', '', $_SERVER['SCRIPT_NAME']);
        }
    }
    
}

?>