<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtUser');

/**
 * user read class
 * 
 * @since  2012-8-6
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdEtUser extends MbqBaseRdEtUser {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtUser, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }
    
    /**
     * get user objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byUserIds' means get data by user ids.$var is the ids.
     * @return  Array
     */
    public function getObjsMbqEtUser($var, $mbqOpt) {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        if ($mbqOpt['case'] == 'byUserIds') {
            $objsMbqEtUser = array();
            foreach ($var as $userId) {
                if ($oMbqEtUser = $this->initOMbqEtUser($userId, array('case' => 'byUserId'))) {
                    $objsMbqEtUser[] = $oMbqEtUser;
                }
            }
            return $objsMbqEtUser;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one user by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'userInfo' means init user by userInfo.$var is userInfo.
     * $mbqOpt['case'] = 'byUserId' means init user by user id.$var is user id.
     * @return  Mixed
     */
    public function initOMbqEtUser($var, $mbqOpt) {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        if ($mbqOpt['case'] == 'userInfo') {
            $oMbqEtUser = MbqMain::$oClk->newObj('MbqEtUser');
            $oMbqEtUser->userId->setOriValue($var['userid']);
            $oMbqEtUser->loginName->setOriValue($var['username']);
            $oMbqEtUser->userName->setOriValue($var['username']);
            $oMbqEtUser->userGroupIds->setOriValue(array('usergroupid'));
            if ($var['hascustomavatar']) {
                $oMbqEtUser->iconUrl->setOriValue(MbqMain::$oMbqAppEnv->rootUrl.'image.php?u='.$var['userid']);
            }
            $oMbqEtUser->postCount->setOriValue($var['posts']);
            $oMbqEtUser->regTime->setOriValue($var['joindate']);
            $oMbqEtUser->lastActivityTime->setOriValue($var['lastactivity']);
            $oMbqEtUser->mbqBind['userInfo'] = $var;
            return $oMbqEtUser;
        } elseif ($mbqOpt['case'] == 'byUserId') {
            $options = (
            FETCH_USERINFO_AVATAR
            );
            if ($userInfo = fetch_userinfo($var, $options)) {
                return $this->initOMbqEtUser($userInfo, array('case' => 'userInfo'));
            }
            return false;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * get user display name
     *
     * @param  Object  $oMbqEtUser
     * @return  String
     */
    public function getDisplayName($oMbqEtUser) {
        return $oMbqEtUser->loginName->oriValue;
    }
    
    /**
     * init current user obj if login
     */
    public function initOCurMbqEtUser() {
        if (MbqMain::$oMbqAppEnv->currentUserInfo) {
            MbqMain::$oCurMbqEtUser = $this->initOMbqEtUser(MbqMain::$oMbqAppEnv->currentUserInfo['userid'], array('case' => 'byUserId'));
        }
    }
  
}

?>