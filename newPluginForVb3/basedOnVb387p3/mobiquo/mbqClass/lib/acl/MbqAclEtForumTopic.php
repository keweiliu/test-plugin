<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseAclEtForumTopic');

/**
 * forum topic acl class
 * 
 * @since  2013-9-30
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
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        //ref forumdisplay.php get permission to view forum
        $forumperms = fetch_permissions($oMbqEtForum->forumId->oriValue);
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
        {
        	return false;
        }
        return true;
    }
    
    /**
     * judge can get thread
     *
     * @param  Object  $oMbqEtForumTopic
     * @return  Boolean
     */
    public function canAclGetThread($oMbqEtForumTopic) {
        /* global common begin */
        require_once(MBQ_PATH.'appExtt/exttGlobal.php');
        eval(ExttMbqGlobal::$v);
        /* global common end */
        $thread = $oMbqEtForumTopic->mbqBind['topicRecord'];
        $foruminfo = $oMbqEtForumTopic->oMbqEtForum->mbqBind['forumRecord'];
        //ref showthread.php check for visible / deleted thread
        if (((!$thread['visible'] AND !can_moderate($thread['forumid'], 'canmoderateposts'))) OR ($thread['isdeleted'] AND !can_moderate($thread['forumid'])))
        {
        	return false;
        }
        //ref showthread.php jump page if thread is actually a redirect
        if ($thread['open'] == 10)
        {
        	return false;
        }
        //ref showthread.php Tachy goes to coventry
        if (in_coventry($thread['postuserid']) AND !can_moderate($thread['forumid']))
        {
        	return false;
        }
        //ref showthread.php check forum permissions
        $forumperms = fetch_permissions($thread['forumid']);
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
        {
        	return false;
        }
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
        {
        	return false;
        }
        return true;
    }
  
}

?>