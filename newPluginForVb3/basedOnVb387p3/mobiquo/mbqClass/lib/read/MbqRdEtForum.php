<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForum');

/**
 * forum read class
 * 
 * @since  2012-8-4
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdEtForum extends MbqBaseRdEtForum {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtForum, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }
    
    /**
     * get forum tree structure
     *
     * @return  Array
     */
    public function getForumTree() {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        //ref index.php START MAIN SCRIPT
        $newTree = array();
        if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
        {
	        return $newTree;
        }
        //ref index.php GET FORUMS & MODERATOR iCACHES
        cache_ordered_forums(1, 1);
        if ($vbulletin->options['showmoderatorcolumn'])
        {
        	cache_moderators();
        }
        else if ($vbulletin->userinfo['userid'])
        {
        	cache_moderators($vbulletin->userinfo['userid']);
        }
        if ($vbulletin->iforumcache['-1']) {
            $oMbqAclEtForumTopic = MbqMain::$oClk->newObj('MbqAclEtForumTopic');
            foreach ($vbulletin->iforumcache['-1'] as $forumId) {
                $id = $forumId;
                if (($forumRecord = $vbulletin->forumcache[$forumId]) && ($oNewMbqEtForum = $this->initOMbqEtForum($forumRecord, array('case' => 'forumRecord'))) && $oMbqAclEtForumTopic->canAclGetTopic($oNewMbqEtForum)) {
                    MbqMain::$oMbqAppEnv->exttAllForums[$id] = clone $oNewMbqEtForum;
                    $newTree[$id] = $oNewMbqEtForum;
                    $this->exttRecurInitObjsSubMbqEtForum($newTree[$id], $vbulletin->forumcache, $vbulletin->iforumcache);
                }
            }
        }
        return $newTree;
    }
    /**
     * recursive init objsSubMbqEtForum
     *
     * @param  Object  $oMbqEtForum  the object need init objsSubMbqEtForum
     * @param  Array  $forumcache
     * @param  Array  $iforumcache
     */
    private function exttRecurInitObjsSubMbqEtForum(&$oMbqEtForum, $forumcache, $iforumcache) {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        if ($iforumcache[$oMbqEtForum->forumId->oriValue]) {
            $oMbqAclEtForumTopic = MbqMain::$oClk->newObj('MbqAclEtForumTopic');
            foreach ($iforumcache[$oMbqEtForum->forumId->oriValue] as $forumId) {
                $id = $forumId;
                if (($forumRecord = $forumcache[$forumId]) && ($oNewMbqEtForum = $this->initOMbqEtForum($forumRecord, array('case' => 'forumRecord'))) && $oMbqAclEtForumTopic->canAclGetTopic($oNewMbqEtForum)) {
                    MbqMain::$oMbqAppEnv->exttAllForums[$id] = clone $oNewMbqEtForum;
                    $oMbqEtForum->objsSubMbqEtForum[$id] = $oNewMbqEtForum;
                    $oMbqEtForum->objsSubMbqEtForum[$id]->oParentMbqEtForum = clone $oMbqEtForum;    //!!!
                    $this->exttRecurInitObjsSubMbqEtForum($oMbqEtForum->objsSubMbqEtForum[$id], $forumcache, $iforumcache);
                }
            }
        }
    }
    
    /**
     * get breadcrumb forums
     *
     * @param  Integer  $forumId
     * @return Array
     */
    public function getObjsBreadcrumbMbqEtForum($forumId) { //for json
        $tree = MbqMain::$oMbqAppEnv->returnForumTree();
        $objsBreadcrumbMbqEtForum = array();
        foreach ($tree as $oMbqEtForum) {
            if ($oMbqEtForum->forumId->oriValue == $forumId) {
                $oFindMbqEtForum = $oMbqEtForum;
                break;
            } else {
                $ret = $this->exttRecurFindForum($oMbqEtForum->objsSubMbqEtForum, $forumId);
                if ($ret) {
                    $oFindMbqEtForum = $ret;
                    break;
                }
            }
        }
        if ($oFindMbqEtForum) {
            $tempObjsBreadcrumbMbqEtForum[0] = clone $oFindMbqEtForum;
            $tempObjsBreadcrumbMbqEtForum[0]->objsSubMbqEtForum = array();  //!!! clear sub forums for output breadcrumb
            $this->exttRecurMakeTempObjsBreadcrumbMbqEtForum($tempObjsBreadcrumbMbqEtForum, $oFindMbqEtForum);
            $objsBreadcrumbMbqEtForum =  array_reverse($tempObjsBreadcrumbMbqEtForum);
            return $objsBreadcrumbMbqEtForum;
        } else {
            return array();
        }
    }
    /**
     * recursive find forum
     *
     * @param  Array  $objsSubMbqEtForum
     * @param  String  $forumId
     * @return  Mixed
     */
    private function exttRecurFindForum($objsSubMbqEtForum, $forumId) {
        foreach ($objsSubMbqEtForum as $oMbqEtForum) {
            if ($oMbqEtForum->forumId->oriValue == $forumId) {
                $oFindMbqEtForum = $oMbqEtForum;
                break;
            } else {
                $ret = $this->exttRecurFindForum($oMbqEtForum->objsSubMbqEtForum, $forumId);
                if ($ret) {
                    $oFindMbqEtForum = $ret;
                    break;
                }
            }
        }
        if ($oFindMbqEtForum) return $oFindMbqEtForum;
        else return false;
    }
    /**
     * recur make $tempObjsBreadcrumbMbqEtForum
     *
     * @param  Array  $tempObjsBreadcrumbMbqEtForum
     * @param  Object  $oFindMbqEtForum
     */
    private function exttRecurMakeTempObjsBreadcrumbMbqEtForum(&$tempObjsBreadcrumbMbqEtForum, $oFindMbqEtForum) {
        if ($oFindMbqEtForum->oParentMbqEtForum) {
            $i = count($tempObjsBreadcrumbMbqEtForum);
            $tempObjsBreadcrumbMbqEtForum[$i] = clone $oFindMbqEtForum->oParentMbqEtForum;
            $tempObjsBreadcrumbMbqEtForum[$i]->objsSubMbqEtForum = array();  //!!! clear sub forums for output breadcrumb
            $this->exttRecurMakeTempObjsBreadcrumbMbqEtForum($tempObjsBreadcrumbMbqEtForum, $oFindMbqEtForum->oParentMbqEtForum);
        }
    }
    
    /**
     * get forum objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byForumIds' means get data by forum ids.$var is the ids.
     * @return  Array
     */
    public function getObjsMbqEtForum($var, $mbqOpt) {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        if ($mbqOpt['case'] == 'byForumIds') {
            $objsMbqEtForum = array();
            $i = 0;
            foreach ($var as $id) {
                if ($oNewMbqEtForum = $this->initOMbqEtForum($id, array('case' => 'byForumId'))) {
                    $objsMbqEtForum[$i] = $oNewMbqEtForum;
                    $i ++;
                }
            }
            return $objsMbqEtForum;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one forum by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byForumId' means init forum by forum id
     * $mbqOpt['case'] = 'forumRecord' means init forum by forumRecord
     * @return  Mixed
     */
    public function initOMbqEtForum($var, $mbqOpt) {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        if ($mbqOpt['case'] == 'byForumId') {
            if (MbqMain::$oMbqAppEnv->exttAllForums[$var]) {
                return MbqMain::$oMbqAppEnv->exttAllForums[$var];
            }
            return false;
        } elseif ($mbqOpt['case'] == 'forumRecord') {
            $oMbqEtForum = MbqMain::$oClk->newObj('MbqEtForum');
            $oMbqEtForum->forumId->setOriValue($var['forumid']);
            $oMbqEtForum->forumName->setOriValue(MbqMain::$oMbqCm->exttNativeStrToUtf8($var['title']));
            $oMbqEtForum->description->setOriValue(MbqMain::$oMbqCm->exttNativeStrToUtf8($var['description']));
            $oMbqEtForum->totalTopicNum->setOriValue($var['threadcount']);
            $oMbqEtForum->totalPostNum->setOriValue($var['replycount']);
            $oMbqEtForum->parentId->setOriValue($var['parentid']);
            /*
            if ($var['password'] && ($var['canhavepassword'] || (!$var['canhavepassword'] && $vbulletin->userinfo['usergroupid'] != 5 && $vbulletin->userinfo['usergroupid'] != 7 && $vbulletin->userinfo['usergroupid'] != 6))) {
                $oMbqEtForum->isProtected->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.isProtected.range.yes'));
            } else {
                $oMbqEtForum->isProtected->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.isProtected.range.no'));
            }
            */
            if ($var['password']) {
                $oMbqEtForum->isProtected->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.isProtected.range.yes'));
            } else {
                $oMbqEtForum->isProtected->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.isProtected.range.no'));
            }
            if ($var['link']) {
                $oMbqEtForum->url->setOriValue($var['link']);
            }
            if ($forumInfo = fetch_foruminfo($var['forumid'])) {
                $oMbqEtForum->mbqBind['forumInfo'] = $forumInfo;
                if ($forumInfo['cancontainthreads']) {
                    $oMbqEtForum->subOnly->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.subOnly.range.no'));
                } else {
                    $oMbqEtForum->subOnly->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.subOnly.range.yes'));
                }
                //ref forumdisplay.php -> if ($foruminfo['cancontainthreads'])
            	if ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'])
            	{
            		$foruminfo['forumread'] = $vbulletin->forumcache["$foruminfo[forumid]"]['forumread'];
            		$lastread = max($foruminfo['forumread'], TIMENOW - ($vbulletin->options['markinglimit'] * 86400));
            	}
            	else
            	{
            		$bbforumview = intval(fetch_bbarray_cookie('forum_view', $foruminfo['forumid']));
            		$lastread = max($bbforumview, $vbulletin->userinfo['lastvisit']);
            	}
            	$oMbqEtForum->mbqBind['lastread'] = $lastread;  //!!!
            }
            $oMbqEtForum->mbqBind['forumRecord'] = $var;
            return $oMbqEtForum;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * get sub forums in a special forum
     *
     * @return Array
     */
    public function getObjsSubMbqEtForum($forumId) {    //for json
        $objsSubMbqEtForum = array();
        $tree = MbqMain::$oMbqAppEnv->returnForumTree();
        foreach ($tree as $oMbqEtForum) {
            if ($oMbqEtForum->forumId->oriValue == $forumId) {
                return $oMbqEtForum->objsSubMbqEtForum;
            } else {
                if ($oNewMbqEtForum = $this->exttRecurFindForum($oMbqEtForum->objsSubMbqEtForum, $forumId)) {
                    return $oNewMbqEtForum->objsSubMbqEtForum;
                }
            }
        }
        return $objsSubMbqEtForum;
    }
  
}

?>