<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForumTopic');

/**
 * forum topic read class
 * 
 * @since  2012-8-8
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdEtForumTopic extends MbqBaseRdEtForumTopic {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtForumTopic, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }
    
    /**
     * get forum topic objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byForum' means get data by forum obj.$var is the forum obj.
     * $mbqOpt['case'] = 'subscribed' means get subscribed data.$var is the user id.
     * $mbqOpt['case'] = 'byArrApiMessage' means get data by arrApiMessage.$var is the arrApiMessage.
     * $mbqOpt['case'] = 'byTopicIds' means get data by topic ids.$var is the ids.
     * $mbqOpt['case'] = 'byAuthor' means get data by author.$var is the MbqEtUser obj.
     * $mbqOpt['top'] = true means get sticky data.
     * $mbqOpt['notIncludeTop'] = true means get not sticky data.
     * @return  Mixed
     */
    public function getObjsMbqEtForumTopic($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byForum') {
            $oMbqEtForum = $var;
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                if ($var->extt['type'] == 'category') {
                    $oMbqDataPage->totalNum = 0;
                    $oMbqDataPage->datas = array();
                    return $oMbqDataPage;
                } elseif ($var->extt['type'] == 'forumBoard') {
                    /* TODO:for $mbqOpt['top'] and $mbqOpt['notIncludeTop'] */
                    if ($mbqOpt['top']) {
                        $oMbqDataPage->totalNum = 0;
                        $oMbqDataPage->datas = array();
                        return $oMbqDataPage;
                    } elseif ($mbqOpt['notIncludeTop']) {
                        $apiResultCount = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$var->mbqBind['apiForumBoard']['id']['$'].'/topics/count');
                        $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$var->mbqBind['apiForumBoard']['id']['$'].'/topics?page_size='.$oMbqDataPage->numPerPage.'&page='.$oMbqDataPage->curPage);
                        $oMbqDataPage->totalNum = $apiResultCount['data']['response']['value']['$'];
                        $arrApiMessage = array();
                        foreach ($apiResult['data']['response']['node_message_context']['message'] as $apiMessage) {
                            $arrApiMessage[] = $apiMessage;
                        }
                        /* common begin */
                        $mbqOpt['case'] = 'byArrApiMessage';
                        $mbqOpt['oMbqDataPage'] = $oMbqDataPage;
                        return $this->getObjsMbqEtForumTopic($arrApiMessage, $mbqOpt);
                        /* common end */
                    } else {
                        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
                    }
                } else {
                    MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
                }
            }
        } elseif ($mbqOpt['case'] == 'subscribed') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        } elseif ($mbqOpt['case'] == 'byAuthor') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        } elseif ($mbqOpt['case'] == 'byTopicIds') {
            $arrApiMessage = array();
            foreach ($var as $id) {
                $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('messages/id/'.$id);
                if ($apiResult['data']['response']['message']['href'] == $apiResult['data']['response']['message']['root']['href']) {   //judge is topic root message
                    $arrApiMessage[] = $apiResult['data']['response']['message'];
                }
            }
            /* common begin */
            $mbqOpt['case'] = 'byArrApiMessage';
            return $this->getObjsMbqEtForumTopic($arrApiMessage, $mbqOpt);
            /* common end */
        } elseif ($mbqOpt['case'] == 'byArrApiMessage') {
            $arrApiMessage = $var;
            /* common begin */
            $objsMbqEtForumTopic = array();
            $authorUserIds = array();
            $lastReplyUserIds = array();
            $forumIds = array();
            $topicIds = array();
            foreach ($arrApiMessage as $apiMessage) {
                $objsMbqEtForumTopic[] = $this->initOMbqEtForumTopic($apiMessage, array('case' => 'byApiMessage'));
            }
            foreach ($objsMbqEtForumTopic as $oMbqEtForumTopic) {
                $authorUserIds[$oMbqEtForumTopic->topicAuthorId->oriValue] = $oMbqEtForumTopic->topicAuthorId->oriValue;
                //$lastReplyUserIds[$oMbqEtForumTopic->lastReplyAuthorId->oriValue] = $oMbqEtForumTopic->lastReplyAuthorId->oriValue;   //TODO
                $forumIds[$oMbqEtForumTopic->forumId->oriValue] = $oMbqEtForumTopic->forumId->oriValue;
                $topicIds[$oMbqEtForumTopic->topicId->oriValue] = $oMbqEtForumTopic->topicId->oriValue;
            }
            /* load oMbqEtForum property */
            $oMbqRdEtForum = MbqMain::$oClk->newObj('MbqRdEtForum');
            $objsMbqEtForum = $oMbqRdEtForum->getObjsMbqEtForum($forumIds, array('case' => 'byForumIds'));
            foreach ($objsMbqEtForum as $oNewMbqEtForum) {
                foreach ($objsMbqEtForumTopic as &$oMbqEtForumTopic) {
                    if ($oNewMbqEtForum->forumId->oriValue == $oMbqEtForumTopic->forumId->oriValue) {
                        $oMbqEtForumTopic->oMbqEtForum = $oNewMbqEtForum;
                    }
                }
            }
            /* load topic author */
            $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
            $objsAuthorMbqEtUser = $oMbqRdEtUser->getObjsMbqEtUser($authorUserIds, array('case' => 'byUserIds'));
            foreach ($objsMbqEtForumTopic as &$oMbqEtForumTopic) {
                foreach ($objsAuthorMbqEtUser as $oAuthorMbqEtUser) {
                    if ($oMbqEtForumTopic->topicAuthorId->oriValue == $oAuthorMbqEtUser->userId->oriValue) {
                        $oMbqEtForumTopic->oAuthorMbqEtUser = $oAuthorMbqEtUser;
                        if ($oMbqEtForumTopic->oAuthorMbqEtUser->iconUrl->hasSetOriValue()) {
                            $oMbqEtForumTopic->authorIconUrl->setOriValue($oMbqEtForumTopic->oAuthorMbqEtUser->iconUrl->oriValue);
                        }
                        break;
                    }
                }
            }
            /* load oLastReplyMbqEtUser */
            $objsLastReplyMbqEtUser = $oMbqRdEtUser->getObjsMbqEtUser($lastReplyUserIds, array('case' => 'byUserIds'));
            foreach ($objsMbqEtForumTopic as &$oMbqEtForumTopic) {
                foreach ($objsLastReplyMbqEtUser as $oLastReplyMbqEtUser) {
                    if ($oMbqEtForumTopic->lastReplyAuthorId->oriValue == $oLastReplyMbqEtUser->userId->oriValue) {
                        $oMbqEtForumTopic->oLastReplyMbqEtUser = $oLastReplyMbqEtUser;
                        break;
                    }
                }
            }
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $oMbqDataPage->datas = $objsMbqEtForumTopic;
                return $oMbqDataPage;
            } else {
                return $objsMbqEtForumTopic;
            }
            /* common end */
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one forum topic by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byApiMessage' means init forum topic by apiMessage
     * $mbqOpt['case'] = 'byTopicId' means init forum topic by topic id
     * @return  Mixed
     */
    public function initOMbqEtForumTopic($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byApiMessage') {
            $oMbqRdEtForumPost = MbqMain::$oClk->newObj('MbqRdEtForumPost');
            $oMbqEtForumTopic = MbqMain::$oClk->newObj('MbqEtForumTopic');
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('threads/id/'.$var['id']['$'].'/replies/count');
            $oMbqEtForumTopic->totalPostNum->setOriValue($apiResult['data']['response']['value']['$'] + 1);
            $oMbqEtForumTopic->topicId->setOriValue($var['id']['$']);
            $oMbqEtForumTopic->forumId->setOriValue('forumBoard|'.MbqMain::$oMbqCm->exttGetObjIdByHref($var['board']['href']));
            $oMbqEtForumTopic->firstPostId->setOriValue($var['id']['$']);
            $oMbqEtForumTopic->topicTitle->setOriValue($var['subject']['$']);
            $oMbqEtForumTopic->topicContent->setOriValue($var['body']['$']);
            $oMbqEtForumTopic->shortContent->setOriValue(MbqMain::$oMbqCm->getShortContent($oMbqRdEtForumPost->processContentForDisplay($var['body']['$'], true)));
            $oMbqEtForumTopic->topicAuthorId->setOriValue(MbqMain::$oMbqCm->exttGetObjIdByHref($var['author']['href']));
            //$oMbqEtForumTopic->lastReplyAuthorId->setOriValue($var['content']['lastauthorid']);   //TODO
            $oMbqEtForumTopic->postTime->setOriValue(strtotime($var['post_time']['$']));
            //$oMbqEtForumTopic->lastReplyTime->setOriValue($var['content']['lastcontent']);        //TODO
            $oMbqEtForumTopic->replyNumber->setOriValue($oMbqEtForumTopic->totalPostNum->oriValue - 1);
            $oMbqEtForumTopic->viewNumber->setOriValue($var['views']['count']['$']);
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('messages/id/'.$var['id']['$'].'/moderation/status');
            if ($apiResult['data']['response']['value']['$'] == 'approved') {
                $oMbqEtForumTopic->state->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumTopic.state.range.postOk'));
            } else {
                $oMbqEtForumTopic->state->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumTopic.state.range.postOkNeedModeration'));
            }
            $oMbqEtForumTopic->mbqBind['apiMessage'] = $var;
            return $oMbqEtForumTopic;
        } elseif ($mbqOpt['case'] == 'byTopicId') {
            $topicId = $var;
            if ($objsMbqEtForumTopic = $this->getObjsMbqEtForumTopic(array($topicId), array('case' => 'byTopicIds'))) {
                return $objsMbqEtForumTopic[0];
            }
            return false;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
  
}

?>