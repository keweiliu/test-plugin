<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtForumTopic');

/**
 * forum topic acl class
 * 
 * @since  2012-8-10
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqAclEtForumTopic extends MbqBaseAclEtForumTopic {
    
    public function __construct() {
    }
    
    /**
     * judge can get topic from the forum
     *
     * @param  Object  $oMbqEtForum
     * @return  Boolean
     */
    public function canAclGetTopic($oMbqEtForum) {
        if ($oMbqEtForum->extt['type'] == 'category') {
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$oMbqEtForum->mbqBind['apiCategory']['id']['$'].'/view/allowed');
            if ($apiResult['data']['response']['value']['$']) {
                return true;
            }
        } elseif ($oMbqEtForum->extt['type'] == 'forumBoard') {
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$oMbqEtForum->mbqBind['apiForumBoard']['id']['$'].'/view/allowed');
            if ($apiResult['data']['response']['value']['$']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * judge can get thread
     *
     * @param  Object  $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclGetThread($oMbqEtForumTopic) {
        if ($oMbqEtForumTopic->oMbqEtForum && $this->canAclGetTopic($oMbqEtForumTopic->oMbqEtForum)) {
            return true;
        }
        return false;
    }
    
    /**
     * judge can get_latest_topic
     *
     * @return  Boolean
     */
    public function canAclGetLatestTopic() {
        return true;
    }
  
}

?>