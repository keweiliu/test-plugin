<?php
/*======================================================================*\
 || #################################################################### ||
 || # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
 || # This file may not be redistributed in whole or significant part. # ||
 || # This file is part of the Tapatalk package and should not be used # ||
 || # and distributed for any other purpose that is not approved by    # ||
 || # Quoord Systems Ltd.                                              # ||
 || # http://www.tapatalk.com | http://www.tapatalk.com/license.html   # ||
 || #################################################################### ||
 \*======================================================================*/

defined('IN_MOBIQUO') or exit;

$phrasegroups = array();
$actiontemplates = array();
$specialtemplates = array(
    'userstats',
    'birthdaycache',
    'maxloggedin',
    'iconcache',
    'eventcache',
    'mailqueue'
);
$globaltemplates = array(
    'ad_forumhome_afterforums',
    'FORUMHOME',
    'forumhome_event',
    'forumhome_forumbit_level1_nopost',
    'forumhome_forumbit_level1_post',
    'forumhome_forumbit_level2_nopost',
    'forumhome_forumbit_level2_post',
    'forumhome_lastpostby',
    'forumhome_loggedinuser',
    'forumhome_moderator',
    'forumhome_subforumbit_nopost',
    'forumhome_subforumbit_post',
    'forumhome_subforumseparator_nopost',
    'forumhome_subforumseparator_post',
    'forumhome_markread_script',
    'forumhome_birthdaybit'
);

require_once(SCRIPT_ROOT . '/global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumlist.php');

function get_config_func()
{
    global $vbulletin, $tt_config, $permissions, $db;


    $return_config = array(
        'sys_version'   => new xmlrpcval(@$vbulletin->versionnumber, 'string'),
        'is_open'       => new xmlrpcval(isset($vbulletin->products['tapatalk'])? $vbulletin->products['tapatalk'] && $vbulletin->options['bbactive'] : $tt_config['is_open'] && $vbulletin->options['bbactive'], 'boolean'),
        'guest_okay'    => new xmlrpcval(isset($vbulletin->options['tapatalk_guest_okay']) ? $vbulletin->options['tapatalk_guest_okay'] : $tt_config['guest_okay'], 'boolean'),
        'push'          => new xmlrpcval('1', 'string'),
    );
    //$return_config['is_beta'] = new xmlrpcval('1', 'string');



    //Check if plugin is valid.
    $is_open = false;
    $open_result_text = 'Tapatalk Plugin is not activated in your forum';
    $product = $db->query_first("
        SELECT *
        FROM " . TABLE_PREFIX . "product
        WHERE productid = 'tapatalk'
    ");
    if($product && $product['active']  && $tt_config['is_open'])
    {
        $config_version =  trim(str_replace('vb40_', '', $tt_config['version']));
        $hook_version = trim($product['version']);
        if($config_version == $hook_version)
            $is_open = true;
        else
            $open_result_text = "Tapatalk plug-in file 'product-tapatalk.xml' was not installed or updated. This may affect some features from working correctly in this forum. Please inform the forum admin to complete/fix the Tapatalk installation";
        $return_config['hook_version'] = new xmlrpcval('vb40_'.$hook_version, 'string');
    }

    //VBSEO forum/thread/post rule
    if(defined('VBSEO_ENABLED'))
    {
        if(@include_once(DIR . '/vbseo/includes/functions_vbseo.php'))
        {
            if(defined('VBSEO_REWRITE_FORUM') && defined('VBSEO_URL_FORUM') && VBSEO_REWRITE_FORUM)
            {
                if(preg_match('/%forum_id%/',VBSEO_URL_FORUM))
                {
                    $expression = preg_replace('/%forum_id%/','(\d+)',VBSEO_URL_FORUM);
                    $expression = preg_replace('/%\w+_id%/','\d+', $expression);
                    $expression = preg_replace('/%.*?%/', '.*?', $expression);
                    if(substr($expression, -1) == '/') $expression = substr($expression,0,-1);
                    $return_config['vbseo_forum_rule'] = new xmlrpcval($expression,'string');
                }
            }
            if(defined('VBSEO_REWRITE_THREADS') && defined('VBSEO_URL_THREAD') && VBSEO_REWRITE_THREADS)
            {
                if(preg_match('/%thread_id%/',VBSEO_URL_THREAD))
                {
                    $expression = preg_replace('/%thread_id%/','(\d+)',VBSEO_URL_THREAD);
                    $expression = preg_replace('/%\w+_id%/','\d+', $expression);
                    $expression = preg_replace('/%.*?%/', '.*?', $expression);
                    if(substr($expression, -1) == '/') $expression = substr($expression,0,-1);
                    $return_config['vbseo_thread_rule'] = new xmlrpcval($expression,'string');
                }
            }
            if(defined('VBSEO_REWRITE_THREADS') && defined('VBSEO_URL_THREAD_GOTOPOST') && VBSEO_REWRITE_THREADS)
            {
                if(preg_match('/%post_id%/',VBSEO_URL_THREAD_GOTOPOST))
                {
                    $expression = preg_replace('/%post_id%/','(\d+)',VBSEO_URL_THREAD_GOTOPOST);
                    $expression = preg_replace('/%\w+_id%/','\d+', $expression);
                    $expression = preg_replace('/%.*?%/', '.*?', $expression);
                    if(substr($expression, -1) == '/') $expression = substr($expression,0,-1);
                    $return_config['vbseo_post_rule'] = new xmlrpcval($expression,'string');
                }
            }
        }
    }


    $return_config['is_open'] = new xmlrpcval($is_open, 'boolean');
    if(!$is_open)
        $return_config['result_text'] = new xmlrpcval($open_result_text, 'base64');
    if(!$vbulletin->options['bbactive'])
        $return_config['result_text'] = new xmlrpcval($vbulletin->options['bbclosedreason'], 'base64');
    
    foreach($tt_config as $key => $value){
        if(!$return_config[$key] && !is_array($value)){
            $return_config[$key] = new xmlrpcval(mobiquo_encode($value), 'string');
        }
    }
    
    if (isset($vbulletin->options['tapatalk_delete_option']) && $vbulletin->options['tapatalk_delete_option']) {
        $return_config['advanced_delete'] = new xmlrpcval('1', 'string');
    }
    
    $return_config['advanced_merge'] = new xmlrpcval('1', 'string');
    
    if (!$vbulletin->userinfo['userid'])
    {
        require_once(DIR . "/vb/legacy/currentuser.php");
        $current_user = new vB_Legacy_CurrentUser();
        
        if ($current_user->hasPermission('forumpermissions', 'cansearch') && $vbulletin->options['enablesearches']) {
            $return_config['guest_search'] = new xmlrpcval('1', 'string');
        }
        
        if ($vbulletin->options['WOLenable'] && $permissions['wolpermissions'] & $vbulletin->bf_ugp_wolpermissions['canwhosonline']) {
            $return_config['guest_whosonline'] = new xmlrpcval('1', 'string');
        }
    }


    //forum stastics
    $activeusers = '';
    $show['loggedinusers'] = false;
    
    cache_ordered_forums(1, 1);
    if ($vbulletin->options['showmoderatorcolumn'])
    {
        cache_moderators();
    }
    else if ($vbulletin->userinfo['userid'])
    {
        cache_moderators($vbulletin->userinfo['userid']);
    }
    // define max depth for forums display based on $vbulletin->options[forumhomedepth]
    define('MAXFORUMDEPTH', $vbulletin->options['forumhomedepth']);
    
    $forumbits = construct_forum_bit($forumid);

    // ### BOARD STATISTICS #################################################

    // get total threads & posts from the forumcache
    $totalthreads = 0;
    $totalposts = 0;
    if (is_array($vbulletin->forumcache))
    {
        foreach ($vbulletin->forumcache AS $forum)
        {
            $totalthreads += $forum['threadcount'];
            $totalposts += $forum['replycount'];
        }
    }

    // get total members and newest member from template
    $numbermembers = $vbulletin->userstats['numbermembers'];
    $newusername = $vbulletin->userstats['newusername'];
    $newuserid = $vbulletin->userstats['newuserid'];
    $activemembers = $vbulletin->userstats['activemembers'];
    $show['activemembers'] = ($vbulletin->options['activememberdays'] > 0 AND ($vbulletin->options['activememberoptions'] & 2)) ? true : false;
		$stats = array(
        'user' => new xmlrpcval($numbermembers, 'int'),
        'topic' => new xmlrpcval($totalthreads, 'int'),
        'post'   => new xmlrpcval($totalposts, 'int')
    );

    if($show['activemembers'])
    	$stats['active'] =	new xmlrpcval($activemembers, 'int');
    $return_config['ads_disabled_group'] = new xmlrpcval($vbulletin->options['tapatalk_ads'], 'string');
    $return_config['guest_group_id'] = new xmlrpcval('1', 'string');

	$return_config['stats'] = new xmlrpcval($stats, 'struct');
    return new xmlrpcresp(new xmlrpcval($return_config, 'struct'));
}
