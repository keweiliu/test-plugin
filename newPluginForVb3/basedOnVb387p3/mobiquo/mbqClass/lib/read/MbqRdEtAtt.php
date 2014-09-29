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
     * init one attachment by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'attachmentRecord' means init attachment by attachmentRecord
     * @return  Mixed
     */
    public function initOMbqEtAtt($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'attachmentRecord') {
            $oMbqEtAtt = MbqMain::$oClk->newObj('MbqEtAtt');
            $oMbqEtAtt->attId->setOriValue($var['attachmentid']);
            $oMbqEtAtt->postId->setOriValue($var['postid']);
            $oMbqEtAtt->filtersSize->setOriValue($var['filesize']);
            $oMbqEtAtt->uploadFileName->setOriValue($var['filename']);
            $ext = strtolower(MbqMain::$oMbqCm->getFileExtension($var['filename']));
            if ($ext == 'jpeg' || $ext == 'gif' || $ext == 'bmp' || $ext == 'png' || $ext == 'jpg') {
                $contentType = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.image');
            } elseif ($ext == 'pdf') {
                $contentType = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.pdf');
            } else {
                $contentType = MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.other');
            }     
            $oMbqEtAtt->contentType->setOriValue($contentType);
            if ($contentType == MbqBaseFdt::getFdt('MbqFdtAtt.MbqEtAtt.contentType.range.image')) {
                //$oMbqEtAtt->thumbnailUrl->setOriValue(MbqMain::$oMbqAppEnv->rootUrl.'attachment.php?attachmentid='.$var['attachmentid'].'&stc=1&thumb=1');
                $oMbqEtAtt->thumbnailUrl->setOriValue(MbqMain::$oMbqAppEnv->rootUrl.'attachment.php?attachmentid='.$var['attachmentid']);
            }
            $oMbqEtAtt->url->setOriValue(MbqMain::$oMbqAppEnv->rootUrl.'attachment.php?attachmentid='.$var['attachmentid']);
            $oMbqEtAtt->userId->setOriValue($var['userid']);
            $oMbqEtAtt->mbqBind['attachmentRecord'] = $var;
            return $oMbqEtAtt;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
  
}

?>