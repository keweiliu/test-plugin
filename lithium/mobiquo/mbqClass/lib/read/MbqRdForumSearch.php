<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdForumSearch');

/**
 * forum search class
 * 
 * @since  2012-8-27
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdForumSearch extends MbqBaseRdForumSearch {
    
    public function __construct() {
    }
    
    /**
     * forum advanced search
     *
     * @param  Array  $filter  search filter
     * @param  Object  $oMbqDataPage
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'advanced' means advanced search
     * @return  Object  $oMbqDataPage
     */
    public function forumAdvancedSearch($filter, $oMbqDataPage, $mbqOpt) {
        if ($mbqOpt['case'] == 'getLatestTopic') {
            $apiResultCount = MbqMain::$oMbqAppEnv->exttApiCall('topics/style/board/recent/count');
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('topics/style/board/recent?page_size='.$oMbqDataPage->numPerPage.'&page='.$oMbqDataPage->curPage);
            $oMbqDataPage->totalNum = $apiResultCount['data']['response']['value']['$'];
            $arrApiMessage = array();
            foreach ($apiResult['data']['response']['messages']['message'] as $apiMessage) {
                $arrApiMessage[] = $apiMessage;
            }
            /* common begin */
            $mbqOpt['case'] = 'byArrApiMessage';
            $mbqOpt['oMbqDataPage'] = $oMbqDataPage;
            $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
            return $oMbqRdEtForumTopic->getObjsMbqEtForumTopic($arrApiMessage, $mbqOpt);
            /* common end */
        } elseif ($mbqOpt['case'] == 'searchTopic') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        } elseif ($mbqOpt['case'] == 'searchPost') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        } elseif ($mbqOpt['case'] == 'advanced') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
  
}

?>