<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtAtt');

/**
 * attachment read class
 * 
 * @since  2012-8-14
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdEtAtt extends MbqBaseRdEtAtt {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtAtt, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }
    
    /**
     * get attachment objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byObjsMbqEtForumPost' means get data by objsMbqEtForumPost.$var is the objsMbqEtForumPost.
     * @return  Mixed
     */
    public function getObjsMbqEtAtt($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byObjsMbqEtForumPost') {
            $objsMbqEtAtt = array();
            foreach ($var as $oMbqEtForumPost) {
                $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('messages/id/'.$oMbqEtForumPost->postId->oriValue.'/uploads/attachments');
                foreach ($apiResult['data']['response']['attachments']['attachment'] as $apiAttachment) {
                    $oMbqEtAtt = MbqMain::$oClk->newObj('MbqEtAtt');
                    $oMbqEtAtt->attId->setOriValue($apiAttachment['id']['$']);
                    if ($oMbqEtForumPost->forumId->hasSetOriValue()) {
                        $oMbqEtAtt->forumId->setOriValue($oMbqEtForumPost->forumId->oriValue);
                    }
                    $oMbqEtAtt->postId->setOriValue($oMbqEtForumPost->postId->oriValue);
                    $oMbqEtAtt->filtersSize->setOriValue($apiAttachment['content']['size']['$']);
                    $oMbqEtAtt->uploadFileName->setOriValue($apiAttachment['title']['$']);
                    $oMbqEtAtt->attType->setOriValue(MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.attType.range.forumPostAtt'));
                    $ext = strtolower(substr($oMbqEtAtt->uploadFileName->oriValue, strrpos($oMbqEtAtt->uploadFileName->oriValue, '.') + 1));
                    if ($ext == 'jpeg' || $ext == 'gif' || $ext == 'bmp' || $ext == 'png' || $ext == 'jpg') {
                        $contentType = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.image');
                    } elseif ($ext == 'pdf') {
                        $contentType = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.pdf');
                    } else {
                        $contentType = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.other');
                    }     
                    $oMbqEtAtt->contentType->setOriValue($contentType);
                    $oMbqEtAtt->url->setOriValue($apiAttachment['url']['$']);
                    if ($contentType == MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.image')) {
                        $oMbqEtAtt->thumbnailUrl->setOriValue($oMbqEtAtt->url->oriValue);
                    }
                    $oMbqEtAtt->mbqBind['apiAttachment'] = $apiAttachment;
                    
                    $objsMbqEtAtt[] = $oMbqEtAtt;
                }
            }
            return $objsMbqEtAtt;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
  
}

?>