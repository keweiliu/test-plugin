<?php

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtForumPost');

/**
 * forum post read class
 * 
 * @since  2012-8-13
 * @author Wu ZeTao <578014287@qq.com>
 */
Class MbqRdEtForumPost extends MbqBaseRdEtForumPost {
    
    public function __construct() {
    }
    
    public function makeProperty(&$oMbqEtForumPost, $pName, $mbqOpt = array()) {
        switch ($pName) {
            default:
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
            break;
        }
    }
    
    /**
     * get forum post objs
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'byTopic' means get data by forum topic obj.$var is the forum topic obj.
     * $mbqOpt['case'] = 'byPostIds' means get data by post ids.$var is the ids.
     * $mbqOpt['case'] = 'byArrApiMessage' means get data by arrApiMessage.$var is the arrApiMessage.
     * $mbqOpt['case'] = 'byReplyUser' means get data by reply user.$var is the MbqEtUser obj.
     * @return  Mixed
     */
    public function getObjsMbqEtForumPost($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'byTopic') {
            $oMbqEtForumTopic = $var;
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $apiResultCount = MbqMain::$oMbqAppEnv->exttApiCall('threads/id/'.$oMbqEtForumTopic->topicId->oriValue.'/messages/linear/count');
                $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('threads/id/'.$oMbqEtForumTopic->topicId->oriValue.'/messages/linear?page_size='.$oMbqDataPage->numPerPage.'&page='.$oMbqDataPage->curPage);
                $oMbqDataPage->totalNum = $apiResultCount['data']['response']['value']['$'];
                $arrApiMessage = array();
                foreach ($apiResult['data']['response']['messages']['message'] as $apiMessage) {
                    $arrApiMessage[] = $apiMessage;
                }
                /* common begin */
                $mbqOpt['case'] = 'byArrApiMessage';
                $mbqOpt['oMbqDataPage'] = $oMbqDataPage;
                return $this->getObjsMbqEtForumPost($arrApiMessage, $mbqOpt);
                /* common end */
            }
        } elseif ($mbqOpt['case'] == 'byPostIds') {
            $arrApiMessage = array();
            foreach ($var as $id) {
                $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('messages/id/'.$id);
                $arrApiMessage[] = $apiResult['data']['response']['message'];
            }
            /* common begin */
            $mbqOpt['case'] = 'byArrApiMessage';
            return $this->getObjsMbqEtForumPost($arrApiMessage, $mbqOpt);
            /* common end */
        } elseif ($mbqOpt['case'] == 'byReplyUser') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_NOT_ACHIEVE);
        } elseif ($mbqOpt['case'] == 'byArrApiMessage') {
            $arrApiMessage = $var;
            /* common begin */
            $objsMbqEtForumPost = array();
            $authorUserIds = array();
            $topicIds = array();
            foreach ($arrApiMessage as $apiMessage) {
                $objsMbqEtForumPost[] = $this->initOMbqEtForumPost($apiMessage, array('case' => 'apiMessage'));
            }
            foreach ($objsMbqEtForumPost as $oMbqEtForumPost) {
                $authorUserIds[$oMbqEtForumPost->postAuthorId->oriValue] = $oMbqEtForumPost->postAuthorId->oriValue;
                $topicIds[$oMbqEtForumPost->topicId->oriValue] = $oMbqEtForumPost->topicId->oriValue;
            }
            /* load oMbqEtForumTopic property and oMbqEtForum property */
            $oMbqRdEtForumTopic = MbqMain::$oClk->newObj('MbqRdEtForumTopic');
            $objsMbqEtFroumTopic = $oMbqRdEtForumTopic->getObjsMbqEtForumTopic($topicIds, array('case' => 'byTopicIds'));
            foreach ($objsMbqEtFroumTopic as $oNewMbqEtFroumTopic) {
                foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                    if ($oNewMbqEtFroumTopic->topicId->oriValue == $oMbqEtForumPost->topicId->oriValue) {
                        $oMbqEtForumPost->oMbqEtForumTopic = $oNewMbqEtFroumTopic;
                        if ($oMbqEtForumPost->oMbqEtForumTopic->oMbqEtForum) {
                            $oMbqEtForumPost->oMbqEtForum = $oMbqEtForumPost->oMbqEtForumTopic->oMbqEtForum;
                        }
                    }
                }
            }
            /* load post author */
            $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');
            $objsAuthorMbqEtUser = $oMbqRdEtUser->getObjsMbqEtUser($authorUserIds, array('case' => 'byUserIds'));
            $postIds = array();
            foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                $postIds[] = $oMbqEtForumPost->postId->oriValue;
                foreach ($objsAuthorMbqEtUser as $oAuthorMbqEtUser) {
                    if ($oMbqEtForumPost->postAuthorId->oriValue == $oAuthorMbqEtUser->userId->oriValue) {
                        $oMbqEtForumPost->oAuthorMbqEtUser = $oAuthorMbqEtUser;
                        if ($oMbqEtForumPost->oAuthorMbqEtUser->isOnline->hasSetOriValue()) {
                            $oMbqEtForumPost->isOnline->setOriValue($oMbqEtForumPost->oAuthorMbqEtUser->isOnline->oriValue ? MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.isOnline.range.yes') : MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.isOnline.range.no'));
                        }
                        if ($oMbqEtForumPost->oAuthorMbqEtUser->iconUrl->hasSetOriValue()) {
                            $oMbqEtForumPost->authorIconUrl->setOriValue($oMbqEtForumPost->oAuthorMbqEtUser->iconUrl->oriValue);
                        }
                        break;
                    }
                }
            }
            /* load attachment */
            $oMbqRdEtAtt =  MbqMain::$oClk->newObj('MbqRdEtAtt');
            $objsMbqEtAtt = $oMbqRdEtAtt->getObjsMbqEtAtt($objsMbqEtForumPost, array('case' => 'byObjsMbqEtForumPost'));
            foreach ($objsMbqEtAtt as $oMbqEtAtt) {
                foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                    if ($oMbqEtForumPost->postId->oriValue == $oMbqEtAtt->postId->oriValue) {
                        $oMbqEtForumPost->objsMbqEtAtt[] = $oMbqEtAtt;
                    }
                }
            }
            /* load objsNotInContentMbqEtAtt */
            foreach ($objsMbqEtForumPost as &$oMbqEtForumPost) {
                $oMbqEtForumPost->objsNotInContentMbqEtAtt = $oMbqEtForumPost->objsMbqEtAtt;    //interimprogramme TODO
            }
            /* load objsMbqEtThank property and make related properties/flags */
            //
            /* make other properties */
            //
            /* common end */
            if ($mbqOpt['oMbqDataPage']) {
                $oMbqDataPage = $mbqOpt['oMbqDataPage'];
                $oMbqDataPage->datas = $objsMbqEtForumPost;
                return $oMbqDataPage;
            } else {
                return $objsMbqEtForumPost;
            }
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * init one forum post by condition
     *
     * @param  Mixed  $var
     * @param  Array  $mbqOpt
     * $mbqOpt['case'] = 'apiMessage' means init forum post by apiMessage
     * $mbqOpt['case'] = 'byPostId' means init forum post by post id
     * @return  Mixed
     */
    public function initOMbqEtForumPost($var, $mbqOpt) {
        if ($mbqOpt['case'] == 'apiMessage') {
            $oMbqEtForumPost = MbqMain::$oClk->newObj('MbqEtForumPost');
            $oMbqEtForumPost->postId->setOriValue($var['id']['$']);
            if ($var['parent']['href']) {
                $oMbqEtForumPost->parentPostId->setOriValue(MbqMain::$oMbqCm->exttGetObjIdByHref($var['parent']['href']));
            }
            $oMbqEtForumPost->forumId->setOriValue('forumBoard|'.MbqMain::$oMbqCm->exttGetObjIdByHref($var['board']['href']));
            $oMbqEtForumPost->topicId->setOriValue(MbqMain::$oMbqCm->exttGetObjIdByHref($var['root']['href']));
            $oMbqEtForumPost->postTitle->setOriValue($var['subject']['$']);
            $oMbqEtForumPost->postAuthorId->setOriValue(MbqMain::$oMbqCm->exttGetObjIdByHref($var['author']['href']));
            $oMbqEtForumPost->postTime->setOriValue(strtotime($var['post_time']['$']));
            $oMbqEtForumPost->postContent->setOriValue($var['body']['$']);
            $oMbqEtForumPost->postContent->setAppDisplayValue($var['body']['$']);
            $oMbqEtForumPost->postContent->setTmlDisplayValue($this->processContentForDisplay($var['body']['$'], true));
            $oMbqEtForumPost->postContent->setTmlDisplayValueNoHtml($this->processContentForDisplay($var['body']['$'], false));
            $oMbqEtForumPost->shortContent->setOriValue(MbqMain::$oMbqCm->getShortContent($oMbqEtForumPost->postContent->tmlDisplayValue));
            $apiResult = MbqMain::$oMbqAppEnv->exttApiCall('messages/id/'.$var['id']['$'].'/moderation/status');
            if ($apiResult['data']['response']['value']['$'] == 'approved') {
                $oMbqEtForumPost->state->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.state.range.postOk'));
            } else {
                $oMbqEtForumPost->state->setOriValue(MbqBaseFdt::getFdt('MbqFdtForum.MbqEtForumPost.state.range.postOkNeedModeration'));
            }
            $oMbqEtForumPost->mbqBind['apiMessage'] = $var;
            return $oMbqEtForumPost;
        } elseif ($mbqOpt['case'] == 'byPostId') {
            $objsMbqEtForumPost = $this->getObjsMbqEtForumPost(array($var), array('case' => 'byPostIds'));
            if ($objsMbqEtForumPost) {
                return $objsMbqEtForumPost[0];
            } else {
                return false;
            }
        }
        MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
    }
    
    /**
     * process content for display in mobile app
     *
     * @params  String  $content
     * @params  Boolean  $returnHtml
     * @return  String
     */
    public function processContentForDisplay($content, $returnHtml) {
        /*
        support bbcode:url/img/quote
        support html:br/i/b/u/font+color(red/blue)
        <strong> -> <b>
        attention input param:return_html
        attention output param:post_content
        */
        $post = $content;
    	if($returnHtml){
    	    $post = preg_replace('/<BLOCKQUOTE><HR \/>([^<]*?) wrote:<BR \/>/i', '$1 wrote:[quote]', $post); //quote begin
    	    $post = preg_replace('/<HR \/><\/BLOCKQUOTE>/i', '[/quote]', $post); //quote end
    	    $post = preg_replace('/<STRONG>(.*?)<\/STRONG>/i', '<b>$1</b>', $post); //b
    	    $post = preg_replace('/<EM>(.*?)<\/EM>/i', '<i>$1</i>', $post); //i
    	    $post = preg_replace('/<U>(.*?)<\/U>/i', '<u>$1</u>', $post); //u
    	    $post = preg_replace('/<div class="lia-spoiler-container"><a class="lia-spoiler-link" href="[^"]*?" rel="nofollow">Spoiler<\/a><noscript>.*?<\/noscript><div class="lia-spoiler-border"><div class="lia-spoiler-content">(.*?)<\/div><noscript>.*?<\/noscript><\/div><\/div>/i', '[spoiler]$1[/spoiler]', $post); //spoiler
    	    $post = preg_replace('/<PRE>(.*?)<\/PRE>/i', '[quote]$1[/quote]', $post); //code
    	    $post = preg_replace('/<img class="emoticon [^"]*?"[^>]*? alt="([^>]*?)"[^>]*?\/>/i', '$1', $post);//expression
    	    $post = preg_replace('/<A [^>]*?href="([^"]*?)"[^>]*?>(.*?)<\/A>/i', '[url=$1]$2[/url]', $post); //link
    	    $post = preg_replace('/<IMG [^>]*?src="([^"]*?)"[^>]*?\/>/i', '[img]$1[/img]', $post); //image
    	    $post = preg_replace('/<div class="video-embed-center [^"]*?"><iframe src="([^"]*?)"[^>]*?><\/iframe><\/div>/i', '[url=$1]$1[/url]', $post); //video
    	    $post = preg_replace_callback('/<FONT color="(#[^"]*?)">(.*?)<\/FONT>/i', create_function('$matches','return MbqMain::$oMbqCm->mbColorConvert($matches[1], $matches[2]);'), $post);    //font color
    	    
    	    /* final convert */
            $post = str_ireplace('<hr />', '<br />____________________________________<br />', $post);
    	    $post = str_ireplace('<li>', "\t\t<li>", $post);
    	    $post = str_ireplace('</li>', "</li><br />", $post);
    	    $post = str_ireplace('</tr>', '</tr><br />', $post);
    	    $post = str_ireplace('</td>', "</td>\t\t", $post);
    	    $post = str_ireplace('</p>', "</p><br />", $post);
    	    $post = str_ireplace('</div>', '</div><br />', $post);
    	    $post = strip_tags($post, '<br><i><b><u><font>');
    	} else {
    	    $post = preg_replace('/<br \/>/i', "\n", $post);
            $post = str_ireplace('<hr />', "\n____________________________________\n", $post);
            //strip remaining bbcode
            $post = preg_replace('/\[\/?.*?\]/i', '', $post);
    		$post = strip_tags($post);
    		$post = html_entity_decode($post, ENT_QUOTES, 'UTF-8');
    	}
    	$post = trim($post);
    	return $post;
    }
  
}

?>