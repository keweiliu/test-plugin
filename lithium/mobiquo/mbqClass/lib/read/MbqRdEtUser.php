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
     * @mbqOpt['case'] = 'online' means get online user.
     * @return  Array
     */
    public function getObjsMbqEtUser($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byUserIds') {
            $objsMbqEtUser = array();
            foreach ($var as $userId) {
                if ($userId) {
                    $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('users/id/'.$userId.'/ref');
                    $objsMbqEtUser[] = $this->initOMbqEtUser($apiResult['data']['response']['user'], array('case' => 'apiUser'));
                }
            }
            return $objsMbqEtUser;
        } elseif ($mbqOpt['case'] == 'online') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one user by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'apiUser' means init user by apiUser.$var is apiUser.
     * $mbqOpt['case'] = 'byUserId' means init user by user id.$var is user id.
     * $mbqOpt['case'] = 'byLoginName' means init user by login name.$var is login name.
     * @return  Mixed
     */
    public function initOMbqEtUser($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'apiUser') {
            $oMbqEtUser = MbqMain::$oClk->newObj('MbqEtUser');
            $oMbqEtUser->userId->setOriValue($var['id']['$']);
            $oMbqEtUser->loginName->setOriValue($var['login']['$']);
            $oMbqEtUser->userName->setOriValue($var['login']['$']);
            //$oMbqEtUser->userGroupIds->setOriValue(array($var['usergroupid']));
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('users/id/'.$var['id']['$'].'/profiles/avatar/url');
            $oMbqEtUser->iconUrl->setOriValue($apiResult['data']['response']['value']['$']);
            $oMbqEtUser->canSearch->setOriValue(MbqBaseFdt::getFdt('MbqFdtUser.MbqEtUser.canSearch.range.yes'));
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('users/id/'.$var['id']['$'].'/posts/style/forum/count');
            $oMbqEtUser->postCount->setOriValue($apiResult['data']['response']['value']['$']);
            $oMbqEtUser->displayText->setOriValue('');
            $oMbqEtUser->regTime->setOriValue(strtotime($var['registration_time']['$']));
            $oMbqEtUser->lastActivityTime->setOriValue(strtotime($var['last_visit_time']['$']));
            $oMbqEtUser->canWhosonline->setOriValue(MbqBaseFdt::getFdt('MbqFdtUser.MbqEtUser.canWhosonline.range.yes'));
            $oMbqEtUser->mbqBind['userRecord'] = $var;
            return $oMbqEtUser;
        } elseif ($mbqOpt['case'] == 'byUserId') {
            $userIds = array($var);
            $objsMbqEtUser = $this->getObjsMbqEtUser($userIds, array('case' => 'byUserIds'));
            if (is_array($objsMbqEtUser) && (count($objsMbqEtUser) == 1)) {
                return $objsMbqEtUser[0];
            }
            return false;
        } elseif ($mbqOpt['case'] == 'byLoginName') {
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('users/login/'.$var.'/ref');
            return $this->initOMbqEtUser($apiResult['response']['user'], array('case' => 'apiUser'));
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
     * login
     *
     * @param  String  $loginName
     * @param  String  $password
     * @return  Boolean  return true when login success.
     */
    public function login($loginName, $password) {
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
    }
    
    /**
     * logout
     *
     * @return  Boolean  return true when logout success.
     */
    public function logout() {
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
    }
    
    /**
     * init current user obj if login
     */
    public function initOCurMbqEtUser() {
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
    }
  
}

?>