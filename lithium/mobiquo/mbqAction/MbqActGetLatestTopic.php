<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseActGetLatestTopic');

/**
 * get_latest_topic action
 * 
 * @since  2012-8-27
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqActGetLatestTopic extends MbqBaseActGetLatestTopic {
    
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
        $startNum = (int) MbqMain::$input[0];
        $lastNum = (int) MbqMain::$input[1];
        $oMbqDataPage = MbqMain::$oClk->newObj('MbqDataPage');
        $oMbqDataPage->initByStartAndLast($startNum, $lastNum);
        $filter = array(
            'searchid' => MbqMain::$input[2],
            'page' => $oMbqDataPage->curPage,
            'perpage' => $oMbqDataPage->numPerPage
        );
        if (MbqMain::$input[3] && is_array(MbqMain::$input[3])) {
            $filter = array_merge($filter, MbqMain::$input[3]);
        }
        $filter['showposts'] = 0;
        /* get cache begin */
        $cacheKey = "$startNum-$lastNum-".serialize($filter);
        if ($cacheData = MbqMain::$oMbqAppEnv->exttGetCache($cacheKey)) {
            if ($cacheData !== false) {
                $this->data = unserialize($cacheData);
                return;
            }
        }
        /* get cache end */
        $oMbqAclEtForumTopic = MbqMain::$oClk->newObj('MbqAclEtForumTopic');
        if ($oMbqAclEtForumTopic->canAclGetLatestTopic()) {    //acl judge
            $oMbqRdForumSearch = MbqMain::$oClk->newObj('MbqRdForumSearch');
            $oMbqDataPage = $oMbqRdForumSearch->forumAdvancedSearch($filter, $oMbqDataPage, array('case' => 'getLatestTopic'));
            $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
            $this->data['result'] = true;
            $this->data['total_topic_num'] = (int) $oMbqDataPage->totalNum;
            $this->data['topics'] = $oMbqRdEtForumTopic->returnApiArrDataForumTopic($oMbqDataPage->datas);
            /* set cache begin */
            MbqMain::$oMbqAppEnv->exttSetCache($cacheKey, serialize($this->data));
            /* set cache end */
        } else {
            MbqError::alert('', '', '', MBQ_ERR_APP);
        }
    }
  
}

?>