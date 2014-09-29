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

require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(CWD1. '/include/function_text_parse.php');

function get_post_func($xmlrpc_params)
{
    global $vbulletin, $vbphrase, $html_content, $threadinfo, $foruminfo;

    $decode_params = php_xmlrpc_decode($xmlrpc_params);
    $postid = intval($decode_params[0]);
    $html_content = isset($decode_params[1]) && $decode_params[1];
    
    $vbulletin->GPC['postid'] = $postid;

    if ($vbulletin->GPC['postid'] AND $postinfo = mobiquo_verify_id('post', $vbulletin->GPC['postid'], 0, 1))
    {
        $postid =& $postinfo['postid'];
        $vbulletin->GPC['threadid'] =& $postinfo['threadid'];
    }

    // automatically query $threadinfo & $foruminfo if $threadid exists
    if ($vbulletin->GPC['threadid'] AND $threadinfo = mobiquo_verify_id('thread', $vbulletin->GPC['threadid'], 0, 1))
    {
        $threadid =& $threadinfo['threadid'];
        $vbulletin->GPC['forumid'] = $forumid = $threadinfo['forumid'];
        if ($forumid)
        {
            $foruminfo = fetch_foruminfo($threadinfo['forumid']);
            if (($foruminfo['styleoverride'] == 1 OR $vbulletin->userinfo['styleid'] == 0) AND !defined('BYPASS_STYLE_OVERRIDE'))
            {
                $codestyleid = $foruminfo['styleid'];
            }
        }
    }
    
    if (!$postinfo['postid'] OR $postinfo['isdeleted'] OR (!$postinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
        return_fault(fetch_error('invalidid', $vbphrase['post']));
    }

    if (!$threadinfo['threadid'] OR $threadinfo['isdeleted'] OR (!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')))
    {
        return_fault(fetch_error('invalidid', $vbphrase['thread']));
    }

    if ($vbulletin->options['wordwrap'])
    {
        $threadinfo['title'] = fetch_word_wrapped_string($threadinfo['title']);
    }

    // get permissions info
    $_permsgetter_ = 'edit post';
    $forumperms = fetch_permissions($threadinfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview'])
    OR
    !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR
    (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND
    ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    )
    {
        return_fault();
    }

    $foruminfo = fetch_foruminfo($threadinfo['forumid'], false);

    // check if there is a forum password and if so, ensure the user has it set
    if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false))
        return_fault('Your administrator has required a password to access this forum.');
    
    $new_post = get_post_from_id($postid);
    
    return new xmlrpcresp(new xmlrpcval($new_post, 'struct'));
}