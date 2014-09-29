<?php

global $headinclude, $header;

$app_android_id = $vbulletin->options['tp_app_android_url'] ? $vbulletin->options['tp_app_android_url'] : '';
$app_ios_id = $vbulletin->options['tp_app_ios_id'] ? $vbulletin->options['tp_app_ios_id'] : '';
$app_banner_message = $vbulletin->options['tp_app_banner_message'] ? $vbulletin->options['tp_app_banner_message'] : '';
$app_banner_message = preg_replace('/\r\n/','<br>',$app_banner_message);
$app_location_url = get_scheme_url();
$script_to_page = array(
   'index'          => 'index',
   'showthread'     => 'topic',
   'showpostpost'   => 'post',
   'forumdisplay'   => 'forum',
);

$page_type = defined('THIS_SCRIPT') && isset($script_to_page[THIS_SCRIPT]) ? $script_to_page[THIS_SCRIPT] : 'others';
$is_mobile_skin = false;
$app_forum_name = $vbulletin->options['bbtitle'] ? $vbulletin->options['bbtitle'] : '';
$board_url = $vbulletin->options['bburl'];
$tapatalk_dir = $vbulletin->options['tapatalk_directory'];  // default as 'mobiquo'
$tapatalk_dir_url = $board_url.'/'.$tapatalk_dir;
$api_key = $vbulletin->options['push_key'];
$app_ads_enable = $vbulletin->options['full_ads'];
$app_banner_enable = $vbulletin->options['tapatalk_smartbanner'];
if (file_exists(CWD .'/'.$tapatalk_dir . '/smartbanner/head.inc.php'))
    include_once(CWD .'/'.$tapatalk_dir . '/smartbanner/head.inc.php');

$headinclude .= isset($app_head_include) ? $app_head_include : '';


$header = '
<!-- Tapatalk Detect body start -->
<script type="text/javascript">if (typeof(tapatalkDetect) == "function") tapatalkDetect()</script>
<!-- Tapatalk Detect banner body end -->

'.$header;



function get_scheme_url()
{
    global $vbulletin;

    $baseUrl = $vbulletin->options['bburl'];
    $baseUrl = preg_replace('/https?:\/\//', 'tapatalk://', $baseUrl);
    $location = 'index';
    $other_info = array();
    $gpc = $vbulletin->GPC;

    $has_forumid = isset($vbulletin->GPC['forumid']) && !empty($vbulletin->GPC['forumid']);
    $has_threadid = isset($vbulletin->GPC['threadid']) && !empty($vbulletin->GPC['threadid']);
    $has_postid = isset($vbulletin->GPC['postid']) && !empty($vbulletin->GPC['postid']);
    if($has_forumid)
    {
        $location = 'forum';
        $other_info[] = 'fid='.$vbulletin->GPC['forumid'];
        $perpage = $vbulletin->options['maxthreads'];
        $page = $gpc['pagenumber'] > 0 ? $gpc['pagenumber']:  1;
        if($has_threadid)
        {
            $location = 'topic';
            $perpage = $vbulletin->options['maxposts'];
            $page = $gpc['pagenumber'];
            $other_info[] = 'tid='.$vbulletin->GPC['threadid'];
            if($has_postid)
            {
                $perpage = $vbulletin->options['maxposts'];
                $page = $gpc['pagenumber'];
                $location = 'post';
                $other_info[] = 'pid='.$vbulletin->GPC['postid'];
            }
        }
    }
    else if(isset($vbulletin->GPC['userid']) && !empty($vbulletin->GPC['userid']))
    {
        $location = 'profile';
        $other_info[] = 'uid='.$vbulletin->GPC['userid'];
    }
    else if(isset($_REQUEST['pmid']) && !empty($_REQUEST['pmid']))
    {
        $location = 'message';
        $other_info[] = 'mid='.$_REQUEST['pmid'];
    }
    else if(isset($vbulletin->GPC['who']))
       $location = 'online';
    else if(isset($vbulletin->GPC['searchid']))
       $location = 'search';
    else if(isset($vbulletin->GPC['logintype']))
       $location = 'login';


    $other_info_str = implode('&', $other_info);
    $scheme_url = $baseUrl. (!empty($vbulletin->userinfo['userid']) ? '?user_id='.$vbulletin->userinfo['userid'].'&' : '?') . 'location='.$location.(!empty($page) && !empty($perpage) ? "&page=$page&perpage=$perpage" : '').(!empty($other_info_str) ? '&'.$other_info_str : '');

    return $scheme_url;
}
?>
