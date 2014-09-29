<?php
/**
 * User API functions
 *
 * @author Shitiz Garg
 * @copyright Copyright 2009 Quoord Systems Ltd. All Rights Reserved.
 * @license This file or any content of the file should not be
 *                redistributed in any form of matter. This file is a part of
 *                Tapatalk package and should not be used and distributed
 *                in any form not approved by Quoord Systems Ltd.
 *                http://tapatalk.com | http://taptatalk.com/license.html
 */

if (!defined('IN_MOBIQUO'))
	die('Hacking Attempt...');

function mob_get_user_topic($rpcmsg)
{
     global $mobdb, $context, $scripturl, $modSettings, $sourcedir;

     require_once($sourcedir . '/Subs-Auth.php');

     // Get the user
     $username = $rpcmsg->getScalarValParam(0);
     $id_user = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : null;

     // If we have an user ID, use it otherwise search for the user
     if (!is_null($id_user))
     {
          $request = $mobdb->query('
               SELECT ID_MEMBER
               FROM {db_prefix}members
               WHERE ID_MEMBER = {int:member}',
               array(
                    'member' => $id_user,
               )
          );
          if ($mobdb->num_rows($request) == 0)
               $id_user = null;
          else
               list ($id_user) = $mobdb->fetch_row($request);
          $mobdb->free_result($request);
     }

     // Otherwise search from the DB,
     if (is_null($id_user))
     {
         $username = utf8ToAscii($username);
          $members = findMembers($username);
          if (empty($members))
               mob_error('user not found');
          $member_ids = array_keys($members);
          $id_user = $members[$member_ids[0]]['id'];
     }

     // Return the topics by this user
     return new xmlrpcresp(new xmlrpcval(get_topics('t.ID_MEMBER_STARTED = {int:member}', array('member' => $id_user), 0, 50, true), 'array'));
}

function mob_get_user_reply_post($rpcmsg)
{
    global $mobdb, $context, $scripturl, $modSettings, $sourcedir, $user_info;
    
    require_once($sourcedir . '/Subs-Auth.php');
    
    // Get the user
    $username = $rpcmsg->getScalarValParam(0);
    $id_user = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : null;
    
    // If we have an user ID, use it otherwise search for the user
    if (!is_null($id_user))
    {
        $request = $mobdb->query('
            SELECT ID_MEMBER
            FROM {db_prefix}members
            WHERE ID_MEMBER = {int:member}',
        array(
            'member' => $id_user,
        )
    );
    if ($mobdb->num_rows($request) == 0)
        $id_user = null;
    else
        list ($id_user) = $mobdb->fetch_row($request);
        $mobdb->free_result($request);
    }
    
    // Otherwise search from the DB,
    if (is_null($id_user))
    {
        $username = utf8ToAscii($username);
        $members = findMembers($username);
        if (empty($members))
            mob_error('user not found');
        $member_ids = array_keys($members);
        $id_user = $members[$member_ids[0]]['id'];
    }

    // Load this user's post
    $request = $mobdb->query('
        SELECT m.ID_MSG, m.posterTime AS poster_time, m.subject, m.body, t.ID_TOPIC, fm.subject AS topic_subject, b.name AS board_name, b.ID_BOARD AS id_board, t.locked, t.isSticky, IFNULL(mem.realName, m.posterName) AS member_name, t.numViews as views, t.numReplies as replies,'
        . ($user_info['is_guest'] ? '1 AS `read`' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, 0)) >= m.ID_MSG_MODIFIED AS `read`, 
                                                     IFNULL(al.ID_ATTACH, 0) AS last_id_attach, al.filename AS last_filename, al.attachmentType AS last_attachment_type, mem.avatar AS last_avatar') . '
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (t.ID_TOPIC = m.ID_TOPIC)
            INNER JOIN {db_prefix}messages AS fm ON (fm.ID_MSG = t.ID_FIRST_MSG)
            INNER JOIN {db_prefix}boards as b on (b.ID_BOARD = m.ID_BOARD)
            LEFT JOIN {db_prefix}members AS mem ON (mem.ID_MEMBER = m.ID_MEMBER)' . (!$user_info['is_guest'] ? "
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = m.ID_TOPIC AND lt.ID_MEMBER = {int:cur_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = m.ID_BOARD AND lmr.ID_MEMBER = {int:cur_member})
            LEFT JOIN {db_prefix}attachments AS al ON (al.ID_MEMBER = mem.ID_MEMBER)" : '') . '
        WHERE m.ID_MEMBER = {int:member}
            AND {query_see_board}
        ORDER BY m.ID_MSG DESC
        LIMIT 50',
        array(
            'member' => $id_user,
            'cur_member' => $user_info['id'],
        )
    );
    $posts = array();
    while ($row = $mobdb->fetch_assoc($request))
    {
        $last_avatar =  $row['last_avatar'] == '' ? ($row['last_id_attach'] > 0 ? (empty($row['last_attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['last_id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['last_filename']) : '') : (stristr($row['last_avatar'], 'http://') ? $row['last_avatar'] : $modSettings['avatar_url'] . '/' . $row['last_avatar']);

        $posts[] = array(
            'id' => $row['ID_TOPIC'],
            'post_id' => $row['ID_MSG'],
            'subject' => $row['topic_subject'],
            'board' => array(
                'id' => $row['id_board'],
                'name' => $row['board_name'],
            ),
            'first_post' => array(
                'poster' => array(
                    'id' => 0,
                    'name' => '',
                ),
            ),
            'last_post' => array(
                'id' => $row['ID_MSG'],
                'subject' => $row['subject'],
                'body' => $row['body'],
                'time' => $row['poster_time'],
                'member' => array(
                    'id' => $id_user,
                    'name' => $row['member_name'],
                    'avatar' => array(
                        'href' => $last_avatar,
                    ),
                ),
            ),
            'is_sticky' => $row['isSticky'],
            'locked' => $row['locked'],
            'views' => $row['views'],
            'replies' => $row['replies'],
            'new' => !empty($row['read']),
        );
    }
    $mobdb->free_result($request);

    // Return the posts
    return new xmlrpcresp(new xmlrpcval(get_topics_xmlrpc($posts, false), 'array'));
}

function mob_get_user_info($rpcmsg)
{
    global $mobdb, $context, $modSettings, $memberContext, $user_profile, $sourcedir, $txt, $user_info;
    
    $username = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : null;
    $id_user = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : null;
    
    if (empty($username) && empty($id_user))
        $id_user = $user_info['id'];
    
    $id_user = intval($id_user);
    
    require_once($sourcedir . '/Subs-Auth.php');

    // If we have an user ID, use it otherwise search for the user
    if (!is_null($id_user))
    {
        $request = $mobdb->query('
            SELECT ID_MEMBER
            FROM {db_prefix}members
            WHERE ID_MEMBER = {int:member}',
        array(
            'member' => $id_user,
        )
        );
        if ($mobdb->num_rows($request) == 0)
            $id_user = null;
        else
            list ($id_user) = $mobdb->fetch_row($request);
        $mobdb->free_result($request);
    }
    
    // Otherwise search from the DB,
    if (is_null($id_user))
    {
        $username = utf8ToAscii($username);
        $members = findMembers($username);
        if (empty($members))
            mob_error('user not found');
        $member_ids = array_keys($members);
        $id_user = $members[$member_ids[0]]['id'];
    }
    
    loadMemberData($id_user);
    loadMemberContext($id_user);
    
    $member = $memberContext[$id_user];
    
    // Is the guy banned?
    $request = $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}ban_items AS bi
            INNER JOIN {db_prefix}ban_groups AS bg ON (bg.ID_BAN_GROUP = bi.ID_BAN_GROUP)
        WHERE bi.ID_MEMBER = {int:member}
            AND (bg.expire_time IS NULL OR bg.expire_time > {int:time})
            AND bg.cannot_access != 0',
        array(
            'member' => $member['id'],
            'time' => time(),
        )
    );
    $banned = false;
    list ($count) = $mobdb->fetch_row($request);
    if ($count > 0)
      $banned = true;
    $mobdb->free_result($request);
    
    loadLanguage('Profile');
    
    // Load the current action
    $current_action = determineActions($user_profile[$id_user]['url']);
    
    // Figure out all the custom fields
    $custom_fields = array();

    $custom_fields[] = new xmlrpcval(array(
        'name'  => new xmlrpcval($txt[87], 'base64'),
        'value' => new xmlrpcval(!empty($member['group']) ? $member['group'] : $member['post_group'], 'base64')
    ), 'struct');
    
    // Custom communication fields
    $fields = array('icq', 'aim', 'msn', 'yim');
    $_fields = array($txt[513], $txt[603], $txt['MSN'], $txt[604]);
    foreach ($fields as $k => $field)
    {
        if (!empty($member[$field]['name']))
        {
            $custom_fields[] = new xmlrpcval(array(
                'name'  => new xmlrpcval(processSubject($_fields[$k]), 'base64'),
                'value' => new xmlrpcval(processSubject($member[$field]['name']), 'base64')
            ), 'struct');
        }
    }

    if ($modSettings['karmaMode'] == '1' || $modSettings['karmaMode'] == '2')
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval(processSubject($modSettings['karmaLabel']), 'base64'),
            'value' => new xmlrpcval(processSubject($modSettings['karmaMode'] == '1' ? $member['karma']['good'] - $member['karma']['bad'] : '+' . $member['karma']['good'] . '/-' . $member['karma']['bad']), 'base64')
        ), 'struct');
    
    if (!empty($member['gender']['name']))
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval(processSubject($txt[231]), 'base64'),
            'value' => new xmlrpcval(processSubject($member['gender']['name']), 'base64')
        ), 'struct');
    
    if (!empty($member['location']))
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval(processSubject($txt[227]), 'base64'),
            'value' => new xmlrpcval(processSubject($member['location']), 'base64')
        ), 'struct');
    
    if (!empty($member['signature']))
        $custom_fields[] = new xmlrpcval(array(
            'name'  => new xmlrpcval(processSubject($txt[85]), 'base64'),
            'value' => new xmlrpcval(processSubject($member['signature']), 'base64')
        ), 'struct');

    // Return the response
    return new xmlrpcresp(new xmlrpcval(array(
        'user_id'           => new xmlrpcval($member['id'], 'string'),
        'user_name'         => new xmlrpcval(processUsername(!empty($member['name']) ? $member['name'] : $member['username']), 'base64'),
        'display_name'      => new xmlrpcval(processUsername(!empty($member['name']) ? $member['name'] : $member['username']), 'base64'),
        'post_count'        => new xmlrpcval($member['posts'], 'int'),
        'reg_time'          => new xmlrpcval(mobiquo_time($member['registered_timestamp']), 'dateTime.iso8601'),
        'is_online'         => new xmlrpcval(!empty($user_profile[$id_user]['isOnline']), 'boolean'),
        'accept_pm'         => new xmlrpcval(true, 'boolean'),
        'display_text'      => new xmlrpcval(processSubject($member['title']), 'base64'),
        'icon_url'          => new xmlrpcval($member['avatar']['href'], 'string'),
        'current_activity'  => new xmlrpcval(processSubject($current_action), 'base64'),
        'current_action'    => new xmlrpcval(processSubject($current_action), 'base64'),
        'is_ban'            => new xmlrpcval($banned, 'boolean'),
        'can_ban'           => new xmlrpcval(allowedTo('manage_bans'), 'boolean'),
       'custom_fields_list' => new xmlrpcval($custom_fields, 'array'),
    ), 'struct'));
}

function mob_prefetch_account($rpcmsg)
{
    global $mobdb;
    
    $email = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : '';
    
    $request = $mobdb->query('
        SELECT mem.ID_MEMBER, mem.memberName, mem.realName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE mem.emailAddress = {string:email}',
        array(
            'email' => $email,
        )
    );
    if ($mobdb->num_rows($request))
        $profile = $mobdb->fetch_assoc($request);
    
    $mobdb->free_result($request);
    
    if ($profile)
    {
        $xmlrpc_user_info = array(
            'result'                => new xmlrpcval($profile['ID_MEMBER'] ? true : false, 'boolean'),
            'user_id'               => new xmlrpcval($profile['ID_MEMBER']),
            'login_name'            => new xmlrpcval(processUsername($profile['memberName']), 'base64'),
            'display_name'          => new xmlrpcval(processUsername($profile['realName']),'base64'),
            'avatar'                => new xmlrpcval(get_avatar($profile)),
        );
    }
    else
    {
        $xmlrpc_user_info['result'] = new xmlrpcval(false, 'boolean');
    }
    
    return new xmlrpcresp(new xmlrpcval($xmlrpc_user_info, 'struct'));
}

function mob_forget_password($rpcmsg)
{
    global $sourcedir, $db_prefix, $scripturl, $txt;
    
    $token = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : '';
    $code = $rpcmsg->getParam(2) ? $rpcmsg->getScalarValParam(2) : '';
    $_POST['user'] = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : '';
    $_POST['user'] = utf8ToAscii($_POST['user']);
    
    $_POST = htmltrim__recursive($_POST);
    $_POST = stripslashes__recursive($_POST);
    $_POST = htmlspecialchars__recursive($_POST);
    $_POST = addslashes__recursive($_POST);
    
    loadLanguage('Profile');
    loadTemplate('Reminder');
    
    //checkSession();

    // You must enter a username/email address.
    if (!isset($_POST['user']) || $_POST['user'] == '')
        fatal_lang_error(40, false);

    // Find the user!
    $request = db_query("
        SELECT ID_MEMBER, realName, memberName, emailAddress, is_activated, validation_code, ID_GROUP
        FROM {$db_prefix}members
        WHERE memberName = '$_POST[user]'
        LIMIT 1", __FILE__, __LINE__);
    if (mysql_num_rows($request) == 0)
    {
        mysql_free_result($request);

        $request = db_query("
            SELECT ID_MEMBER, realName, memberName, emailAddress, is_activated, validation_code, ID_GROUP
            FROM {$db_prefix}members
            WHERE emailAddress = '$_POST[user]'
            LIMIT 1", __FILE__, __LINE__);
        if (mysql_num_rows($request) == 0)
            fatal_lang_error(40, false);
    }

    $row = mysql_fetch_assoc($request);
    mysql_free_result($request);

    // If the user isn't activated/approved, give them some feedback on what to do next.
    if ($row['is_activated'] != 1)
    {
        // Awaiting approval...
        if (trim($row['validation_code']) == '')
            fatal_error($txt['registration_not_approved'] . ' <a href="' . $scripturl . '?action=activate;user=' . $_POST['user'] . '">' . $txt[662] . '</a>.', false);
        else
            fatal_error($txt['registration_not_activated'] . ' <a href="' . $scripturl . '?action=activate;user=' . $_POST['user'] . '">' . $txt[662] . '</a>.', false);
    }

    // You can't get emailed if you have no email address.
    $row['emailAddress'] = trim($row['emailAddress']);
    if ($row['emailAddress'] == '')
        fatal_error($txt[394]);

    // verify Tapatalk Authorization
    if ($token && $code && $row['ID_GROUP'] != 1)
    {
        $ttid = TapatalkSsoVerification($token, $code);
        if ($ttid && $ttid->result)
        {
            $tapatalk_id_email = $ttid->email;
            if (strtolower($row['emailAddress']) == strtolower($tapatalk_id_email))
            {
                $response = array(
                    'result'    => new xmlrpcval(true, 'boolean'),
                    'verified'  => new xmlrpcval(true, 'boolean'),
                );
                
                return new xmlrpcresp(new xmlrpcval($response, 'struct'));
            }
        }
    }
    
    // Randomly generate a new password, with only alpha numeric characters that is a max length of 10 chars.
    require_once($sourcedir . '/Subs-Members.php');
    $password = generateValidationCode();

    // Set the password in the database.
    updateMemberData($row['ID_MEMBER'], array('validation_code' => "'" . substr(md5($password), 0, 10) . "'"));

    require_once($sourcedir . '/Subs-Post.php');

    sendmail($row['emailAddress'], $txt['reminder_subject'],
        sprintf($txt['sendtopic_dear'], $row['realName']) . "\n\n" .
        "$txt[reminder_mail]:\n\n" .
        "$scripturl?action=reminder;sa=setpassword;u=$row[ID_MEMBER];code=$password\n\n" .
        "$txt[512]: $user_info[ip]\n\n" .
        "$txt[35]: $row[memberName]\n\n" .
        $txt[130]);

    $response = array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval(processSubject($txt['reminder_sent']), 'base64'),
    );
    
    return new xmlrpcresp(new xmlrpcval($response, 'struct'));
}

function mob_update_password($rpcmsg)
{
    global $txt, $modSettings;
    global $cookiename, $context;
    global $sourcedir, $scripturl, $db_prefix;
    global $ID_MEMBER, $user_info;
    global $newpassemail, $user_profile, $validationCode;
    
    loadLanguage('Profile');
    
    // Start with no updates and no errors.
    $profile_vars = array();
    $post_errors = array();
    $good_password = false;
    
    // reset directly with tapatalk id credential
    if ($rpcmsg->getParam(2))
    {
        $_POST['passwrd1'] = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : '';
        $_POST['passwrd1'] = utf8ToAscii($_POST['passwrd1']);
        $token = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : '';
        $code = $rpcmsg->getParam(2) ? $rpcmsg->getScalarValParam(2) : '';
        
        // verify Tapatalk Authorization
        if ($token && $code)
        {
            $ttid = TapatalkSsoVerification($token, $code);
            
            if ($ttid && $ttid->result)
            {
                $tapatalk_id_email = $ttid->email;
                
                if (empty($ID_MEMBER) && $ID_MEMBER = emailExists($tapatalk_id_email))
                {
                    loadMemberData($ID_MEMBER, false, 'profile');
                    $user_info = $user_profile[$ID_MEMBER];
                
                    $user_info['is_guest'] = false;
                    $user_info['is_admin'] = $user_info['id_group'] == 1 || in_array(1, explode(',', $user_info['additionalGroups']));
                    $user_info['id'] = $ID_MEMBER;
                    
                    if (empty($user_info['additionalGroups']))
                        $user_info['groups'] = array($user_info['ID_GROUP'], $user_info['ID_POST_GROUP']);
                    else
                        $user_info['groups'] = array_merge(array($user_info['ID_GROUP'], $user_info['ID_POST_GROUP']), explode(',', $user_info['additionalGroups']));
                    
                    $user_info['groups'] = array_unique(array_map('intval', $user_info['groups']));
                    
                    loadPermissions();
                }
                
                if (strtolower($user_info['emailAddress']) == strtolower($tapatalk_id_email) && $user_info['ID_GROUP'] != 1)
                {
                    $good_password = true;
                }
            }
        }
        
        if (!$good_password) get_error('Failed to update password');
    }
    else
    {
        $_POST['oldpasswrd'] = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : '';
        $_POST['passwrd1'] = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : '';
        $_POST['passwrd1'] = utf8ToAscii($_POST['passwrd1']);
    }
    
    // Clean up the POST variables.
    $_POST = htmltrim__recursive($_POST);
    $_POST = stripslashes__recursive($_POST);
    $_POST = htmlspecialchars__recursive($_POST);
    $_POST = addslashes__recursive($_POST);
    
    $memberResult = loadMemberData($ID_MEMBER, false, 'profile');
    
    if (!is_array($memberResult))
        fatal_lang_error(453, false);
    
    $memID = $ID_MEMBER;
    $context['user']['is_owner'] = true;

    isAllowedTo(array('manage_membergroups', 'profile_identity_any', 'profile_identity_own'));

    // You didn't even enter a password!
    if (trim($_POST['oldpasswrd']) == '' && !$good_password)
        fatal_error($txt['profile_error_no_password']);
    
    // Since the password got modified due to all the $_POST cleaning, lets undo it so we can get the correct password
    $_POST['oldpasswrd'] = addslashes(un_htmlspecialchars(stripslashes($_POST['oldpasswrd'])));

    // Does the integration want to check passwords?
    if (isset($modSettings['integrate_verify_password']) && function_exists($modSettings['integrate_verify_password']))
        if (call_user_func($modSettings['integrate_verify_password'], $user_profile[$memID]['memberName'], $_POST['oldpasswrd'], false) === true)
            $good_password = true;

    // Bad password!!!
    if (!$good_password && $user_info['passwd'] != sha1(strtolower($user_profile[$memID]['memberName']) . $_POST['oldpasswrd']))
        fatal_error($txt['profile_error_bad_password']);

    // Let's get the validation function into play...
    require_once($sourcedir . '/Subs-Auth.php');
    $passwordErrors = validatePassword($_POST['passwrd1'], $user_info['username'], array($user_info['name'], $user_info['email']));

    // Were there errors?
    if ($passwordErrors != null)
        fatal_error($txt['profile_error_password_' . $passwordErrors]);

    // Set up the new password variable... ready for storage.
    $profile_vars['passwd'] = '\'' . sha1(strtolower($user_profile[$memID]['memberName']) . un_htmlspecialchars(stripslashes($_POST['passwrd1']))) . '\'';

    // If we've changed the password, notify any integration that may be listening in.
    if (isset($modSettings['integrate_reset_pass']) && function_exists($modSettings['integrate_reset_pass']))
        call_user_func($modSettings['integrate_reset_pass'], $user_profile[$memID]['memberName'], $user_profile[$memID]['memberName'], $_POST['passwrd1']);

    updateMemberData($memID, $profile_vars);
    
    require_once($sourcedir . '/Subs-Auth.php');
    setLoginCookie(60 * $modSettings['cookieTime'], $memID, sha1(sha1(strtolower($user_profile[$memID]['memberName']) . un_htmlspecialchars(stripslashes($_POST['passwrd1']))) . $user_profile[$memID]['passwordSalt']));
    
    $response = array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64'),
    );
    
    return new xmlrpcresp(new xmlrpcval($response, 'struct'));
}

function mob_update_email($rpcmsg)
{
    global $txt, $modSettings;
    global $cookiename, $context;
    global $sourcedir, $scripturl, $db_prefix;
    global $ID_MEMBER, $user_info;
    global $newpassemail, $user_profile, $validationCode;
    
    loadLanguage('Profile');
    
    // Start with no updates and no errors.
    $profile_vars = array();
    $post_errors = array();
    
    $_POST['oldpasswrd'] = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : '';
    $_POST['emailAddress'] = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : '';
    
    // Clean up the POST variables.
    $_POST = htmltrim__recursive($_POST);
    $_POST = stripslashes__recursive($_POST);
    $_POST = htmlspecialchars__recursive($_POST);
    $_POST = addslashes__recursive($_POST);
    
    $memberResult = loadMemberData($ID_MEMBER, false, 'profile');
    
    if (!is_array($memberResult))
        fatal_lang_error(453, false);
    
    $memID = $ID_MEMBER;
    $newpassemail = false;
    $context['user']['is_owner'] = true;

    isAllowedTo(array('manage_membergroups', 'profile_identity_any', 'profile_identity_own'));

    // You didn't even enter a password!
    if (trim($_POST['oldpasswrd']) == '')
        fatal_error($txt['profile_error_no_password']);
    
    // This block is only concerned with email address validation..
    if (strtolower($_POST['emailAddress']) != strtolower($user_profile[$memID]['emailAddress']))
    {
        $_POST['emailAddress'] = strtr($_POST['emailAddress'], array('&#039;' => '\\\''));

        // Prepare the new password, or check if they want to change their own.
        if (!empty($modSettings['send_validation_onChange']) && !allowedTo('moderate_forum'))
        {
            require_once($sourcedir . '/Subs-Members.php');
            $validationCode = generateValidationCode();
            $profile_vars['validation_code'] = '\'' . $validationCode . '\'';
            $profile_vars['is_activated'] = '2';
            $newpassemail = true;
        }

        // Check the name and email for validity.
        if (trim($_POST['emailAddress']) == '')
            fatal_error($txt['profile_error_no_email']);
        if (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', stripslashes($_POST['emailAddress'])) == 0)
            fatal_error($txt['profile_error_bad_email']);

        // Email addresses should be and stay unique.
        $request = db_query("
            SELECT ID_MEMBER
            FROM {$db_prefix}members
            WHERE ID_MEMBER != $memID
                AND emailAddress = '$_POST[emailAddress]'
            LIMIT 1", __FILE__, __LINE__);
        if (mysql_num_rows($request) > 0)
            fatal_error($txt['profile_error_email_taken']);
        mysql_free_result($request);

        $profile_vars['emailAddress'] = '\'' . $_POST['emailAddress'] . '\'';
    }
    
    if (!empty($profile_vars))
        updateMemberData($memID, $profile_vars);
    
    // Send an email?
    if ($newpassemail)
    {
        require_once($sourcedir . '/Subs-Post.php');

        // Send off the email.
        sendmail($_POST['emailAddress'], $txt['activate_reactivate_title'] . ' ' . $context['forum_name'],
            "$txt[activate_reactivate_mail]\n\n" .
            "$scripturl?action=activate;u=$memID;code=$validationCode\n\n" .
            "$txt[activate_code]: $validationCode\n\n" .
            $txt[130]);

        // Log the user out.
        db_query("
            DELETE FROM {$db_prefix}log_online
            WHERE ID_MEMBER = $memID", __FILE__, __LINE__);
        $_SESSION['log_time'] = 0;
        $_SESSION['login_' . $cookiename] = serialize(array(0, '', 0));
    }
    
    $response = array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64'),
    );
    
    return new xmlrpcresp(new xmlrpcval($response, 'struct'));
}