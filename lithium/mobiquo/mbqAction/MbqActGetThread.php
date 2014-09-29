<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActGetThread');

/**
 * get_thread action
 * 
 * @since  2012-8-12
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqActGetThread extends MbqBaseActGetThread {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * action implement
     */
    public function actionImplement() {
        if (!MbqMain::$oMbqConfig->moduleIsEnable('forum')) {
            MbqError::alert('', "Not support module forum!", '', MBQ_ERR_NOT_SUPPORT);
        }
        $topicId = MbqMain::$input[0];
        $startNum = (int) MbqMain::$input[1];
        $lastNum = (int) MbqMain::$input[2];
        $returnHtml = (boolean) MbqMain::$input[3];
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByStartAndLast($startNum, $lastNum);
        /* get cache begin */
        $cacheKey = "$topicId-$startNum-$lastNum-$returnHtml";
        if ($cacheData = MbqMain::$oMbqAppEnv->exttGetCache($cacheKey)) {
            if ($cacheData !== false) {
                $this->data = unserialize($cacheData);
                return;
            }
        }
        /* get cache end */
        $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
        if ($oMbqEtForumTopic = $oMbqRdEtForumTopic->initOMbqEtForumTopic($topicId, array('case' => 'byTopicId'))) {
            $oMbqAclEtForumTopic = MbqMain::$oClk->newObj('MbqAclEtForumTopic');
            if ($oMbqAclEtForumTopic->canAclGetThread($oMbqEtForumTopic)) {    //acl judge
                $oMbqRdEtForumPost = MbqMain::$oClk->newObj('MbqRdEtForumPost');
                $oMbqDataPage = $oMbqRdEtForumPost->getObjsMbqEtForumPost($oMbqEtForumTopic, array('case' => 'byTopic', 'oMbqDataPage' => $oMbqDataPage));
                $this->data = $oMbqRdEtForumTopic->returnApiDataForumTopic($oMbqEtForumTopic);
                $this->data['forum_name'] = (string) $oMbqEtForumTopic->oMbqEtForum->forumName->oriValue;
                $this->data['can_upload'] = (boolean) $oMbqEtForumTopic->oMbqEtForum->canUpload->oriValue;
                $this->data['posts'] = $oMbqRdEtForumPost->returnApiArrDataForumPost($oMbqDataPage->datas, $returnHtml);
                $oMbqWrEtForumTopic = MbqMain::$oClk->newObj('MbqWrEtForumTopic');
                /* add forum topic view num */
                $oMbqWrEtForumTopic->addForumTopicViewNum($oMbqEtForumTopic);
                /* mark forum topic read */
                $oMbqWrEtForumTopic->markForumTopicRead($oMbqEtForumTopic);
                /* reset forum topic subscription */
                $oMbqWrEtForumTopic->resetForumTopicSubscription($oMbqEtForumTopic);
                /* set cache begin */
                MbqMain::$oMbqAppEnv->exttSetCache($cacheKey, serialize($this->data));
                /* set cache end */
            } else {
                MbqError::alert('', '', '', MBQ_ERR_APP);
            }
        } else {
            MbqError::alert('', "Need valid topic id!", '', MBQ_ERR_APP);
        }
    }
  
}

?>