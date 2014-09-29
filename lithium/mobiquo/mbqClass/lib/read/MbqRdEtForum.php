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
        $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('categories/top');   //!!!
        $newTree = array();
        foreach ($apiResult['data']['response']['category']['categories']['category'] as $apiCategory) {
            $apiResultPermisstion = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$apiCategory['id']['$'].'/settings', array('echoError' => false));
            if (!MbqMain::$oMbqAppEnv->exttApiHasError($apiResultPermisstion)) {
                $id = 'category|'.$apiCategory['id']['$'];  //!!!
                if ($oNewMbqEtForum1 = $this->initOMbqEtForum($apiCategory, array('case' => 'apiCategory', 'parentId' => 'category|top'))) { //!!!
                    $newTree[$id] = $oNewMbqEtForum1;
                    $this->exttRecurInitObjsSubMbqEtForum($newTree[$id]);
                }
            }
        }
        $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('categories/top/boards/style/forum');    //!!!
        foreach ($apiResult['data']['response']['boards']['board'] as $apiForumBoard) {
            $apiResultPermisstion = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$apiForumBoard['id']['$'].'/settings', array('echoError' => false));
            if (!MbqMain::$oMbqAppEnv->exttApiHasError($apiResultPermisstion)) {
                $id = 'forumBoard|'.$apiForumBoard['id']['$'];  //!!!
                if ($oNewMbqEtForum2 = $this->initOMbqEtForum($apiForumBoard, array('case' => 'apiForumBoard', 'parentId' => 'category|top'))) { //!!!
                    $newTree[$id] = $oNewMbqEtForum2;
                }
            }
        }
        return $newTree;
    }
    /**
     * recursive init objsSubMbqEtForum
     *
     * @param  Object  $oMbqEtForum  the object need init objsSubMbqEtForum
     */
    private function exttRecurInitObjsSubMbqEtForum(&$oMbqEtForum) {
        $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$oMbqEtForum->mbqBind['apiCategory']['id']['$'].'/categories');
        foreach ($apiResult['data']['response']['categories']['category'] as $apiCategory) {
            $apiResultPermisstion = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$apiCategory['id']['$'].'/settings', array('echoError' => false));
            if (!MbqMain::$oMbqAppEnv->exttApiHasError($apiResultPermisstion)) {
                $id = 'category|'.$apiCategory['id']['$'];  //!!!
                if ($oNewMbqEtForum1 = $this->initOMbqEtForum($apiCategory, array('case' => 'apiCategory', 'parentId' => 'category|'.$oMbqEtForum->mbqBind['apiCategory']['id']['$']))) { //!!!
                    $oMbqEtForum->objsSubMbqEtForum[$id] = $oNewMbqEtForum1;
                    $this->exttRecurInitObjsSubMbqEtForum($oMbqEtForum->objsSubMbqEtForum[$id]);
                }
            }
        }
        $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$oMbqEtForum->mbqBind['apiCategory']['id']['$'].'/boards/style/forum');
        foreach ($apiResult['data']['response']['boards']['board'] as $apiForumBoard) {
            $apiResultPermisstion = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$apiForumBoard['id']['$'].'/settings', array('echoError' => false));
            if (!MbqMain::$oMbqAppEnv->exttApiHasError($apiResultPermisstion)) {
                $id = 'forumBoard|'.$apiForumBoard['id']['$'];  //!!!
                if ($oNewMbqEtForum2 = $this->initOMbqEtForum($apiForumBoard, array('case' => 'apiForumBoard', 'parentId' => 'category|'.$oMbqEtForum->mbqBind['apiCategory']['id']['$']))) { //!!!
                    $oMbqEtForum->objsSubMbqEtForum[$id] = $oNewMbqEtForum2;
                }
            }
        }
    }
    
    /**
     * get forum objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byForumIds' means get data by forum ids.$var is the ids.
     * $mbqOpt['case'] = 'subscribed' means get subscribed data.$var is the user id.
     * @return  Array
     */
    public function getObjsMbqEtForum($var, $mbqOpt) {
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
        } elseif ($mbqOpt['case'] == 'subscribed') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one forum by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byForumId' means init forum by forum id
     * $mbqOpt['case'] = 'apiCategory' means init forum by apiCategory
     * $mbqOpt['case'] = 'apiForumBoard' means init forum by apiForumBoard
     * $mbqOpt['parentId'] means parent forum id
     * @return  Mixed
     */
    public function initOMbqEtForum($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byForumId') {
            if (strpos($var, 'category|') === 0) {
                $categoryId = substr($var, strlen('category|'));
                $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$categoryId);
                $apiCategory = $apiResult['data']['response']['category'];
                $apiResult2 = MbqMain::$oMbqAppEnv->exttApiCall('categories/id/'.$categoryId.'/parent');
                return $this->initOMbqEtForum($apiCategory, array('case' => 'apiCategory', 'parentId' => 'category|'.$apiResult2['data']['response']['category']['id']['$']));
            } elseif (strpos($var, 'forumBoard|') === 0) {
                $forumBoardId = substr($var, strlen('forumBoard|'));
                $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$forumBoardId);
                $apiForumBoard = $apiResult['data']['response']['board'];
                $apiResult2 = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$forumBoardId.'/category');
                return $this->initOMbqEtForum($apiForumBoard, array('case' => 'apiForumBoard', 'parentId' => 'category|'.$apiResult2['data']['response']['category']['id']['$']));
            }
            return false;
        } elseif ($mbqOpt['case'] == 'apiForumBoard') {
            $oMbqEtForum = MbqMain::$oClk->newObj('MbqEtForum');
            $oMbqEtForum->forumId->setOriValue('forumBoard|'.$var['id']['$']);    //!!!
            $oMbqEtForum->forumName->setOriValue($var['title']['$']);
            $oMbqEtForum->description->setOriValue($var['description']['$']);
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('boards/id/'.$var['id']['$'].'/topics/count');
            $oMbqEtForum->totalTopicNum->setOriValue($apiResult['data']['response']['value']['$']);
            $oMbqEtForum->parentId->setOriValue($mbqOpt['parentId']);
            $oMbqEtForum->subOnly->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.subOnly.range.no'));
            $oMbqEtForum->mbqBind['apiForumBoard'] = $var;
            $oMbqEtForum->extt['type'] = 'forumBoard';
            return $oMbqEtForum;
        } elseif ($mbqOpt['case'] == 'apiCategory') {
            $oMbqEtForum = MbqMain::$oClk->newObj('MbqEtForum');
            $oMbqEtForum->forumId->setOriValue('category|'.$var['id']['$']);    //!!!
            $oMbqEtForum->forumName->setOriValue($var['title']['$']);
            $oMbqEtForum->description->setOriValue($var['description']['$']);
            $oMbqEtForum->totalTopicNum->setOriValue(0);
            $oMbqEtForum->parentId->setOriValue($mbqOpt['parentId']);
            $oMbqEtForum->subOnly->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForum.subOnly.range.yes'));
            $oMbqEtForum->mbqBind['apiCategory'] = $var;
            $oMbqEtForum->extt['type'] = 'category';
            return $oMbqEtForum;
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
  
}

?>