<?php
/***************************************************************
* Mobiquo-Functions.php                                        *
* Copyright 2009 Quoord Systems Ltd. All Rights Reserved.     *
* Created by Dragooon (http://smf-media.com)                   *
****************************************************************
* This file or any content of the file should not be           *
* redistributed in any form of matter. This file is a part of  *
* Tapatalk package and should not be used and distributed      *
* in any form not approved by Quoord Systems Ltd.              *
* http://tapatalk.com | http://taptatalk.com/license.html      *
****************************************************************
* Contains functions for various methods on mobiquo            *
***************************************************************/

// @TODO - Fix up unread_count in get_forum
// @TODO - Fix up shortened_message in get_topic - currently it strips of all the tags, we need to keep img and url

if (!defined('SMF'))
    die('Hacking Attempt...');

loadLanguage('Errors');

// Returns the configuration for the forum
function method_get_config()
{
    global $modSettings, $mobiquo_config, $context, $user_info;
    
    if ($user_info['is_guest'] && allowedTo('search_posts'))
        $mobiquo_config['guest_search'] = 1;
    
    if ($user_info['is_guest'] && allowedTo('who_view'))
        $mobiquo_config['guest_whosonline'] = 1;

    if(!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 3)
    {
        $mobiquo_config['sign_in'] = 0;
        $mobiquo_config['inappreg'] = 0;
        
        $mobiquo_config['sso_signin'] = 0;
        $mobiquo_config['sso_register'] = 0;
        $mobiquo_config['native_register'] = 0;
    }
    
    if (!function_exists('curl_init') && !@ini_get('allow_url_fopen'))
    {
        $mobiquo_config['sign_in'] = 0;
        $mobiquo_config['inappreg'] = 0;
        
        $mobiquo_config['sso_login'] = 0;
        $mobiquo_config['sso_signin'] = 0;
        $mobiquo_config['sso_register'] = 0;
    }

    // Return the forum configuration
    $response = '
<params>
<param>
<value>
<struct>
<member>
<name>is_open</name>
<value><boolean>' . (!empty($context['in_maintenance']) || empty($mobiquo_config['is_open']) ? 0 : 1) . '</boolean></value>
</member>
<member>
<name>guest_okay</name>
<value><boolean>' . (empty($modSettings['allow_guestAccess']) || empty($mobiquo_config['guest_okay']) ? 0 : 1). '</boolean></value>
</member>
<member>
<name>forum_name</name>
<value><base64>' . base64_encode($context['forum_name']) . '</base64></value>
</member>
<member>
<name>charset</name>
<value><string>' . $context['character_set'] . '</string></value>
</member>';
    foreach($mobiquo_config as $key => $value) {
        if (in_array($key, array('is_open', 'guest_okay', 'mod_function'))) continue;
        $response .= '
<member>
<name>'.$key.'</name>
<value><string>'. $value .'</string></value>
</member>';
    }

    $response .= '
<member><name>stats</name>
<value><struct>
<member><name>user</name>
<value><int>'.intval($modSettings['totalMembers']).'</int></value>
</member>
<member><name>topic</name>
<value><int>'.intval($modSettings['totalTopics']).'</int></value>
</member>
<member><name>post</name>
<value><int>'.intval($modSettings['totalMessages']).'</int></value>
</member>
</struct></value>
</member>
</struct>
</value>
</param>
</params>';

    outputRPCResponse($response);
}

// Returns the complete board structure
function method_get_forum()
{
    global $mobdb, $mobsettings, $modSettings, $user_info, $scripturl, $ID_MEMBER;

    // Fetch all the boards
    $cats = array();
    $mobdb->query('
        SELECT b.ID_BOARD AS id_board, b.name, b.description, IFNULL(mem.memberName, m.posterName) AS posterName, b.ID_PARENT AS id_parent,
                c.ID_CAT AS id_cat, c.name AS cat_name'. ($user_info['is_guest'] ? ", 1 AS isRead, 0 AS new_from" : ", (IFNULL(lb.ID_MSG, 0) >= b.ID_MSG_UPDATED) AS isRead, IFNULL(ln.sent, -1) AS is_notify") . '
        FROM {db_prefix}categories AS c
            LEFT JOIN {db_prefix}boards AS b ON (b.ID_CAT = c.ID_CAT)
            LEFT JOIN {db_prefix}messages AS m ON (m.ID_MSG = b.ID_LAST_MSG)
            LEFT JOIN {db_prefix}members AS mem ON (mem.ID_MEMBER = m.ID_MEMBER)' . (!$user_info['is_guest'] ? "
            LEFT JOIN {db_prefix}log_boards AS lb ON (lb.ID_BOARD = b.ID_BOARD AND lb.ID_MEMBER = $ID_MEMBER)
            LEFT JOIN {db_prefix}log_notify AS ln ON (ln.ID_BOARD = b.ID_BOARD AND ln.ID_MEMBER = $ID_MEMBER)" : '') . '
        WHERE {query_see_board}
        ORDER BY c.catOrder, b.childLevel, b.boardOrder',
        array()
    );

    while ($row = $mobdb->fetch_assoc())
    {
        if (!isset($cats[$row['id_cat']]))
        {
            $cats[$row['id_cat']] = array(
                'id' => 'c' . $row['id_cat'],
                'parent' => -1,
                'name' => html_entity_decode($row['cat_name']),
                'description' => '',
                'sub_only' => 1,
                'unread_count' => 0,
                'children' => array(),
                'new' => false,
                'is_notify' => false,
                'can_notify' => false,
                'icon' => get_board_icon('c'.$row['id_cat']),
            );
        }

        // If this board has new posts in it (and isn't the recycle bin!) then the category is new.
        if (empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $row['id_board'])
            $cats[$row['id_cat']]['new'] |= empty($row['isRead']) && $row['posterName'] != '';

        $cats[$row['id_cat']]['children'][$row['id_board']] = array(
            'id' => $row['id_board'],
            'parent' => empty($row['id_parent']) ? 'c' . $row['id_cat'] : $row['id_parent'],
            'act_parent' => $row['id_parent'],
            'name' => html_entity_decode($row['name']),
            'description' => $row['description'],
            'redirect' => isset($row['redirect']) ? $row['redirect'] : '',
            'unread_count' => 0,
            'children' => array(),
            'new' => empty($row['isRead']) && $row['posterName'] != '',
            'is_notify' => isset($row['is_notify']) && $row['is_notify'] != -1,
            'can_notify' => allowedTo('mark_notify', $row['id_board']) && !$user_info['is_guest'],
            'icon' => get_board_icon($row['id_board']),
        );
    }
    $mobdb->free_result();

    // Load up the tree
    foreach ($cats as $id_cat => $cat_data)
        foreach ($cat_data['children'] as $id_board => $board_data)
            if (!empty($board_data['act_parent']))
                $cats[$id_cat]['children'][$board_data['act_parent']]['children'][$id_board] = &$cats[$id_cat]['children'][$id_board];

    // Only add the base item to this array
    foreach ($cats as $id_cat => $cat_data)
        foreach ($cat_data['children'] as $id_board => $board_data)
            if (!empty($board_data['act_parent']))
                unset($cats[$id_cat]['children'][$id_board]);

    // Output the board tree
    outputRPCBoardTree($cats);
}

function method_get_topic()
{
    global $mobdb, $mobsettings, $modSettings, $context, $scripturl, $user_info, $board;

    // Load the parameters

    // Our first parameter - forum_id(Or as we say, id_board)
    $id_board = $context['mob_request']['params'][0][0];
    if (empty($id_board))
        createErrorResponse(4);

    $board = $id_board;
    loadBoard();
    loadPermissions();

    // Do we have start num defined?
    if (isset($context['mob_request']['params'][1]))
        $start_num = (int) $context['mob_request']['params'][1][0];

    // Do we have last number defined?
    if (isset($context['mob_request']['params'][2]))
        $last_num = (int) $context['mob_request']['params'][2][0];

    $sticky = false;
    // Are we requesting sticky topics only?
    if (isset($context['mob_request']['params'][3]) && strtolower($context['mob_request']['params'][3][0]) == 'top')
        $sticky = true;

    // Can you access this board?
    $mobdb->query('
        SELECT b.ID_BOARD AS id_board, b.name AS board_name
        FROM {db_prefix}boards AS b
        WHERE {query_see_board}
            AND b.ID_BOARD = {string:board}',
        array(
            'board' => $id_board,
        )
    );
    if ($mobdb->num_rows() == 0)
        createErrorResponse(4);
    $board_info = $mobdb->fetch_assoc();
    $mobdb->free_result();

    $board_info['can_post_new'] = allowedTo('post_new');

    // Perform some start/last num checks
    if (isset($start_num) && isset($last_num))
        if ($start_num > $last_num)
            createErrorResponse(3);
        elseif ($last_num - $start_num > 50)
            $last_num = $start_num + 50;

    // Default number of topics per page
    $topics_per_page = 20;

    // Generate the limit clause
    $limit = '';
    if (!isset($start_num) && !isset($last_num))
        $limit = $topics_per_page;
    elseif (isset($start_num) && !isset($last_num))
        $limit = $start_num . ', ' . $topics_per_page;
    elseif (isset($start_num) && isset($last_num))
        $limit = $start_num . ', ' . (($last_num - $start_num) + 1);
    elseif (empty($start_num) && empty($last_num))
        $limit = 1;

    // Perform the query to fetch the topics
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, t.locked, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                IFNULL(mem.ID_MEMBER, 0) AS id_member, mem.realName, mem.memberName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                fm.body, lm.ID_MSG_MODIFIED AS id_msg_modified
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mem ON (t.ID_MEMBER_STARTED = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = {int:board} AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE t.ID_BOARD = {int:board}
            AND t.isSticky = ' . ($sticky ? 1 : 0) . '
        ORDER BY IFNULL(lm.posterTime, fm.posterTime) DESC
        LIMIT ' . $limit,
        array(
            'current_member' => $user_info['id'],
            'board' => $id_board,
        )
    );
    $topics = array();
    $tids = array();
    while ($row = $mobdb->fetch_assoc())
    {
        // Get a shortened version of this topic's first message
        //$shortened_message = shorten_subject($row['body'], 200);
        $shortened_message = $row['body'];
        // Replace all bug [img] tags to nowhere(Does that even make sense?)!
        //$shortened_message = preg_replace('/&#?[a-z0-9]{2,8};/i','', strip_tags(parse_bbc($shortened_message)));
        $shortened_message =    processShortContent($shortened_message);
        // Add stuff to the array
        $topics[$row['id_topic']] = array(
            'id' => $row['id_topic'],
            'title' => processSubject($row['topic_title']),
            'replies' => $row['replies'],
            'views' => $row['views'],
            'poster' => array(
                'id' => $row['id_member'],
                'name' => $row['realName'],
                'username' => $row['memberName'],
                'avatar' => get_avatar($row),
            ),
            'last_msg_time' => mobiquo_time($row['last_message_time']),
            'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
            'short_msg' => $shortened_message,
            'board' => $id_board,
            'is_marked_notify' => false,
            'is_locked' => !empty($row['locked']),
        );
        $tids[] = $row['id_topic'];
    }
    $mobdb->free_result();

    if (!empty($tids))
    {
        // Check for notifications on this topic OR board.
        $mobdb->query("
            SELECT sent, ID_TOPIC
            FROM {db_prefix}log_notify
            WHERE (ID_TOPIC IN ({array_int:topic_ids}) OR ID_BOARD = {int:board})
                AND ID_MEMBER = {int:member}",
            array(
                'topic_ids' => $tids,
                'board' => $id_board,
                'member' => $user_info['id']
            )
        );

        while ($row = $mobdb->fetch_assoc())
        {
            // Find if this topic is marked for notification...
            if (!empty($row['ID_TOPIC']))
                $topics[$row['ID_TOPIC']]['is_marked_notify'] = true;
        }
        $mobdb->free_result();
    }

    // Get unread sticky topics num
    $board_info['unread_sticky_count'] = 0;
    if (!$user_info['is_guest'])
    {
        $mobdb->query('
            SELECT IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1 AS new_from, lm.ID_MSG_MODIFIED AS id_msg_modified
            FROM {db_prefix}topics AS t
                LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
                LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
                LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = {int:board} AND lmr.ID_MEMBER = {int:current_member})
            WHERE t.ID_BOARD = {int:board}
                AND t.isSticky = 1',
            array(
                'current_member' => $user_info['id'],
                'board' => $id_board,
            )
        );

        while ($row = $mobdb->fetch_assoc())
        {
            if ($row['new_from'] <= $row['id_msg_modified'])
                $board_info['unread_sticky_count']++;
        }
        $mobdb->free_result();
    }

    // Get the total
    $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}topics AS t
        WHERE t.ID_BOARD = {int:board}
            AND t.isSticky = ' . ($sticky ? 1 : 0),
        array(
            'board' => $id_board,
        )
    );
    list($board_info['total_topic_num']) = $mobdb->fetch_row();
    $mobdb->free_result();

    // Return the output
    outputRPCTopics($topics, $board_info);
}

function method_login()
{
    global $context, $mobdb, $mobsettings, $modSettings, $scripturl, $user_info, $sourcedir, $txt, $user_info, $user_profile;

    loadLanguage('Login');

    // Call this file for authentication
    require_once($sourcedir . '/Subs-Auth.php');

    // We are performing a nobel function, let the user log in!
    $username = base64_decode($context['mob_request']['params'][0][0]);
    $password = base64_decode($context['mob_request']['params'][1][0]);

    if (empty($username))
        outputRPCResult(false, $txt[37]);

    if (empty($password))
        outputRPCResult(false, $txt[38]);

    // Load the data up! (This is a shameless copy from LogInOut.php)
    $mobdb->query('
        SELECT passwd, ID_MEMBER AS id_member, is_activated, ID_GROUP AS id_group, emailAddress AS email_address, additionalGroups AS additional_groups, memberName AS member_name,
            passwordSalt AS password_salt, ID_POST_GROUP
        FROM {db_prefix}members
        WHERE memberName = {string:user_name}
        LIMIT 1',
        array(
            'user_name' => $username,
        )
    );

    // Probably mistyped or their email, try it as an email address. (member_name first, though!)
    if ($mobdb->num_rows() == 0)
    {
        $mobdb->free_result();

        $mobdb->query('
            SELECT passwd, ID_MEMBER AS id_member, is_activated, ID_GROUP AS id_group, emailAddress AS email_address, additionalGroups AS additional_groups, memberName AS member_name,
                passwordSalt AS password_salt, ID_POST_GROUP
            FROM {db_prefix}members
            WHERE emailAddress = {string:user_name}
            LIMIT 1',
            array(
                'user_name' => $username,
            )
        );
        // Let them try again, it didn't match anything...
        if ($mobdb->num_rows() == 0)
            outputRPCResult(false);
    }

    $user = $mobdb->fetch_assoc();
    $mobdb->free_result();

    // Hash the password
    $sha_passwd = sha1(strtolower($user['member_name']) . $password);

    // Are we having an incorrect password?
    if ($user['passwd'] != $sha_passwd)
        outputRPCResult(false, $txt[39]);

    if ($user['is_activated'] == 3)
        fatal_lang_error('still_awaiting_approval');

    // Set the login cookie
    setLoginCookie(60 * $modSettings['cookieTime'], $user['id_member'], sha1($user['passwd'] . $user['password_salt']));

    loadMemberData($user['id_member'], false, 'profile');
    $user_info = $user_profile[$user['id_member']];

    $user_info['is_guest'] = false;
    $user_info['is_admin'] = $user['id_group'] == 1 || in_array(1, explode(',', $user['additional_groups']));
    $user_info['id'] = $user['id_member'];

    if (empty($user_info['additionalGroups']))
        $user_info['groups'] = array($user_info['ID_GROUP'], $user_info['ID_POST_GROUP']);
    else
        $user_info['groups'] = array_merge(array($user_info['ID_GROUP'], $user_info['ID_POST_GROUP']), explode(',', $user_info['additionalGroups']));
    
    $user_info['groups'] = array_unique(array_map('intval', $user_info['groups']));
    
    // Banned?
    is_not_banned(true);

    // Delete any old session
    $mobdb->query('
        DELETE FROM {db_prefix}log_online
        WHERE session = {string:session}',
        array(
            'session' => 'ip' . $user_info['ip'],
        )
    );

    // Update last logged in time
    if (!defined('OLD_SYSTEM'))
    updateMemberData($user_info['id'], array('lastLogin' => time(), 'memberIP' => '\'' . $user_info['ip'] . '\'', 'memberIP2' => '\'' . $_SERVER['BAN_CHECK_IP'] . '\''));

    loadPermissions();
    
    update_push();
    
    // We got this far? return a positive response....
    outputRPCLogin(true);
}

function method_sign_in()
{
    global $db_prefix, $context, $user_profile, $modSettings, $register, $sourcedir, $user_info, $boardurl, $txt;
    
    require_once($sourcedir . '/Register.php');
    require_once($sourcedir . '/Subs-Members.php');
    require_once($sourcedir . '/Subs-Auth.php');
    
    $token = $context['mob_request']['params'][0][0];
    $code = $context['mob_request']['params'][1][0];
    $email = isset($context['mob_request']['params'][2][0]) ? base64_decode($context['mob_request']['params'][2][0]) : '';
    $username = isset($context['mob_request']['params'][3][0]) ? base64_decode($context['mob_request']['params'][3][0]) : '';
    $password = isset($context['mob_request']['params'][4][0]) ? base64_decode($context['mob_request']['params'][4][0]) : '';

    // verify tapatalk token and code first
    $ttid = TapatalkSsoVerification($token, $code);
    
    if (empty($ttid)) get_error('Single Sign-On feature is not setup correctly with this community. Please contact your administrator if problem persists.');
    
    $tapatalk_id_email = $ttid->email;
    $result_status = true;
    $register = false;
    $result_text = '';
    
    if (!$ttid->result || empty($tapatalk_id_email))
        get_error($ttid->result_text ? $ttid->result_text : 'Invalid Tapatalk authentication');
    // sign in with email or register an account
    else if ($email)
    {
        $email = strtolower( trim( $email ) );

        if ($email != $tapatalk_id_email)
        {
            get_error('Unmatched email with Tapatalk ID', 3);
        }
        // email registered, login directly
        elseif ($login_id = emailExists($tapatalk_id_email))
        {
            
        }
        // email not registered, register an account
        else
        {
            if (empty($username) || empty($password))
            {
                get_error('Invalid Parameters', 2);
            }
            else if (isReservedName($username, 0, true, false))
            {
                get_error($txt[473], 1);
            }
            else
            {
                //$ttid_profile = (array)$ttid->profile;
                
                $_POST['user'] = $username;
                $_POST['email'] = $email;
                $_POST['passwrd1'] = $password;
                $_POST['passwrd2'] = $password;
                $_POST['regagree'] = 'on';
                $_POST['regSubmit'] = 'Register';
                $_POST['skip_coppa'] = 1;
                $_SESSION['old_url'] = $boardurl;
                $modSettings['disable_visual_verification'] = 1;
                $modSettings['recaptcha_enabled'] = 0;
                $modSettings['anti_spam_ver_enable'] = false;
                if ($modSettings['registration_method'] == 1)
                    $modSettings['registration_method'] = 0;
                
                $login_id = Register2();
                $register = true;
                $result_status = $modSettings['registration_method'] == 2 ? false : true;
                $result_text = $modSettings['registration_method'] == 2 ? $txt['approval_after_registration'] : '';
                
                if (empty($login_id))
                {
                    get_error('Register failed');
                }
            }
        }
    }
    // sign in with username
    elseif ($username)
    {
        $loaded_ids = loadMemberData( $username, true, 'minimal' );
        $login_id = $loaded_ids[0];
        $memberData = $user_profile[$login_id];

        if (empty($memberData) || $memberData['email'] != $tapatalk_id_email)
        {
            get_error('Unmatched email with Tapatalk ID', 3);
        }
    }
    // sign in with tapatalk id email as default
    else
    {
        $login_id = emailExists($tapatalk_id_email);
        if ( empty($login_id) ) get_error('Invalid Parameters', 2);
    }
    
    // do login
    if ($login_id)
    {
        $request = db_query("
            SELECT passwd, ID_MEMBER AS id_member, is_activated, ID_GROUP AS id_group, emailAddress AS email_address, additionalGroups AS additional_groups, memberName AS member_name,
                passwordSalt AS password_salt, ID_POST_GROUP
            FROM {$db_prefix}members
            WHERE ID_MEMBER = '$login_id'
            ", __FILE__, __LINE__);
        $user = mysql_fetch_assoc($request);
        
        if ($user['is_activated'] == 3 && !$register)
            fatal_lang_error('still_awaiting_approval');
        
        // Set the login cookie
        setLoginCookie(60 * $modSettings['cookieTime'], $login_id, sha1($user['passwd'] . $user['password_salt']));
    
        loadMemberData($user['id_member'], false, 'profile');
        $user_info = $user_profile[$user['id_member']];
    
        $user_info['is_guest'] = false;
        $user_info['is_admin'] = $user['id_group'] == 1 || in_array(1, explode(',', $user['additional_groups']));
        $user_info['id'] = $user['id_member'];
    
        if (empty($user_info['additionalGroups']))
            $user_info['groups'] = array($user_info['ID_GROUP'], $user_info['ID_POST_GROUP']);
        else
            $user_info['groups'] = array_merge(array($user_info['ID_GROUP'], $user_info['ID_POST_GROUP']), explode(',', $user_info['additionalGroups']));
        
        $user_info['groups'] = array_unique(array_map('intval', $user_info['groups']));
        
        // Banned?
        is_not_banned(true);
    
        // Don't stick the language or theme after this point.
        unset($_SESSION['language']);
        unset($_SESSION['ID_THEME']);
    
        // You've logged in, haven't you?
        updateMemberData($user_info['id'], array('lastLogin' => time(), 'memberIP' => '\'' . $user_info['ip'] . '\'', 'memberIP2' => '\'' . $_SERVER['BAN_CHECK_IP'] . '\''));
    
        // Get rid of the online entry for that old guest....
        db_query("
            DELETE FROM {$db_prefix}log_online
            WHERE session = 'ip$user_info[ip]'
            LIMIT 1", __FILE__, __LINE__);
        $_SESSION['log_time'] = 0;
    
        loadPermissions();
        
        update_push();
        
        // We got this far? return a positive response....
        outputRPCLogin($result_status, $result_text);
    }
    else
    {
        get_error('Sign In Failed');
    }
}

// Logs an user out
function method_logout_user()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info, $sourcedir, $ID_MEMBER, $user_settings;

    require_once($sourcedir . '/Subs-Auth.php');

    if (isset($_SESSION['pack_ftp']))
        $_SESSION['pack_ftp'] = null;

    // Just ensure they aren't a guest!
    if (!$user_info['is_guest'])
    {
        if (isset($modSettings['integrate_logout']) && function_exists($modSettings['integrate_logout']))
            call_user_func($modSettings['integrate_logout'], $user_settings['memberName']);

        // If you log out, you aren't online anymore :P.
        $mobdb->query("
            DELETE FROM {db_prefix}log_online
            WHERE ID_MEMBER = {int:current_member}
            LIMIT 1",
            array(
                'current_member' => $ID_MEMBER,
            )
        );
    }

    $_SESSION['log_time'] = 0;

    // Empty the cookie! (set it in the past, and for ID_MEMBER = 0)
    setLoginCookie(-3600, 0);

}

// Gets newest topics from the forum
function method_get_new_topic()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info, $sourcedir;

    // Figure out the parameters
    if (isset($context['mob_request']['params'][0]))
        $start_num = (int) $context['mob_request']['params'][0][0];
    if (isset($context['mob_request']['params'][1]))
        $last_num = (int) $context['mob_request']['params'][1][0];

    // Some start_num/last_num checks
    if (isset($start_num) && isset($last_num))
        if ($start_num > $last_num)
            createErrorResponse(3);
        elseif ($last_num - $start_num > 50)
            $last_num = $start_num + 50;

    // Generate the limit clause
    $topics_per_page = 20;
    if (!isset($start_num) && !isset($last_num))
        $limit = $topics_per_page;
    elseif (isset($start_num) && !isset($last_num))
        $limit = $start_num . ', ' . $topics_per_page;
    elseif (isset($start_num) && isset($last_num))
        $limit = $start_num . ', ' . (($last_num - $start_num) + 1);

    // Grab the topics
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, t.locked, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                IFNULL(mem.ID_MEMBER, 0) AS id_member, mem.realName, mem.memberName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'ln.ID_TOPIC AS is_notify, IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                IFNULL(lm.body, fm.body) AS body, lm.ID_MSG_MODIFIED AS id_msg_modified, b.name AS board_name, b.ID_BOARD AS id_board
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mem ON (lm.ID_MEMBER = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_notify AS ln ON ((ln.ID_TOPIC = t.ID_TOPIC OR ln.ID_BOARD = t.ID_BOARD) AND ln.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE {query_see_board}
        ORDER BY lm.posterTime DESC
        LIMIT ' . $limit,
        array(
            'current_member' => $user_info['id'],
        )
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc())
    {
        // Add stuff to the array
        $topics[$row['id_topic']] = array(
            'id' => $row['id_topic'],
            'title' => processSubject($row['topic_title']),
            'short_msg' => processShortContent($row['body']),
            'replies' => $row['replies'],
            'views' => $row['views'],
            'poster' => array(
                'id' => $row['id_member'],
                'username' => $row['memberName'],
                'post_name' => $row['realName'],
                'avatar' => get_avatar($row),
            ),
            'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
            'board' => $row['id_board'],
            'board_name' => $row['board_name'],
            'post_time' => mobiquo_time($row['last_message_time']),
            'is_marked_notify' => !empty($row['is_notify']),
            'is_locked' => !empty($row['locked']),
        );
    }
    $mobdb->free_result();

    // Return the output...
    outputRPCNewTopics($topics);
}

function method_get_thread_by_unread()
{
    method_get_thread('unread');
}

function method_get_thread_by_post()
{
    method_get_thread('post');
}

// Get the posts from a topic
function method_get_thread($type = '')
{
    global $mobdb, $mobsettings, $context, $scripturl, $modSettings, $user_info, $user_profile, $topic, $board;

    if (!isset($context['mob_request']['params'][0]))
        createErrorResponse(7);

    if ($type == 'post') {
        $msg = $id_msg = (int) $context['mob_request']['params'][0][0];

        $mobdb->query('
            SELECT t.ID_TOPIC as topic_id, t.ID_BOARD AS board_id, t.numReplies, t.locked, ms.subject, t.ID_MEMBER_STARTED, b.name AS board_name, t.ID_LAST_MSG, t.ID_FIRST_MSG
            FROM ({db_prefix}topics AS t, {db_prefix}messages AS ms)
                INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            WHERE {query_see_board}
                AND ms.ID_MSG = {int:msg}
                AND t.ID_TOPIC = ms.ID_TOPIC',
            array(
                'msg' => $id_msg,
            )
        );
    } else {
        $id_topic = (int) $context['mob_request']['params'][0][0];

        $mobdb->query('
            SELECT t.ID_TOPIC as topic_id, t.ID_BOARD AS board_id, t.numReplies, t.locked, ms.subject, t.ID_MEMBER_STARTED, b.name AS board_name, t.ID_LAST_MSG, t.ID_FIRST_MSG
            FROM ({db_prefix}topics AS t, {db_prefix}messages AS ms)
                INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            WHERE {query_see_board}
                AND ms.ID_MSG = t.ID_FIRST_MSG
                AND t.ID_TOPIC = {int:topic}',
            array(
                'topic' => $id_topic,
            )
        );
    }
    if ($mobdb->num_rows() == 0)
        createErrorResponse(1);
    $topicinfo = $mobdb->fetch_assoc();
    $context = array_merge($context, $topicinfo);
    $topic = $id_topic = $topicinfo['topic_id'];
    $board = $id_board = $topicinfo['board_id'];
    $mobdb->free_result();

    loadBoard();
    loadPermissions();

    if ($type == 'unread')
    {
        $posts_per_page = (int) $context['mob_request']['params'][1][0];
        $posts_per_page || $posts_per_page = 20;
        $GLOBALS['return_html'] = isset($context['mob_request']['params'][2][0]) ? $context['mob_request']['params'][2][0] : false;

        if ($user_info['is_guest']) {
            $context['start_from'] = 0;
        } else {
            $mobdb->query('
                SELECT IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1 AS new_from
                FROM {db_prefix}topics AS t
                    LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:member})
                    LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = {int:board} AND lmr.ID_MEMBER = {int:member})
                WHERE t.ID_TOPIC = {int:topic}
                LIMIT 1',
                array(
                    'topic' => $id_topic,
                    'board' => $id_board,
                    'member' => $user_info['id'],
                )
            );
            list($virtual_msg) = $mobdb->fetch_row();
            $mobdb->free_result();

            if ($virtual_msg >= $topicinfo['ID_LAST_MSG'])
                $context['start_from'] = $topicinfo['numReplies'];
            elseif ($virtual_msg <= $topicinfo['ID_FIRST_MSG'])
                $context['start_from'] = 0;
            else
            {
                // Find the start value for that message......
                $mobdb->query('
                    SELECT COUNT(*)
                    FROM {db_prefix}messages
                    WHERE ID_MSG < {int:msg}
                        AND ID_TOPIC = {int:topic}',
                    array(
                        'topic' => $id_topic,
                        'msg' => $virtual_msg,
                    )
                );
                list ($context['start_from']) = $mobdb->fetch_row();
                $mobdb->free_result();
            }
        }

        if (!empty($options['view_newest_first'])) {
            $context['start_from'] = $topicinfo['numReplies'] - $context['start_from'] + 1;
        }

        if ($context['start_from'] >= $topicinfo['numReplies'] + 1)
            $context['new_position'] = $topicinfo['numReplies'] + 1;
        else
            $context['new_position'] = $context['start_from'] + 1;

        $start = $context['start_from'] - ($context['start_from'] % $posts_per_page);
        $limit = $start . ', ' . $posts_per_page;
    } elseif ($type == 'post') {
        $posts_per_page = (int) $context['mob_request']['params'][1][0];
        $posts_per_page || $posts_per_page = 20;
        $GLOBALS['return_html'] = isset($context['mob_request']['params'][2][0]) ? $context['mob_request']['params'][2][0] : false;

        if ($msg >= $topicinfo['ID_LAST_MSG'])
            $context['start_from'] = $topicinfo['numReplies'];
        elseif ($msg <= $topicinfo['ID_FIRST_MSG'])
            $context['start_from'] = 0;
        else
        {
            // Find the start value for that message......
            $mobdb->query('
                SELECT COUNT(*)
                FROM {db_prefix}messages
                WHERE ID_MSG < {int:msg}
                    AND ID_TOPIC = {int:topic}',
                array(
                    'topic' => $id_topic,
                    'msg' => $msg,
                )
            );
            list ($context['start_from']) = $mobdb->fetch_row();
            $mobdb->free_result();
        }

        if (!empty($options['view_newest_first'])) {
            $context['start_from'] = $topicinfo['numReplies'] - $context['start_from'] + 1;
        }

        if ($context['start_from'] >= $topicinfo['numReplies'] + 1)
            $context['new_position'] = $topicinfo['numReplies'] + 1;
        else
            $context['new_position'] = $context['start_from'] + 1;

        $start = $context['start_from'] - ($context['start_from'] % $posts_per_page);
        $limit = $start . ', ' . $posts_per_page;
    } else {
        $GLOBALS['return_html'] = isset($context['mob_request']['params'][3][0]) ? $context['mob_request']['params'][3][0] : false;

        if (isset($context['mob_request']['params'][1]))
            $start_num = (int) $context['mob_request']['params'][1][0];
        if (isset($context['mob_request']['params'][2]))
            $last_num = (int) $context['mob_request']['params'][2][0];

        $posts_per_page = 20;
        if (!isset($start_num) && !isset($last_num))
            $limit = $posts_per_page;
        elseif (isset($start_num) && !isset($last_num))
            $limit = $start_num . ', ' . $posts_per_page;
        elseif (isset($start_num) && isset($last_num))
            $limit = $start_num . ', ' . (($last_num - $start_num) + 1);

        $context['new_position'] = $start_num ? $start_num + 1 : 1;
    }

    // Default this topic to not marked for notifications... of course...
    $context['is_marked_notify'] = false;

    // Did this user start the topic or not?
    $context['user']['started'] = $user_info['id'] == $topicinfo['ID_MEMBER_STARTED'] && !$user_info['is_guest'];

    $context['can_mark_notify'] = allowedTo('mark_any_notify') && !$user_info['is_guest'];
    $context['can_reply'] = allowedTo('post_reply_any') || ($context['user']['started'] && allowedTo($perm . '_own'));
    $context['can_reply'] &= empty($topicinfo['locked']) || allowedTo('moderate_board');

    // Up the views!
    if (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $id_topic)
        $mobdb->query('
            UPDATE {db_prefix}topics
            SET numViews = numViews + 1
            WHERE ID_TOPIC = {int:topic}',
            array(
                'topic' => $id_topic,
            )
        );

    // If this user is not a guest, mark this topic as read
    if (!$user_info['is_guest'])
    {
        $mobdb->query('
            REPLACE INTO {db_prefix}log_topics
                (id_member, id_topic, id_msg)
            VALUES
                ({int:member}, {int:topic}, {int:msg})',
            array(
                'member' => $user_info['id'],
                'topic' => $id_topic,
                'msg' => $modSettings['maxMsgID'],
            )
        );

        // Check for notifications on this topic OR board.
        $mobdb->query("
            SELECT sent, ID_TOPIC
            FROM {db_prefix}log_notify
            WHERE (ID_TOPIC = {int:topic} OR ID_BOARD = {int:board})
                AND ID_MEMBER = {int:member}
            LIMIT 2",
            array(
                'topic' => $id_topic,
                'board' => $id_board,
                'member' => $user_info['id']
            )
        );

        while ($row = $mobdb->fetch_assoc())
        {
            // Find if this topic is marked for notification...
            if (!empty($row['ID_TOPIC']))
                $context['is_marked_notify'] = true;
        }
    }

    // Set the last read topic
    $_SESSION['last_read_topic'] = $id_topic;

    // Get each post and poster in this topic.
    $mobdb->query("
        SELECT ID_MSG, ID_MEMBER
        FROM {db_prefix}messages
        WHERE ID_TOPIC = {int:topic}
        LIMIT $limit",
        array(
            'topic' => $id_topic,
        )
    );

    $messages = array();
    $posters = array();
    while ($row = $mobdb->fetch_assoc())
    {
        if (!empty($row['ID_MEMBER']))
            $posters[] = $row['ID_MEMBER'];
        $messages[] = $row['ID_MSG'];
    }
    $posters = array_unique($posters);
    if (!empty($posters))
        loadMemberData($posters);

    // Get the messages
    $mobdb->query('
        SELECT m.ID_MSG AS id_msg, m.body, m.subject, m.smileysEnabled, mem.realName, mem.memberName, mem.ID_MEMBER AS id_member, mem.avatar,
            IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
            m.posterTime AS poster_time, IFNULL(thumb.id_attach, 0) AS id_thumb,
            t.locked, t.ID_MEMBER_STARTED as id_member_started
        FROM {db_prefix}messages AS m
            LEFT JOIN {db_prefix}members AS mem ON (mem.ID_MEMBER = m.ID_MEMBER)
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
            LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)
            LEFT JOIN {db_prefix}topics AS t ON (t.ID_TOPIC = m.ID_TOPIC)
        WHERE m.ID_TOPIC = {int:topic}
        ORDER BY m.posterTime ASC
        LIMIT ' . $limit,
        array(
            'topic' => $id_topic,
        )
    );
    $posts = array();
    $matches = array();
    $board_permission = allowedTo('modify_own', $id_board);

    while ($row = $mobdb->fetch_assoc())
    {
        $is_started = ($user_info['id'] == $row['id_member_started'] && !$user_info['is_guest']);
        $can_edit = (!$row['locked'] || allowedTo('moderate_board', $id_board)) && (allowedTo('modify_any', $id_board) || (allowedTo('modify_replies', $id_board) && $is_started) || (allowedTo('modify_own', $id_board) && $row['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())));

        $posts[$row['id_msg']] = array(
            'id' => $row['id_msg'],
            'subject' => processSubject(censorText($row['subject'])),
            'body' => processBody(censorText($row['body'])),
            'poster' => array(
                'id' => $row['id_member'],
                'username' => $row['memberName'],
                'name' => $row['realName'],
                'is_online' => (!empty($user_profile[$row['id_member']]['showOnline']) || allowedTo('moderate_forum')) && $user_profile[$row['id_member']]['isOnline'] > 0,
                'avatar' => get_avatar($row),
            ),
            'attachment_authority' => allowedTo('view_attachments', $id_board) ? 0 : 4,
            'time' => mobiquo_time($row['poster_time']),
            'attachments' => array(),
            'topic' => $id_topic,
            'allow_smilies' => $row['smileysEnabled'],
            'can_edit' => $can_edit,
            'can_delete' => allowedTo('delete_any', $id_board) || (allowedTo('delete_replies', $id_board) && $is_started) || (allowedTo('delete_own', $id_board) && $row['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $row['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
            //'can_edit' => !empty($user_info['id']) && $row['id_member'] == $user_info['id'] && $board_permission,
        );
    }
    $mobdb->free_result();

    // Figure out the attachments!
    if (allowedTo('view_attachments', $id_board) && !empty($posts))
    {
        $mobdb->query('
            SELECT a.ID_ATTACH AS id_attach, a.ID_MSG AS id_msg, a.width, a.height, a.filename, a.attachmentType AS attachment_type,
                thumb.ID_ATTACH AS id_thumb
            FROM {db_prefix}attachments AS a
                LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.ID_ATTACH = a.ID_THUMB)
            WHERE a.ID_MSG IN ({array_int:messages})
                AND a.attachmentType = 0',
            array(
                'messages' => array_keys($posts),
            )
        );
        while ($row = $mobdb->fetch_assoc())
        {
            // How is this even possible?
            if (empty($posts[$row['id_msg']]))
                continue;

            $posts[$row['id_msg']]['attachments'][$row['id_attach']] = array(
                'id' => $row['id_attach'],
                'is_image' => !empty($row['width']) && !empty($row['height']),
                'href' => $scripturl . '?action=dlattach;topic=' . $id_topic . '.0;attach=' . $row['id_attach'],
                'thumbnail' => !empty($row['id_thumb']) ? $scripturl . '?action=dlattach;topic=' . $id_topic . '.0;attach=' . $row['id_thumb'] : '',
            );
        }
        $mobdb->free_result();
    }

    $context['posts'] = $posts;

    outputRPCPosts();
}

// Gets the user's information
function method_get_user_info()
{
    global $context, $mobdb, $mobsettings, $modSettings, $scripturl, $func, $smcFunc, $memberContext, $txt;

    // Invalid username? Non-existant username?
    if (!isset($context['mob_request']['params'][0]))
        createErrorResponse(7);
    $username = base64_decode($context['mob_request']['params'][0][0]);

    ######## Added by Sean##############
        $username = htmltrim__recursive($username);
        $username = stripslashes__recursive($username);
        $username = htmlspecialchars__recursive($username);
        $username = addslashes__recursive($username);
    ####################################

    list($member_id) = loadMemberData($username, true);
    if (!loadMemberContext($member_id) || !isset($memberContext[$member_id]))
        fatal_error($txt[453] . ' - ' . $member_id, false);

    $user_data = $memberContext[$member_id];

    loadLanguage('Profile');
    if (!empty($modSettings['titlesEnable']) && $user_data['title'] != '')
        $user_data['custom_fields_list'][$txt['title1']] = $user_data['title'];

    $user_data['custom_fields_list'][$txt[87]] = (!empty($user_data['group']) ? $user_data['group'] : $user_data['post_group']);

    if (allowedTo('moderate_forum') && $user_data['ip'])
    {
        $user_data['custom_fields_list'][$txt[512]] = $user_data['ip'];

        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $user_data['ip']) == 1 && empty($modSettings['disableHostnameLookup']))
            $user_data['custom_fields_list'][$txt['hostname']] = host_from_ip($user_data['ip']);
    }

    // If karma enabled show the members karma.
    if ($modSettings['karmaMode'] == '1')
        $user_data['custom_fields_list'][$modSettings['karmaLabel']] = ($user_data['karma']['good'] - $user_data['karma']['bad']);
    elseif ($modSettings['karmaMode'] == '2')
        $user_data['custom_fields_list'][$modSettings['karmaLabel']] = '+'.$user_data['karma']['good'].'/-'.$user_data['karma']['bad'];

    if ($user_data['icq']['name'])
        $user_data['custom_fields_list'][$txt[513]] = $user_data['icq']['name'];

    if ($user_data['aim']['name'])
        $user_data['custom_fields_list'][$txt[603]] = $user_data['aim']['name'];

    if ($user_data['msn']['name'])
        $user_data['custom_fields_list'][$txt['MSN']] = $user_data['msn']['name'];

    if ($user_data['yim']['name'])
        $user_data['custom_fields_list'][$txt[604]] = $user_data['yim']['name'];

    $user_data['custom_fields_list'][$txt[69]] = ($user_data['email_public'] || !$user_data['hide_email']) ? $user_data['email'] : $txt[722];
    if ($user_data['website']['title'] != '' || $user_data['website']['url'] != '')
        $user_data['custom_fields_list'][$txt[96]] = $user_data['website']['title'] . ($user_data['website']['url'] ? '('.$user_data['website']['url'].')' : '');

    if ($user_data['gender']['name'])
        $user_data['custom_fields_list'][$txt[231]] = $user_data['gender']['name'];

    if (!empty($user_data['birth_date']))
    {
        list ($birth_year, $birth_month, $birth_day) = sscanf($user_data['birth_date'], '%d-%d-%d');
        $datearray = getdate(forum_time());
        if ($birth_year > 4)
        {
            $user_data['custom_fields_list'][$txt[420]] = ($datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1));
            $user_data['custom_fields_list'][$txt[420]] .= ($datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day) ? ' ('. substr($txt['calendar3'], 0, -1) .')' : '';
        }
    }

    if ($user_data['location'])
        $user_data['custom_fields_list'][$txt[227]] = $user_data['location'];

    if ($user_data['local_time'])
        $user_data['custom_fields_list'][$txt['local_time']] = $user_data['local_time'];

    if (!empty($modSettings['userLanguage']) && $user_data['language'])
        $user_data['custom_fields_list'][$txt['smf225']] = $user_data['language'];

    if ($user_data['signature'])
        $user_data['custom_fields_list'][$txt[85]] = $user_data['signature'];

    // Return the output
    outputRPCUserInfo($user_data);
}

// Gets inbox unread statistics
function method_get_inbox_stat()
{
    global $user_info;

    if ($user_info['is_guest'])
        createErrorResponse(28);
    
    if (!$user_info['unread_messages'])
        $user_info['unread_messages'] = 0;
    
    // Best. Function. Ever.
    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>inbox_unread_count</name>
<value><int>' . $user_info['unread_messages'] . '</int></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

// Returns inbox and sent item boxes(OR as we say, folders) statistics
function method_get_box_info()
{
    global $user_info, $mobdb, $mobsettings, $modSettings, $txt;

    if ($user_info['is_guest'] || !allowedTo('pm_read'))
        createErrorResponse(28);

    loadLanguage('PersonalMessage');

    // Figure out the box count
    $box_count = allowedTo('pm_send') ? 2 : 1;

    // Get the message count from inbox
    $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}pm_recipients AS pmr
        WHERE pmr.ID_MEMBER = {int:current_member}
            AND pmr.deleted = 0',
        array(
            'current_member' => $user_info['id'],
        )
    );
    list($inbox_count) = $mobdb->fetch_row();
    $mobdb->free_result();

    // Get the sent statistics
    if (allowedTo('pm_send'))
    {
        $mobdb->query('
            SELECT COUNT(*)
            FROM {db_prefix}personal_messages AS pm
            WHERE pm.ID_MEMBER_FROM = {int:current_member}
                AND pm.deletedBySender = 0',
            array(
                'current_member' => $user_info['id'],
            )
        );
        list($sent_count) = $mobdb->fetch_row();
        $mobdb->free_result();
    }

    // Get the boxes up
    $boxes = array(
        'inbox' => array(
            'id' => 'inbox',
            'name' => $txt[316],
            'msg_count' => $inbox_count,
            'unread_count' => $user_info['unread_messages'],
            'box_type' => 'INBOX',
        ),
    );

    if (isset($sent_count))
        $boxes['outbox'] = array(
            'id' => 'outbox',
            'name' => $txt[320],
            'msg_count' => $sent_count,
            'unread_count' => 0,
            'box_type' => 'SENT',
        );

    // Send the response
    outputRPCBoxInfo($boxes, $box_count);
}

// Gets the specific box
function method_get_box()
{
    global $mobdb, $mobsettings, $modSettings, $context, $scripturl, $user_info, $txt, $memberContext;

    // Load the parameters
    if (!isset($context['mob_request']['params'][0]))
        createErrorResponse(7);
    elseif ($user_info['is_guest'] || !allowedTo('pm_read'))
        outputRPCResult(false, $txt['cannot_pm_read']);
    $id_box = $context['mob_request']['params'][0][0];

    if (!in_array($id_box, array('inbox', 'outbox')) || ($id_box == 'outbox' && !allowedTo('pm_send')))
        outputRPCResult(false, $txt['cannot_pm_send']);

    // Star/end
    if (isset($context['mob_request']['params'][1]))
        $start_num = (int) $context['mob_request']['params'][1][0];
    if (isset($context['mob_request']['params'][2]))
        $last_num = (int) $context['mob_request']['params'][2][0];
    
    list($start, $limit) = process_page($start_num, $last_num);
    
    $limit = "$start, $limit";

    // Load thix box's info
    if ($id_box == 'inbox')
    {
        $mobdb->query('
            SELECT COUNT(*)
            FROM {db_prefix}pm_recipients AS pmr
            WHERE pmr.ID_MEMBER = {int:current_member}
                AND pmr.deleted = 0',
            array(
                'current_member' => $user_info['id'],
            )
        );
        list($count) = $mobdb->fetch_row();
        $mobdb->free_result();
    }
    else
    {
        $mobdb->query('
            SELECT COUNT(*)
            FROM {db_prefix}personal_messages AS pm
            WHERE pm.ID_MEMBER_FROM = {int:current_member}
                AND pm.deletedBySender = 0',
            array(
                'current_member' => $user_info['id'],
            )
        );
        list($count) = $mobdb->fetch_row();
        $mobdb->free_result();
    }

    $unread_count = $id_box == 'outbox' ? 0 : $user_info['unread_messages'];

    // Get the ID of messages to load
    $mobdb->query('
        SELECT pm.ID_PM AS id_pm, pm.subject, pm.ID_MEMBER_FROM AS id_member_from, pm.body, pm.msgtime, mem_from.realName AS from_name, mem_from.memberName AS from_username,
        mem_from.avatar as avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename as filename, a.attachmentType AS attachment_type
        FROM {db_prefix}personal_messages AS pm    ' . ($id_box == 'outbox' ? '' : '
            INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.ID_PM = pm.ID_PM
                AND pmr.ID_MEMBER = {int:current_member}
                AND pmr.deleted = 0)') . '
            LEFT JOIN {db_prefix}members AS mem_from ON (mem_from.ID_MEMBER = pm.ID_MEMBER_FROM)
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem_from.ID_MEMBER)
        WHERE ' . ($id_box == 'outbox' ? 'pm.ID_MEMBER_FROM = {int:current_member}
            AND pm.deletedBySender = 0' : '1=1') . '
        ORDER BY pm.ID_PM DESC
        LIMIT ' . $limit,
        array(
            'current_member' => $user_info['id'],
        )
    );
    $pms = array();
    while ($row = $mobdb->fetch_assoc())
        $pms[$row['id_pm']] = array(
            'id' => $row['id_pm'],
            'recipients' => array(),
            'subject' => processSubject($row['subject']),
            'id_member_from' => $row['id_member_from'],
            'from_name' => $row['from_name'],
            'from_username' => $row['from_username'],
            'time' => mobiquo_time($row['msgtime']),
            'body' => processShortContent($row['body']),
            'is_replied' => null,
            'is_unread' => null,
        );
    $mobdb->free_result();

    // Load the PM recipients
    if (!empty($pms))
    {
        $mobdb->query('
            SELECT pmr.ID_PM AS id_pm, mem_to.ID_MEMBER AS id_member_to, mem_to.realName AS to_name, mem_to.memberName AS to_username, pmr.bcc, pmr.labels, pmr.is_read
            FROM {db_prefix}pm_recipients AS pmr
                LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.ID_MEMBER = pmr.ID_MEMBER)
            WHERE pmr.ID_PM IN ({array_int:pm_list})',
            array(
                'pm_list' => array_keys($pms),
            )
        );
        while ($row = $mobdb->fetch_assoc())
        {
            $pms[$row['id_pm']]['recipients'][] = array('name' => $row['to_name'], 'username' => $row['to_username']);

            if ($id_box == 'inbox')
                $display_id = $pms[$row['id_pm']]['id_member_from'];
            else
                $display_id = $row['id_member_to'];

            if (!isset($pms[$row['id_pm']]['icon_url'])) {
                loadMemberData($display_id);
                loadMemberContext($display_id);
                $pms[$row['id_pm']]['is_online'] = $memberContext[$display_id]['online']['is_online'];
                $pms[$row['id_pm']]['icon_url'] = $memberContext[$display_id]['avatar']['href'];
            }

            if ($row['id_member_to'] == $user_info['id'] && $id_box != 'outbox')
            {
                $pms[$row['id_pm']]['is_replied'] = $row['is_read'] & 2;
                $pms[$row['id_pm']]['is_unread'] = $row['is_read'] == 0;
            }
        }
        $mobdb->free_result();
    }

    // Outut the PM Box information
    outputRPCBox($pms, $count, $unread_count);
}

// Loads a single PM
function method_get_message()
{
    global $context, $mobsettings, $mobdb, $modSettings, $scripturl, $user_info, $sourcedir, $txt, $memberContext;

    if ($user_info['is_guest'] || !allowedTo('pm_read'))
        createErrorResponse(21);

    require_once($sourcedir . '/PersonalMessage.php');
    loadLanguage('PersonalMessage');

    // Get the message ID
    if (!isset($context['mob_request']['params'][0]))
        createErrorResponse(27);
    $id_pm = intval($context['mob_request']['params'][0][0]);

    $id_box = 'inbox';
    if (isset($context['mob_request']['params'][1]))
        $id_box = $context['mob_request']['params'][1][0];
    $context['folder'] = ($id_box == 'inbox') ? 'inbox' : 'outbox';
    $context['labels'][-1] = array('id' => -1, 'name' => $txt['pm_msg_label_inbox'], 'messages' => 0, 'unread_messages' => 0);

    $GLOBALS['return_html'] = isset($context['mob_request']['params'][2][0]) ? $context['mob_request']['params'][2][0] : false;

    // Load this message...
    $mobdb->query('
        SELECT pm.ID_PM AS id_pm, pm.subject, pm.body, pm.ID_MEMBER_FROM AS id_member_from, mem_from.realName AS from_name, mem_from.memberName AS from_username, pm.msgtime
        FROM {db_prefix}personal_messages AS pm
        LEFT JOIN {db_prefix}members AS mem_from ON (mem_from.ID_MEMBER = pm.ID_MEMBER_FROM)
        WHERE pm.ID_PM = {int:pm}',
        array(
            'pm' => $id_pm,
        )
    );
    if ($mobdb->num_rows() == 0)
        createErrorResponse(27);
    $pm = $mobdb->fetch_assoc();
    $mobdb->free_result();

    // Load the recipients
    $mobdb->query('
        SELEcT pmr.ID_PM AS id_pm, mem_to.ID_MEMBER AS id_member_to, mem_to.realName AS to_name, mem_to.memberName AS to_username, pmr.bcc, pmr.labels, pmr.is_read
        FROM {db_prefix}pm_recipients AS pmr
            LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.ID_MEMBER = pmr.ID_MEMBER)
        WHERE pmr.ID_PM = {int:pm}
        ORDER BY pmr.bcc DESC',
        array(
            'pm' => $id_pm,
        )
    );
    $pm['recipients'] = array();
    $bcc = array();
    while ($row = $mobdb->fetch_assoc())
    {
        if ($id_box == 'inbox' && !empty($row['bcc']) && $row['id_member_to'] == $user_info['id']) {
            $pm['recipients'][$row['id_member_to']] = array('name' => $row['to_name'], 'username' => $row['to_username']);
            break;
        }

        $pm['recipients'][$row['id_member_to']] = array('name' => $row['to_name'], 'username' => $row['to_username']);

        if ($row['id_member_to'] == $user_info['id'])
            $pm['is_read'] = $row['is_read'];
    }
    $mobdb->free_result();

    // Check if this user applies....
    if ($pm['id_member_from'] != $user_info['id'] && !in_array($user_info['id'], array_keys($pm['recipients'])))
        createErrorResponse(27);

    // Mark this as read, if it is not already
    markMessages(array($id_pm));

    if ($id_box == 'inbox')
        $display_id = $pm['id_member_from'];
    else {
        $display_ids = array_keys($pm['recipients']);
        $display_id = $display_ids[0];
    }

    loadMemberData($display_id);
    loadMemberContext($display_id);

    // Process some extra stuff
    $pm['subject'] = processSubject($pm['subject']);
    $pm['body'] = processBody($pm['body']);
    $pm['time'] = mobiquo_time($pm['msgtime']);
    $pm['is_online'] = $memberContext[$display_id]['online']['is_online'];
    $pm['icon_url'] = $memberContext[$display_id]['avatar']['href'];

    // Send the response
    outputRPCPM($pm);
}

// Deletes a PM
function method_delete_message()
{
    global $mobdb, $mobsettings, $modSettings, $context, $sourcedir, $user_info, $txt;

    if ($user_info['is_guest'])
        outputRPCResult(false, $txt[1]);

    if (!allowedTo('pm_read'))
        outputRPCResult(false, $txt['cannot_pm_read']);

    // Invalid message ID?
    if (!isset($context['mob_request']['params'][0]))
        outputRPCResult(false, $txt['smf272']);
    $id_pm = $context['mob_request']['params'][0][0];

    // Delete the PM
    require_once($sourcedir . '/PersonalMessage.php');
    deleteMessages(array((int) $id_pm));

    outputRPCResult(true);
}

// Subscribes to that specific topic...
function method_subscribe_topic()
{
    global $mobdb, $context, $user_info, $txt;

    // Permissions are an important part of anything ;).
    if ($user_info['is_guest'])
        outputRPCResult(false, $txt[1]);

    if (!isset($context['mob_request']['params'][0]))
        outputRPCResult(false, $txt[472]);

    $id_topic = (int) $context['mob_request']['params'][0][0];

    // Can you see this topic?
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, b.ID_BOARD AS id_board
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE {query_see_board}
            AND t.ID_TOPIC = {int:topic}',
        array(
            'topic' => $id_topic,
        )
    );
    if ($mobdb->num_rows() == 0)
        outputRPCResult(false);
    list($id_topic, $id_board) = $mobdb->fetch_row();
    $mobdb->free_result();

    if (!allowedTo('mark_any_notify', $id_board))
        outputRPCResult(false);

    // Mark this for notifications!
    $mobdb->insert('{db_prefix}log_notify',
        array('ID_MEMBER', 'ID_TOPIC'),
        array($user_info['id'], $id_topic),
        true
    );

    outputRPCResult(true);
}

// Unsubscribe to that specific topic
function method_unsubscribe_topic()
{
    global $mobdb, $context, $user_info, $txt;

    // Permissions are an important part of anything ;).
    if ($user_info['is_guest'])
        outputRPCResult(false, $txt[1]);

    if (!isset($context['mob_request']['params'][0]))
        outputRPCResult(false, $txt[472]);

    $id_topic = (int) $context['mob_request']['params'][0][0];

    // Can you see this topic?
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, b.ID_BOARD AS id_board
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE {query_see_board}
            AND t.ID_TOPIC = {int:topic}',
        array(
            'topic' => $id_topic,
        )
    );
    if ($mobdb->num_rows() == 0)
        outputRPCResult(false);
    list($id_topic, $id_board) = $mobdb->fetch_row();
    $mobdb->free_result();

    if(!allowedTo('mark_any_notify', $id_board))
        outputRPCResult(false, $txt['cannot_mark_any_notify']);

    // Get rid of it...
    $mobdb->query('
        DELETE FROM {db_prefix}log_notify
        WHERE ID_MEMBER = {int:member}
            AND ID_TOPIC = {int:topic}',
        array(
            'member' => $user_info['id'],
            'topic' => $id_topic,
        )
    );

    outputRPCResult(true);
}

function method_get_quote_post()
{
    global $mobdb, $mobsettings, $modSettings, $context, $scripturl, $sourcedir, $user_info, $board, $func, $smcFunc, $language, $txt;

    //SMF 2 or 1.1??
    if (isset($smcFunc)) {
        $FUNC = $smcFunc;
    } else {
        $FUNC = $func;
    }

    // We need these for creating topics
    require_once($sourcedir . '/Subs-Post.php');
    require_once($sourcedir . '/Post.php');

    // Guest? No entry
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Figure out the parameters
    $id_message = (int) $context['mob_request']['params'][0][0];

    // Find out the topic
    $mobdb->query('
        SELECT m.ID_TOPIC, m.ID_BOARD
        FROM {db_prefix}messages AS m
        WHERE m.ID_MSG = {int:value}',
        array(
            'value' => $id_message,
        )
    );
    if ($mobdb->num_rows() == 0)
        createErrorResponse(4);// not such message!!!
    list($topic, $id_board) = $mobdb->fetch_row();
    $mobdb->free_result();

    if (isset($smcFunc)) {
        $mobdb->query('
            SELECT
                t.locked, IFNULL(ln.id_topic, 0) AS notify, t.is_sticky, t.id_poll, t.num_replies, mf.id_member,
                t.id_first_msg, mf.subject,
                CASE WHEN ml.poster_time > ml.modified_time THEN ml.poster_time ELSE ml.modified_time END AS last_post_time
            FROM {db_prefix}topics AS t
                LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_topic = t.id_topic AND ln.id_member = {int:current_member})
                LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
                LEFT JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
            WHERE t.id_topic = {int:current_topic}
            LIMIT 1',
            array(
                'current_member' => $user_info['id'],
                'current_topic' => $topic,
            )
        );
    } else {
        $mobdb->query('
            SELECT
                t.locked, IFNULL(ln.ID_TOPIC, 0) AS notify, t.isSticky, t.ID_POLL, t.numReplies, mf.ID_MEMBER,
                t.ID_FIRST_MSG, mf.subject, GREATEST(ml.posterTime, ml.modifiedTime) AS lastPostTime
            FROM {db_prefix}topics AS t
                LEFT JOIN {db_prefix}log_notify AS ln ON (ln.ID_TOPIC = t.ID_TOPIC AND ln.ID_MEMBER = {int:current_member})
                LEFT JOIN {db_prefix}messages AS mf ON (mf.ID_MSG = t.ID_FIRST_MSG)
                LEFT JOIN {db_prefix}messages AS ml ON (ml.ID_MSG = t.ID_LAST_MSG)
            WHERE t.ID_TOPIC = {int:current_topic}
            LIMIT 1',
            array(
                'current_member' => $user_info['id'],
                'current_topic' => $topic,
            )
        );
    }
    list ($locked, $context['notify'], $sticky, $pollID, $context['num_replies'], $ID_MEMBER_POSTER, $id_first_msg, $first_subject, $lastPostTime) = $mobdb->fetch_row();
    $mobdb->free_result();


    if ($user_info['is_guest'] && !allowedTo('post_reply_any', $id_board) && (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_replies_any', $id_board)))
        createErrorResponse(21);

    // Security Issues!!!
    // This is important!
    if ($ID_MEMBER_POSTER == $user_info['id'])
    {
        if (allowedTo('post_reply_own', $id_board))
            $can_post = 1;
        elseif ($modSettings['postmod_active'] && !allowedTo('post_reply_own', $id_board) && allowedTo('post_unapproved_replies_own', $id_board))
            $can_post = 2;
        else
            createErrorResponse(25);
    }
    else
    {
        if (allowedTo('post_reply_any', $id_board))
            $can_post = 1;
        elseif ($modSettings['postmod_active'] && !allowedTo('post_reply_any', $id_board) && allowedTo('post_unapproved_replies_any', $id_board))
            $can_post = 2;
        else
            createErrorResponse(2);
    }

    // topic locked???
    if ($locked && !allowedTo('moderate_board', $id_board))
        createErrorResponse(25);


    // Get a response prefix (like 'Re:') in the default forum language.
    if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
    {
        if ($language === $user_info['language'])
            $context['response_prefix'] = $txt['response_prefix'];
        else
        {
            loadLanguage('index', $language, false);
            $context['response_prefix'] = $txt['response_prefix'];
            loadLanguage('index');
        }
        cache_put_data('response_prefix', $context['response_prefix'], 600);
    }



    // Make sure they _can_ quote this post, and if so get it.
    if (isset($smcFunc)) { //SMF 2
        $mobdb->query('
            SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
            FROM {db_prefix}messages AS m
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
                LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
            WHERE m.id_msg = {int:id_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
                AND m.approved = {int:is_approved}') . '
            LIMIT 1',
            array(
                'id_msg' => $id_message,
                'is_approved' => 1,
            )
        );
    } else {    //SMF 1.1
        $mobdb->query('
                SELECT m.subject, IFNULL(mem.realName, m.posterName) AS posterName, m.posterTime, m.body
                FROM ({db_prefix}messages AS m, {db_prefix}boards AS b)
                    LEFT JOIN {db_prefix}members AS mem ON (mem.ID_MEMBER = m.ID_MEMBER)
                WHERE {query_see_board} AND m.ID_MSG = {int:id_msg}
                    AND b.ID_BOARD = m.ID_BOARD
                LIMIT 1',
                array(
                    'id_msg' => $id_message,
                )
        );
    }
    if ($mobdb->num_rows() == 0) {
        createErrorResponse(30);
    }

    list ($form_subject, $mname, $mdate, $form_message) = $mobdb->fetch_row();
    $mobdb->free_result();

    // Add 'Re: ' to the front of the quoted subject.
    if (trim($context['response_prefix']) != '' && $FUNC['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
        $form_subject = $context['response_prefix'] . $form_subject;

    // Censor the message and subject.
    censorText($form_message);
    censorText($form_subject);

    // But if it's in HTML world, turn them into htmlspecialchar's so they can be edited!
    if (strpos($form_message, '[html]') !== false)
    {
        $parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $form_message, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0, $n = count($parts); $i < $n; $i++)
        {
            // It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
            if ($i % 4 == 0)
                $parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . preg_replace(\'~<br\s?/?' . '>~i\', \'&lt;br /&gt;<br />\', \'$1\') . \'[/html]\'', $parts[$i]);
        }
        $form_message = implode('', $parts);
    }

    $form_message = preg_replace('~<br ?/?' . '>~i', "\n", $form_message);

    // Remove any nested quotes, if necessary.
    if (!empty($modSettings['removeNestedQuotes']))
        $form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

    // Add a quote string on the front and end.
    $form_message = '[quote author=' . $mname . ' link=topic=' . $topic . '.msg' . (int) $id_message . '#msg' . (int) $id_message . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';

    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>post_id</name>
<value><string>' . $id_message . '</string></value>
</member>
<member>
<name>post_title</name>
<value><base64>' . base64_encode(mobi_unescape_html($form_subject)) . '</base64></value>
</member>
<member>
<name>post_content</name>
<value><base64>' .base64_encode(mobi_unescape_html($form_message)) . '</base64></value>
</member>
</struct>
</value>
</param>
</params>');
}

// Creates a new topic! it also handles method_reply_topic
function method_create_topic($is_post = false, $new_api = false)
{
    global $mobdb, $mobsettings, $modSettings, $context, $scripturl, $sourcedir, $user_info, $board, $topic, $func, $language, $txt, $sc;

    // We need these for creating topics
    require_once($sourcedir . '/Subs-Post.php');
    require_once($sourcedir . '/Post.php');

    // Guest? No entry
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Figure out the parameters
    if ($is_post)
    {
        if ($new_api) {
            $_POST['board'] = intval($context['mob_request']['params'][0][0]);
            $_POST['topic'] = intval($context['mob_request']['params'][1][0]);
            $_POST['icon'] = 'xx';
            $_POST['topic'] = intval($context['mob_request']['params'][1][0]);
            $_POST['subject'] = utf8ToAscii(base64_decode($context['mob_request']['params'][2][0]));
            $_POST['message'] = utf8ToAscii(base64_decode($context['mob_request']['params'][3][0]));
            $_POST['sc'] = $sc = '';
            //$_POST['attachments'] = isset($request_params[4]) ? explode('.', implode('.', $request_params[4])) : array();
            
            cleanRequest();
            loadBoard();
            loadPermissions();
            
            // Get a response prefix (like 'Re:') in the default forum language.
            if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
            {
                if ($language === $user_info['language'])
                    $context['response_prefix'] = $txt['response_prefix'];
                else
                {
                    loadLanguage('index', $language, false);
                    $context['response_prefix'] = $txt['response_prefix'];
                    loadLanguage('index');
                }
                cache_put_data('response_prefix', $context['response_prefix'], 600);
            }
            
            $_POST['subject'] = $context['response_prefix'] . $_POST['subject'];
            
            $post_id = Post2();
            outputRPCNewTopic($post_id, 1, true);
        } else {
            $id_topic = (int) $context['mob_request']['params'][0][0];
            $body = base64_decode($context['mob_request']['params'][2][0]);
            $subject = base64_decode($context['mob_request']['params'][3][0]);

            if (isset($context['mob_request']['params'][4]) && $context['mob_request']['params'][4][0])
                $id_attach = (int) $context['mob_request']['params'][4][0];
        }
    }
    else
    {
        if ($new_api) {
            $_POST['board'] = intval($context['mob_request']['params'][0][0]);
            $_POST['icon'] = 'xx';
            $_POST['subject'] = utf8ToAscii(base64_decode($context['mob_request']['params'][1][0]));
            $_POST['message'] = utf8ToAscii(base64_decode($context['mob_request']['params'][2][0]));
            $_POST['sc'] = $sc = '';
            //$_POST['attachments'] = isset($request_params[4]) ? explode('.', implode('.', $request_params[4])) : array();
            
            cleanRequest();
            loadBoard();
            loadPermissions();
            
            Post2();
            outputRPCNewTopic($topic, 1, false);
        } else {
            $id_board = (int) $context['mob_request']['params'][0][0];
            $subject = base64_decode($context['mob_request']['params'][1][0]);
            $body = base64_decode($context['mob_request']['params'][3][0]);
            if (isset($context['mob_request']['params'][4]) && $context['mob_request']['params'][4][0])
                $id_attach = (int) $context['mob_request']['params'][4][0];
        }
    }

    $subject = utf8ToAscii($subject);
    $body = utf8ToAscii($body);
    $_POST['subject'] = $subject;
    $_POST['message'] = $body;

    // Get a response prefix (like 'Re:') in the default forum language.
    if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
    {
        if ($language === $user_info['language'])
            $context['response_prefix'] = $txt['response_prefix'];
        else
        {
            loadLanguage('index', $language, false);
            $context['response_prefix'] = $txt['response_prefix'];
            loadLanguage('index');
        }
        cache_put_data('response_prefix', $context['response_prefix'], 600);
    }

    if($is_post)
    {
        $subject = $context['response_prefix'] . $subject;
    }


    // Trim out the whitespace
    $subject = trim($subject);
    $body = trim($body);

    // Missing? Oh man
    if ($is_post) {
        if ((!$is_post && empty($id_board)) || empty($body) || (isset($id_attach) && empty($id_attach)) || (isset($id_topic) && empty($id_topic))) {
            createErrorResponse(8);
        }
    }
    else {
        if ((!$is_post && empty($id_board)) || empty($body)|| empty($subject) || (isset($id_attach) && empty($id_attach)) || (isset($id_topic) && empty($id_topic))) {
            createErrorResponse(8);
        }
    }

    // Does this board exist?
    $mobdb->query('
        SELECT b.ID_BOARD
        FROM ' . (!empty($id_topic) ? '{db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (t.ID_BOARD = b.ID_BOARD)' : '{db_prefix}boards AS b') . '
        WHERE {query_see_board}
            AND ' . (!empty($id_topic) ? 't.ID_TOPIC' : 'b.ID_BOARD') . ' = {int:value}',
        array(
            'value' => empty($id_topic) ? $id_board : $id_topic,
        )
    );
    if ($mobdb->num_rows() == 0)
        createErrorResponse(4);
    list($id_board) = $mobdb->fetch_row();
    $mobdb->free_result();

    // Can we actually post?
    if (!isset($id_topic))
    {
        if (allowedTo('post_new', $id_board))
            $can_post = 1;
        elseif ($modSettings['postmod_active'] && !allowedTo('post_new', $id_board) && allowedTo('post_unapproved_topics', $id_board))
            $can_post = 2;
        else
            createErrorResponse(25);
    }
    else
    {
        $mobdb->query('
            SELECT locked, isSticky AS is_sticky, 1 AS approved, numReplies AS num_replies, ID_FIRST_MSG AS id_first_msg, ID_MEMBER_STARTED AS id_member_started, ID_BOARD AS id_board,
                ID_POLL AS id_poll
            FROM {db_prefix}topics
            WHERE id_topic = {int:current_topic}
            LIMIT 1',
            array(
                'current_topic' => $id_topic,
            )
        );
        $topic_info = $mobdb->fetch_assoc();
        $mobdb->free_result();

        if ($topic_info['id_board'] != $id_board)
            createErrorResponse(25);

        // Locked?
        if ($topic_info['locked'] && !allowedTo('moderate_board', $id_board))
            createErrorResponse(25);

        // Is this this guy's topic?
        if ($topic_info['id_member_started'] == $user_info['id'])
        {
            if (allowedTo('post_reply_own', $id_board))
                $can_post = 1;
            elseif ($modSettings['postmod_active'] && !allowedTo('post_reply_own', $id_board) && allowedTo('post_unapproved_replies_own', $id_board))
                $can_post = 2;
            else
                createErrorResponse(25);
        }
        else
        {
            if (allowedTo('post_reply_any', $id_board))
                $can_post = 1;
            elseif ($modSettings['postmod_active'] && !allowedTo('post_reply_any', $id_board) && allowedTo('post_unapproved_replies_any', $id_board))
                $can_post = 2;
            else
                createErrorResponse(2);
        }
    }

    // Alright, we passed the security tests, lets check the inputs
    //$subject = strtr(htmlspecialchars($subject), array("\r" => '', "\n" => '', "\t" => ''));
    //$body = htmlspecialchars($body);

    ######## Added by Sean to fix the issue can not post##############
    $subject = addslashes__recursive($subject);
    $body = addslashes__recursive($body);
    // Set up the inputs for the form.
    $body = $func['htmlspecialchars']($body, ENT_QUOTES);
    preparsecode($body);
    $subject = strtr($func['htmlspecialchars']($subject), array("\r" => '', "\n" => '', "\t" => ''));
    ##################################################################
    if (strlen($subject) > 100)
        $subject = substr($subject, 0, 100);

    // Are the attachments valid?
    if (isset($id_attach))
    {
        // Does it even exist?
        $mobdb->query('
            SELECT a.ID_ATTACH, a.ID_THUMB
            FROM {db_prefix}attachments AS a
            WHERE a.ID_ATTACH = {int:attach}',
            array(
                'attach' => $id_attach,
            )
        );
        // Not found?
        if ($mobdb->num_rows() == 0)
            unset($id_attach);

        list($id_attach, $id_thumb) = $mobdb->fetch_row();
        $mobdb->free_result();
    }

    // Get the parameters ready
    $msgOptions = array(
        'id' => 0,
        'subject' => $subject,
        'body' => $body,
        'icon' => isset($id_attach) ? 'clip' : 'xx',
        'smileys_enabled' => true,
        'attachments' => isset($id_attach) ? array($id_attach, $id_thumb) : null,
        'approved' => $can_post == 2 ? false : true,
    );
    $topicOptions = array(
        'id' => isset($id_topic) ? $id_topic : 0,
        'board' => $id_board,
        'poll' => isset($topic_info) ? $topic_info['id_poll'] : null,
        'lock_mode' => isset($topic_info) ? $topic_info['locked'] : null,
        'sticky_mode' => isset($topic_info) ? $topic_info['is_sticky'] : null,
        'mark_as_read' => true,
        'is_approved' => $can_post == 2 ? false : true,
    );
    $posterOptions = array(
        'id' => $user_info['id'],
        'name' => $user_info['name'],
        'email' => $user_info['email'],
        'update_post_count' => true,
    );

    // Actually create the topic...
    createPost($msgOptions, $topicOptions, $posterOptions);
    if (empty($topicOptions['id']))
        createErrorResponse(8);
    $id_topic = $topicOptions['id'];
    trackStats();

    // Notifications anyone?
    $notifyData = array(
        'body' => $body,
        'subject' => $subject,
        'name' => $user_info['name'],
        'poster' => $user_info['id'],
        'msg' => $msgOptions['id'],
        'board' => $id_board,
        'topic' => $id_topic,
    );
    //!!! Stupid fix for SMF 1.1
    $board = $id_board;
    $topic = $id_topic;
    
    if (!$is_post) notifyMembersBoard($notifyData);

    // Send out the response
    outputRPCNewTopic($is_post ? $msgOptions['id'] : $topicOptions['id'], $can_post, $is_post);
}

// Creates a new attachment
function method_attach_image()
{
    global $context, $mobdb, $modSettings, $mobsettingns, $scripturl, $sourcedir, $user_info, $boarddir;

    // We need these files
    require_once($sourcedir . '/Subs-Post.php');
    require_once($sourcedir . '/Subs-Package.php');

    // Get the parameters
    $image = base64_decode($context['mob_request']['params'][0][0]);
    $attach_name = base64_decode($context['mob_request']['params'][1][0]);
    $type = strtolower($context['mob_request']['params'][2][0]);
    $id_board = (int) $context['mob_request']['params'][3][0];

    // Check it out
    if (empty($image) || empty($attach_name) || !in_array($type, array('png', 'jpg', 'jpeg','image/png', 'image/jpg', 'image/jpeg')) || empty($id_board) || !allowedTo('post_attachment', $id_board))
        createErrorResponse(9);

    $attach_dir = $modSettings['attachmentUploadDir'];
    $id_folder = 1;

    // Does this board exist?
    $mobdb->query('
        SELECT b.ID_BOARD
        FROM {db_prefix}boards AS b
        WHERE {query_see_board}
            AND b.ID_BOARD = {int:board}',
        array(
            'board' => $id_board,
        )
    );
    if ($mobdb->num_rows() == 0)
        createErrorResponse(4);
    $mobdb->free_result();

    // Put this in a workable place
    $name = 'post_tmp_' . $user_info['id'] . '_' . rand(1, 100);
    $destination = $attach_dir . '/' . $name;
    @file_put_contents($destination, $image) or createErrorResponse(9);

    // Create the attachment....
    $attachmentOptions = array(
        'post' => 0,
        'poster' => $user_info['id'],
        'name' => $attach_name,
        'tmp_name' => $name,
        'size' => filesize($destination),
        'approved' => empty($modSettings['postmod_active']) || allowedTo('post_attachment'),
    );
    createAttachment($attachmentOptions);

    // It failed? NOO!!!
    if (!empty($attachmentOptions['errors']))
        createErrorResponse(10);

    // Post the success....
    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>attachment_id</name>
<value><string>' . $attachmentOptions['id'] . '</string></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

// Just a wrapper, actual handling is done by method_create_topic
function method_reply_topic()
{
    return method_create_topic(true);
}

function method_new_topic()
{
    return method_create_topic(false, true);
}

function method_reply_post()
{
    return method_create_topic(true, true);
}

// Sends a PM
function method_create_message()
{
    global $context, $mobdb, $mobsettings, $modSettings, $scripturl, $user_info, $sourcedir, $func, $sc, $options;

    require_once($sourcedir . '/PersonalMessage.php');

    // Load the parameters
    $usernames = array();
    foreach ($context['mob_request']['params'][0] as $username) {
        $user = base64_decode($username[0]);
        $user = htmltrim__recursive($user);
        $user = stripslashes__recursive($user);
        $user = htmlspecialchars__recursive($user);
        $user = addslashes__recursive($user);
        $user = utf8ToAscii($user);
        $usernames[] = $user;
    }
    
    $_REQUEST['sa'] = 'send2';
    $_POST['to'] = implode(',', $usernames);
    $_REQUEST['subject'] = addslashes__recursive(utf8ToAscii(trim(base64_decode($context['mob_request']['params'][1][0]))));
    $_REQUEST['message'] = addslashes__recursive(utf8ToAscii(trim(base64_decode($context['mob_request']['params'][2][0]))));
    $_POST['sc'] = $sc = '';
    $_REQUEST['outbox'] = !empty($options['copy_to_outbox']);
    $modSettings['pm_posts_verification'] = 0;
    $modSettings['pm_spam_settings'] = $modSettings['max_pm_recipients'].','.$modSettings['pm_posts_verification'].','.$modSettings['pm_posts_per_hour'];
    
    if ($context['mob_request']['params'][3][0] == 1 && $context['mob_request']['params'][4][0])
        $_POST['replied_to'] = intval($context['mob_request']['params'][4][0]);
    
    MessageMain();
    outputRPCResult(true);

/*
    require_once($sourcedir . '/Subs-Post.php');
    require_once($sourcedir . '/Subs-Auth.php');

    $subject = utf8ToAscii(trim(base64_decode($context['mob_request']['params'][1][0])));
    $body = utf8ToAscii(trim(base64_decode($context['mob_request']['params'][2][0])));

    ######## Added by Sean to fix the issue can not post##############
    $subject = addslashes__recursive($subject);
    $body = addslashes__recursive($body);
    ##################################################################

    if (empty($usernames) || empty($subject) || empty($body))
        createErrorResponse(7);

    // Figue out the type of action
    if (isset($context['mob_request']['params'][3]))
        $action_type = (int) (in_array($context['mob_request']['params'][3][0], array(1, 2)) ? $context['mob_request']['params'][3][0] : 0);
    else
        $action_type = 0;

    // Base PM?
    if (!empty($action_type))
        $base_pm = (int) $context['mob_request']['params'][4][0];

    // Lets take cre of the uernames, figure out each and every member
    $members = findMembers($usernames);
    $id_members = array_keys($members);

    // No members?
    if (empty($id_members))
        createErrorResponse(26);

    // Too many recipients?
    list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);
    if (count($id_members) > $modSettings['max_pm_recipients'] && $modSettings['max_pm_recipients'] != 0)
        createErrorResponse(29);

    // Send the PM
    $result = sendpm(array('to' => $id_members, 'bcc' => array()), $subject, $body, true);

    // We succeeded?
    outputRPCResult(true);
*/
}

// Gets a single user's topic
function method_get_user_topic()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info, $sourcedir;

    // Guest?
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Get the username
    $username = base64_decode($context['mob_request']['params'][0][0]);
    if (empty($username))
        createErrorResponse(8);

    require_once($sourcedir . '/Subs-Auth.php');

    ######## Added by Sean##############
    $username = htmltrim__recursive($username);
    $username = stripslashes__recursive($username);
    $username = htmlspecialchars__recursive($username);
    $username = addslashes__recursive($username);
    ##################################################################

    // Does this user exist?
    $members = findMembers($username);
    if (empty($members))
        createErrorResponse(8);
    $id_member = array_keys($members);
    $member = $members[$id_member[0]];
    if (empty($member))
        createErrorResponse(8);
    // Load the posts
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                IFNULL(mem.ID_MEMBER, 0) AS id_member, IFNULL(mem.realName, fm.posterName) AS mem_name, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                IFNULL(lm.body, fm.body) AS body, lm.ID_MSG_MODIFIED AS id_msg_modified, b.name AS board_name, b.ID_BOARD AS id_board, mem1.realName AS last_poster_name, mem1.memberName as last_poster_username
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mem1 ON (lm.ID_MEMBER = mem1.ID_MEMBER)
            LEFT JOIN {db_prefix}members AS mem ON (fm.ID_MEMBER = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE {query_see_board}
            AND t.ID_MEMBER_STARTED = {int:member}
        ORDER BY fm.posterTime DESC
        LIMIT 20',
        array(
            'current_member' => $user_info['id'],
            'member' => $member['id'],
        )
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc())
    {
        // Add stuff to the array
        $topics[$row['id_topic']] = array(
            'id' => $row['id_topic'],
            'title' => processSubject($row['topic_title']),
            'short_msg' => processShortContent($row['body']),
            'replies' => $row['replies'],
            'views' => $row['views'],
            'poster' => array(
                'id' => $row['id_member'],
                'name' => $row['mem_name'],
                'avatar' => get_avatar($row),
            ),
            'last_poster_name' => $row['last_poster_name'],
            'last_poster_username' => $row['last_poster_username'],
            'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
            'board' => $row['id_board'],
            'board_name' => $row['board_name'],
            'last_msg_time' => mobiquo_time($row['last_message_time']),
        );
    }
    $mobdb->free_result();

    // LAME!
    outputRPCNewTopics($topics);
}

// Gets a post in its raw format
function method_get_raw_post()
{
    global $mobdb, $mobsettings, $user_info, $context, $sourcedir;

    if ($user_info['is_guest'])
        createErrorResponse(8);

    // What is this post?
    $id_msg = (int) $context['mob_request']['params'][0][0];
    if (empty($id_msg))
        createErrorResponse(6);

    $mobdb->query('
        SELECT m.body, b.ID_BOARD AS id_board, m.subject, m.ID_MEMBER AS id_member, t.locked, t.ID_MEMBER_STARTED, m.posterTime
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (m.ID_TOPIC = t.ID_TOPIC)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE m.ID_MSG = {int:msg}',
        array(
            'msg' => $id_msg,
        )
    );
    if ($mobdb->num_rows() == 0)
        createErrorResponse(6);
    list ($body, $id_board, $subject, $id_member_posted, $locked, $id_member_start, $post_time) = $mobdb->fetch_row();
    $is_started = $user_info['id'] == $id_member_start && !$user_info['is_guest'];
    $can_edit = (!$locked || allowedTo('moderate_board', $id_board)) && (allowedTo('modify_any', $id_board) || (allowedTo('modify_replies', $id_board) && $is_started) || (allowedTo('modify_own', $id_board) && $id_member_posted == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $post_time + $modSettings['edit_disable_time'] * 60 > time())));

    $mobdb->free_result();

    // Can we not modify?
    if (! $can_edit) {
        createErrorResponse(6);
    }

//    if ($user_info['id'] != $id_member_posted || !allowedTo('modify_own', $id_board))
//        createErrorResponse(6);

    // change <br> to \n
    $body = preg_replace('~<br(?: /)?' . '>~i', "\n", $body);

    // Return the resonse
    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>post_id</name>
<value><string>' . $id_msg . '</string></value>
</member>
<member>
<name>post_title</name>
<value><base64>' . base64_encode(mobi_unescape_html($subject)) . '</base64></value>
</member>
<member>
<name>post_content</name>
<value><base64>' . base64_encode(mobi_unescape_html($body)) . '</base64></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

// Saves a raw post
function method_save_raw_post()
{
    global $mobdb, $mobsettings, $user_info, $context, $sourcedir, $func, $smcFunc;

    if ($user_info['is_guest'])
        createErrorResponse(8);

    require_once($sourcedir . '/Subs-Post.php');

    // What is this post?
    $id_msg = (int) $context['mob_request']['params'][0][0];
    if (empty($id_msg))
        createErrorResponse(6);
    $subject = utf8ToAscii(trim(base64_decode($context['mob_request']['params'][1][0])));
    $body = utf8ToAscii(trim(base64_decode($context['mob_request']['params'][2][0])));

    ######## Added by Sean##############
    $subject = addslashes__recursive($subject);
    $body = addslashes__recursive($body);
    // Set up the inputs for the form.
    $body = $func['htmlspecialchars']($body, ENT_QUOTES);
    preparsecode($body);
    $subject = strtr($func['htmlspecialchars']($subject), array("\r" => '', "\n" => '', "\t" => ''));
    ##################################################################

    if (empty($body))
        createErrorResponse('incorrect_params', '', 'xmlrpc');

    // Get the board and body
    $mobdb->query('
        SELECT b.ID_BOARD AS id_board, m.ID_MEMBER AS id_member, t.isSticky, t.locked, t.ID_TOPIC, m.posterTime AS poster_time, t.ID_MEMBER_STARTED
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (m.ID_TOPIC = t.ID_TOPIC)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE m.ID_MSG = {int:msg}',
        array(
            'msg' => $id_msg,
        )
    );

    if ($mobdb->num_rows() == 0)
        createErrorResponse(6);

    list ($id_board, $id_member_posted, $sticky, $locked, $id_topic, $post_time, $id_member_start) = $mobdb->fetch_row();
    $mobdb->free_result();

    $is_started = $user_info['id'] == $id_member_start && !$user_info['is_guest'];
    $can_edit = (!$locked || allowedTo('moderate_board', $id_board)) && (allowedTo('modify_any', $id_board) || (allowedTo('modify_replies', $id_board) && $is_started) || (allowedTo('modify_own', $id_board) && $id_member_posted == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $post_time + $modSettings['edit_disable_time'] * 60 > time())));
    if (! $can_edit) {
        createErrorResponse(6);
    }

    //$subject = strtr(htmlspecialchars($subject), array("\r" => '', "\n" => '', "\t" => ''));
    //$body = htmlspecialchars($body);
    $body = un_preparsecode($body);
    censorText($subject);
    censorText($body);

    // Save it!
    $msgOptions = array(
        'body' => $body,
        'id' => $id_msg,
    );
    
    if ($subject) $msgOptions['subject'] = $subject;
    
    $topicOptions = array(
        'id' => $id_topic,
        'sticky_mode' => $sticky,
        'locked_mode' => $locked,
    );
    $posterOptions = array();
    modifyPost($msgOptions, $topicOptions, $posterOptions);

    outputRPCResult(true);
}

// Gets unreadreplies
function method_get_user_reply_post()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info, $sourcedir;

    // Guest?
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Get the username
    $username = base64_decode($context['mob_request']['params'][0][0]);
    if (empty($username))
        createErrorResponse(8);

    require_once($sourcedir . '/Subs-Auth.php');

    ######## Added by Sean##############
    $username = htmltrim__recursive($username);
    $username = stripslashes__recursive($username);
    $username = htmlspecialchars__recursive($username);
    $username = addslashes__recursive($username);
    ##################################################################

    // Does this user exist?
    $members = findMembers($username);
    if (empty($members))
        createErrorResponse(8);
    $id_member = array_keys($members);
    $member = $members[$id_member[0]];
    if (empty($member))
        createErrorResponse(8);

    // Load the posts
    $mobdb->query('
        SELECT m.ID_MSG as post_id, m.subject as post_title, t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                IFNULL(mem.ID_MEMBER, 0) AS id_member, mem.realName, mem.memberName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                IFNULL(m.body, fm.body) AS body, lm.ID_MSG_MODIFIED AS id_msg_modified, b.name AS board_name, b.ID_BOARD AS id_board
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS m ON (m.ID_TOPIC = t.ID_TOPIC AND m.ID_MEMBER = {int:member})
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mem ON (lm.ID_MEMBER = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE {query_see_board}
        ORDER BY lm.posterTime DESC
        LIMIT 20',
        array(
            'current_member' => $user_info['id'],
            'member' => $member['id'],
        )
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc())
    {
        // Add stuff to the array
        $topics[$row['id_topic']] = array(
            'id' => $row['id_topic'],
            'title' => processSubject($row['topic_title']),
            'post_id' => $row['post_id'],
            'post_title' => processSubject($row['post_title']),
            'short_msg' => processShortContent($row['body']),
            'replies' => $row['replies'],
            'views' => $row['views'],
            'poster' => array(
                'id' => $row['id_member'],
                'post_name' => $row['realName'],
                'username' => $row['memberName'],
                'avatar' => get_avatar($row),
            ),
            'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
            'board' => $row['id_board'],
            'board_name' => $row['board_name'],
            'post_time' => mobiquo_time($row['last_message_time']),
        );
    }
    $mobdb->free_result();

    // LAME!
    outputRPCNewTopics($topics);
}

// Gets subscribed topics
function method_get_subscribed_topic()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info;

    // Guest?
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Load the posts
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, t.locked, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                IFNULL(mem.ID_MEMBER, 0) AS id_member, mem.realName, mem.memberName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                IFNULL(lm.body, fm.body) AS body, lm.ID_MSG_MODIFIED AS id_msg_modified, b.name AS board_name, b.ID_BOARD AS id_board
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}log_notify AS ln ON (ln.ID_TOPIC = t.ID_TOPIC AND ln.ID_MEMBER = {int:current_member})
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mem ON (lm.ID_MEMBER = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE {query_see_board}
        ORDER BY lm.posterTime DESC
        LIMIT 20',
        array(
            'current_member' => $user_info['id'],
        )
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc())
    {
        // Add stuff to the array
        $topics[$row['id_topic']] = array(
            'id' => $row['id_topic'],
            'title' => processSubject($row['topic_title']),
            'short_msg' => processShortContent($row['body']),
            'replies' => $row['replies'],
            'views' => $row['views'],
            'poster' => array(
                'id' => $row['id_member'],
                'post_name' => $row['realName'],
                'username' => $row['memberName'],
                'avatar' => get_avatar($row),
            ),
            'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
            'board' => $row['id_board'],
            'board_name' => $row['board_name'],
            'post_time' => mobiquo_time($row['last_message_time']),
            'is_marked_notify' => true,
            'is_locked' => !empty($row['locked']),
        );
    }
    $mobdb->free_result();

    // Get the count
    $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}log_notify AS ln ON (ln.ID_TOPIC = t.ID_TOPIC AND ln.ID_MEMBER = {int:current_member})
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            LEFT JOIN {db_prefix}messages AS lm ON (lm.ID_MSG = t.ID_LAST_MSG)
        WHERE {query_see_board}
        ORDER BY lm.posterTime DESC',
        array(
            'current_member' => $user_info['id'],
        )
    );
    list($count) = $mobdb->fetch_row();
    $mobdb->free_result();

    // Return...
    outputRPCSubscribedTopics($topics, $count);
}

// Returns the overall board statistics
function method_get_board_stat()
{
    global $modSettings, $context;

    $members_online = getMembersOnline();

    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>total_threads</name>
<value><int>' . $modSettings['totalTopics'] . '</int></value>
</member>
<member>
<name>total_posts</name>
<value><int>' . $modSettings['totalMessages'] . '</int></value>
</member>
<member>
<name>total_members</name>
<value><int>' . $modSettings['totalMembers'] . '</int></value>
</member>
<member>
<name>active_members</name>
<value><int>' . $modSettings['totalMembers'] . '</int></value>
</member>
<member>
<name>guest_online</name>
<value><int>' . $members_online['num_guests'] . '</int></value>
</member>
<member>
<name>total_online</name>
<value><int>' . ($members_online['num_guests'] + count($members_online['users_online'])) . '</int></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

// Returns the current online members
function method_get_online_users()
{
    $members_online = getMembersOnline();

    outputRPCOnline($members_online);
}

// Gets the dashboard stuff
function method_get_dashboard()
{
    global $context, $user_info, $mobdb;

    if ($user_info['is_guest'])
        createErrorResponse(8);

    // Get the unread coount
    $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}topics AS t
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE {query_see_board}
            AND IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, 0)) < t.ID_LAST_MSG',
        array(
            'current_member' => $user_info['id'],
        )
    );
    list($unread_count) = $mobdb->fetch_row();
    $mobdb->free_result();

    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>total_unread_count<name>
<value><string>' . $unread_count . '</string></value>
</member>
<member>
<name>post_title</name>
<value><base64>' . $user_info['unread_messages'] . '</base64></value>
</member>
</struct>
</value>
</param>
</params>'
    );
}

// Gets unread topics
function method_get_unread_topic()
{
    global $forum_root, $context, $modSettings;
    include_once($forum_root . '/Sources/Recent.php');
    
    // Star/end
    if (isset($context['mob_request']['params'][0]))
        $start_num = (int) $context['mob_request']['params'][0][0];
    if (isset($context['mob_request']['params'][1]))
        $last_num = (int) $context['mob_request']['params'][1][0];

    list($_REQUEST['start'], $modSettings['defaultMaxTopics']) = process_page($start_num, $last_num);
    $_REQUEST['action'] = 'unread';
    
    UnreadTopics();
    
    $stids = get_subscribed_tids();
    $uids = array();
    if (!empty($context['topics']))
        foreach($context['topics'] as $tid => $topic)
        {
            $context['topics'][$tid]['is_marked_notify'] = in_array($tid, $stids);
            $uids[] = $topic['last_post']['member']['id'];
        }
    
    if (!empty($uids))
    {
        $avatars = get_avatar_by_ids($uids);
        foreach($context['topics'] as $tid => $topic)
        {
            $context['topics'][$tid]['last_post']['member']['avatar'] = $avatars[$topic['last_post']['member']['id']];
        }
    }
    
    outputRPCSubscribedTopics($context['topics'], $context['num_topics']);
}

/*
// Gets unread topics
function method_get_unread_topic()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info;

    // Guest?
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Star/end
    if (isset($context['mob_request']['params'][0]))
        $start_num = (int) $context['mob_request']['params'][0][0];
    if (isset($context['mob_request']['params'][1]))
        $last_num = (int) $context['mob_request']['params'][1][0];

    $topics_per_page = 20;
    if (!isset($start_num) && !isset($last_num))
        $limit = $topics_per_page;
    elseif (isset($start_num) && !isset($last_num))
        $limit = $start_num . ', ' . $topics_per_page;
    elseif (isset($start_num) && isset($last_num))
        $limit = $start_num . ', ' . (($last_num - $start_num) + 1);

    // Get the unread coount
    $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}topics AS t
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE {query_see_board}
            AND IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, 0)) < t.ID_LAST_MSG',
        array(
            'current_member' => $user_info['id'],
        )
    );
    list($unread_count) = $mobdb->fetch_row();
    $mobdb->free_result();

    // Load the posts
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, t.locked, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                IFNULL(mem.ID_MEMBER, 0) AS id_member, mem.realName, mem.memberName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'ln.ID_TOPIC AS is_notify, IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                IFNULL(lm.body, fm.body) AS body, lm.ID_MSG_MODIFIED AS id_msg_modified, b.name AS board_name, b.ID_BOARD AS id_board
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mem ON (lm.ID_MEMBER = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_notify AS ln ON ((ln.ID_TOPIC = t.ID_TOPIC OR ln.ID_BOARD = t.ID_BOARD) AND ln.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
        WHERE {query_see_board}
            AND IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, 0)) < t.ID_LAST_MSG
        ORDER BY lm.posterTime DESC
        LIMIT ' . $limit,
        array(
            'current_member' => $user_info['id'],
        )
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc())
    {
        // Add stuff to the array
        $topics[$row['id_topic']] = array(
            'id' => $row['id_topic'],
            'title' => processSubject($row['topic_title']),
            'short_msg' => processShortContent($row['body']),
            'replies' => $row['replies'],
            'views' => $row['views'],
            'poster' => array(
                'id' => $row['id_member'],
                'username' => $row['memberName'],
                'post_name' => $row['realName'],
                'avatar' => get_avatar($row),
            ),
            'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
            'board' => $row['id_board'],
            'board_name' => $row['board_name'],
            'post_time' => mobiquo_time($row['last_message_time']),
            'is_marked_notify' => !empty($row['is_notify']),
            'is_locked' => !empty($row['locked']),
        );
    }
    $mobdb->free_result();

    // LAME!
    outputRPCSubscribedTopics($topics, $unread_count);
}
*/

// Mark ALL the topics as READ!
function method_mark_all_as_read()
{
    global $mobdb, $context, $scripturl, $user_info, $modSettings, $sourcedir;

    // Guest?
    if ($user_info['is_guest'])
        createErrorResponse(8);

    $whereadd = '';
    if (isset($context['mob_request']['params'][0][0])) {
        $id_board = intval($context['mob_request']['params'][0][0]);
        $whereadd = " AND b.ID_BOARD=$id_board";
    }

    // Get all the boards this user can see
    $mobdb->query('
        SELECT b.ID_BOARD AS id_board
        FROM {db_prefix}boards AS b
        WHERE {query_see_board}' . $whereadd,
        array()
    );
    $boards = array();
    while ($row = $mobdb->fetch_assoc())
        $boards[] = $row['id_board'];
    $mobdb->free_result();

    // We got boards?
    if (!empty($boards))
    {
        require_once($sourcedir . '/Subs-Boards.php');
        markBoardsRead($boards, false);
    }

    outputRPCResult(true);
}

// Handles the search
function method_search_topic($subject_only = 1)
{
    global $mobdb, $context, $sourcedir, $user_info, $modSettings, $scripturl, $modSettings, $messages_request;

    // Search string
    $string = base64_decode($context['mob_request']['params'][0][0]);
    if (empty($string))
        createErrorResponse(8);

    // Start/limit
    if (isset($context['mob_request']['params'][0]))
        $start_num = (int) $context['mob_request']['params'][1][0];
    if (isset($context['mob_request']['params'][1]))
        $limit = (int) (($context['mob_request']['params'][2][0] - $start_num) + 1);

    // We got an ID?
    if (isset($context['mob_request']['params'][3]))
        $id_search = $context['mob_request']['params'][3][0];

    // Is it an existing search?
    $new_search = !isset($id_search) || empty($_SESSION['search_cache'][$id_search]);

    if (!$new_search)
        $_SESSION['search_cache'] = $_SESSION['search_cache'][$id_search];

    // We use a cheap hack to perform our search
    $_REQUEST['start'] = $_GET['start'] = isset($start_num) ? $start_num : 0;
    $modSettings['search_results_per_page'] = isset($limit) ? $limit : 20;
    $_REQUEST['search'] = $_POST['search'] = $string;
    $_REQUEST['advanced'] = $_POST['advanced'] = 0;
    $_REQUEST['subject_only'] = $_POST['subject_only'] = $subject_only;
    require_once($sourcedir . '/Search.php');
    PlushSearch2();

    // We got results?
    if (!isset($_SESSION['search_cache']))
        createErrorResponse(8);

    $count = $_SESSION['search_cache']['num_results'];
    $search_id = $_SESSION['search_cache']['ID_SEARCH'];

    // Cache it
    if (isset($id_search))
    {
        $search_cache = $_SESSION['search_cache'];
        unset($_SESSION['search_cache']);
        $_SESSION['search_cache'][$id_search] = $search_cache;
        unset ($search_cache);
    }

    // Get the results
    $topics = array();
    $tids = array();
    while ($topic = $context['get_topics']())
    {
        $topics[$topic['id']] = array(
            'board' => $topic['board']['id'],
            'board_name' => $topic['board']['name'],
            'id' => $topic['id'],
            'poster' => array(
                'id' => $topic['matches'][0]['member']['id'],
                'post_name' => $topic['matches'][0]['member']['name'],
                'username' => $topic['matches'][0]['member']['username'],
                'avatar' => $topic['matches'][0]['member']['avatar']['href'],
            ),
            'post_time' => mobiquo_time($topic['first_post']['timestamp']),
            'views' => $topic['views'],
            'replies' => $topic['replies'],
            'title' => processSubject($topic['first_post']['subject']),
            'short_msg' => processShortContent($topic['matches'][0]['body']),
            'is_marked_notify' => false,
            'is_locked' => !empty($topic['is_locked']),
            'post_id' => $topic['matches'][0]['id'],
            'post_title' => processSubject($topic['matches'][0]['subject']),
        );
        $tids[] = $topic['id'];
    }

    if (!empty($tids))
    {
        // Check for notifications on this topic OR board.
        $mobdb->query("
            SELECT sent, ID_TOPIC
            FROM {db_prefix}log_notify
            WHERE ID_TOPIC IN ({array_int:topic_ids})
                AND ID_MEMBER = {int:member}",
            array(
                'topic_ids' => $tids,
                'member' => $user_info['id']
            )
        );

        while ($row = $mobdb->fetch_assoc())
        {
            // Find if this topic is marked for notification...
            if (!empty($row['ID_TOPIC']))
                $topics[$row['ID_TOPIC']]['is_marked_notify'] = true;
        }
        $mobdb->free_result();
    }

    // Output the results
    outputRPCSubscribedTopics($topics, $count, $search_id);
}

function method_search_post()
{
    method_search_topic(0);
}

// Gets a single user's topic
function method_get_participated_topic()
{
    global $context, $mobdb, $mobsettings, $modSettings, $user_info, $sourcedir;

    // Guest?
    if ($user_info['is_guest'])
        createErrorResponse(21);

    // Get the username
    $username = base64_decode($context['mob_request']['params'][0][0]);
    if (empty($username))
        createErrorResponse(8);

    require_once($sourcedir . '/Subs-Auth.php');

    ######## Added by Sean##############
    $username = htmltrim__recursive($username);
    $username = stripslashes__recursive($username);
    $username = htmlspecialchars__recursive($username);
    $username = addslashes__recursive($username);
    ##################################################################

    // Does this user exist?
    $members = findMembers($username);
    if (empty($members))
        createErrorResponse(8);
    $id_member = array_keys($members);
    $member = $members[$id_member[0]];
    if (empty($member))
        createErrorResponse(8);

    // Do we have start num defined?
    if (isset($context['mob_request']['params'][1]))
        $start_num = (int) $context['mob_request']['params'][1][0];

    // Do we have last number defined?
    if (isset($context['mob_request']['params'][2]))
        $last_num = (int) $context['mob_request']['params'][2][0];

    // Perform some start/last num checks
    if (isset($start_num) && isset($last_num))
        if ($start_num > $last_num)
            createErrorResponse(3);
        elseif ($last_num - $start_num > 50)
            $last_num = $start_num + 50;

    // Default number of topics per page
    $topics_per_page = 20;

    // Generate the limit clause
    $limit = '';
    if (!isset($start_num) && !isset($last_num)) {
        $start_num = 0;
        $limit = $topics_per_page;
    } elseif (isset($start_num) && !isset($last_num)) {
        $limit = $topics_per_page;
    } elseif (isset($start_num) && isset($last_num)) {
        $limit = $last_num - $start_num + 1;
    } elseif (empty($start_num) && empty($last_num)) {
        $start_num = 0;
        $limit = $topics_per_page;
    }

    // Get the count
    $mobdb->query('
        SELECT t.ID_TOPIC
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (m.ID_TOPIC = t.ID_TOPIC)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
        WHERE {query_see_board}
            AND m.ID_MEMBER = {int:member}
        GROUP BY t.ID_TOPIC
        ORDER BY t.ID_TOPIC DESC',
        array(
            'member' => $id_member[0],
        )
    );
    $tids = array();
    while ($row = $mobdb->fetch_assoc()) {
        $tids[] = $row['ID_TOPIC'];
    }
    $mobdb->free_result();

    $count = count($tids);
    if ($limit + $start_num > $count) $limit = $count - $start_num;
    $tids = array_slice($tids, $start_num, $limit);

    $topics = array();
    if (count($tids)) {
        // Grab the topics
        $mobdb->query('
            SELECT t.ID_TOPIC AS id_topic, t.isSticky AS is_sticky, t.locked, fm.subject AS topic_title, t.numViews AS views, t.numReplies AS replies,
                    IFNULL(mem.ID_MEMBER, 0) AS id_member, mem.realName, mem.memberName, mem.avatar, IFNULL(a.ID_ATTACH, 0) AS id_attach, a.filename, a.attachmentType AS attachment_type,
                    IFNULL(lm.posterTime, fm.posterTime) AS last_message_time, ' . ($user_info['is_guest'] ? '0' : 'ln.ID_TOPIC AS is_notify, IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                    IFNULL(lm.body, fm.body) AS body, lm.ID_MSG_MODIFIED AS id_msg_modified, b.name AS board_name, b.ID_BOARD AS id_board
            FROM {db_prefix}messages AS m
                INNER JOIN {db_prefix}topics AS t ON (m.ID_TOPIC = t.ID_TOPIC)
                INNER JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
                INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = t.ID_BOARD)
                LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
                LEFT JOIN {db_prefix}members AS mem ON (lm.ID_MEMBER = mem.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
                LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
                LEFT JOIN {db_prefix}log_notify AS ln ON ((ln.ID_TOPIC = t.ID_TOPIC OR ln.ID_BOARD = t.ID_BOARD) AND ln.ID_MEMBER = {int:current_member})
                LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
                LEFT JOIN {db_prefix}attachments AS a ON (a.ID_MEMBER = mem.ID_MEMBER)
            WHERE {query_see_board}
                AND m.ID_MEMBER = {int:member} AND t.ID_TOPIC IN ({array_int:topic_ids})
            ORDER BY lm.posterTime DESC',
            array(
                'current_member' => $user_info['id'],
                'member' => $id_member[0],
                'topic_ids' => $tids,
            )
        );

        while ($row = $mobdb->fetch_assoc())
        {
            // Add stuff to the array
            $topics[$row['id_topic']] = array(
                'id' => $row['id_topic'],
                'title' => processSubject($row['topic_title']),
                'short_msg' => processShortContent($row['body']),
                'replies' => $row['replies'],
                'views' => $row['views'],
                'poster' => array(
                    'id' => $row['id_member'],
                    'username' => $row['memberName'],
                    'post_name' => $row['realName'],
                    'avatar' => get_avatar($row),
                ),
                'is_new' => $user_info['is_guest'] ? 0 : $row['new_from'] <= $row['id_msg_modified'],
                'board' => $row['id_board'],
                'board_name' => $row['board_name'],
                'post_time' => mobiquo_time($row['last_message_time']),
                'is_marked_notify' => !empty($row['is_notify']),
                'is_locked' => !empty($row['locked']),
            );
        }
        $mobdb->free_result();
    }

    // LAME!
    outputRPCSubscribedTopics($topics, $count);
}

function method_subscribe_forum($action = 'on')
{
    global $scripturl, $txt, $board, $ID_MEMBER, $user_info, $context, $mobdb;

    // Permissions are an important part of anything ;).
    if ($user_info['is_guest'])
        outputRPCResult(false, $txt[1]);

    if (!isset($context['mob_request']['params'][0]))
        outputRPCResult(false, $txt['smf232']);

    $id_board = (int) $context['mob_request']['params'][0][0];

    if(!allowedTo('mark_notify', $id_board))
        outputRPCResult(false, $txt['cannot_mark_notify']);

    if ($action == 'on')
    {
        $mobdb->insert('{db_prefix}log_notify',
            array('ID_MEMBER', 'ID_BOARD'),
            array($user_info['id'], $id_board),
            true
        );
    }
    // ...or off?
    else
    {
        $mobdb->query('
            DELETE FROM {db_prefix}log_notify
            WHERE ID_MEMBER = {int:member}
                AND ID_BOARD = {int:board}
            LIMIT 1',
            array(
                'member' => $user_info['id'],
                'board' => $id_board,
            )
        );
    }

    outputRPCResult(true);
}

function method_unsubscribe_forum()
{
    method_subscribe_forum('off');
}

function method_get_subscribed_forum()
{
    global $txt, $user_info, $mobdb;

    // Permissions are an important part of anything ;).
    if ($user_info['is_guest'])
        outputRPCResult(false, $txt[1]);

    // All the boards with notification on..
    $mobdb->query("
        SELECT b.ID_BOARD, b.name, IFNULL(lb.ID_MSG, 0) AS boardRead, b.ID_MSG_UPDATED
        FROM ({db_prefix}log_notify AS ln, {db_prefix}boards AS b)
            LEFT JOIN {db_prefix}log_boards AS lb ON (lb.ID_BOARD = b.ID_BOARD AND lb.ID_MEMBER = {int:member})
        WHERE {query_see_board} AND ln.ID_MEMBER = {int:member}
            AND b.ID_BOARD = ln.ID_BOARD
        ORDER BY b.boardOrder",
        array(
            'member' => $user_info['id'],
        )
    );
    $boards = array();
    while ($row = $mobdb->fetch_assoc())
        $boards[] = array(
            'id' => $row['ID_BOARD'],
            'name' => $row['name'],
            'new' => $row['boardRead'] < $row['ID_MSG_UPDATED'],
            'icon' => get_board_icon($row['ID_BOARD']),
        );
    $mobdb->free_result();

    outputRPCSubscribedBoards($boards);
}

function method_get_quote_pm()
{
    global $context, $mobdb, $user_info, $sourcedir, $txt, $modSettings, $func, $language;

    if ($user_info['is_guest'])
        outputRPCResult(false, $txt[1]);

    if (!allowedTo('pm_read'))
        outputRPCResult(false, $txt['cannot_pm_read']);

    if (!allowedTo('pm_send'))
        outputRPCResult(false, $txt['cannot_pm_send']);

    require_once($sourcedir . '/PersonalMessage.php');

    // Get the message ID
    if (!isset($context['mob_request']['params'][0]))
        outputRPCResult(false, $txt['smf272']);
    $id_pm = $context['mob_request']['params'][0][0];

    // Load this message...
    $mobdb->query('
        SELECT pm.ID_PM AS id_pm, pm.subject, pm.body, pm.msgtime, pm.ID_MEMBER_FROM AS id_member_from, mem_from.realName
        FROM {db_prefix}personal_messages AS pm
        LEFT JOIN {db_prefix}pm_recipients AS pr ON (pm.ID_PM = pr.ID_PM)
        LEFT JOIN {db_prefix}members AS mem_from ON (mem_from.ID_MEMBER = pm.ID_MEMBER_FROM)
        WHERE pm.ID_PM = {int:pm} AND (pm.ID_MEMBER_FROM = {int:member} OR pr.ID_MEMBER = {int:member})',
        array(
            'pm' => $id_pm,
            'member' => $user_info['id'],
        )
    );
    if ($mobdb->num_rows() == 0)
        outputRPCResult(false, $txt['pm_not_yours']);
    $pm = $mobdb->fetch_assoc();
    $mobdb->free_result();

    censorText($pm['subject']);
    censorText($pm['body']);

    // Add 'Re: ' to it....
    if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
    {
        if ($language === $user_info['language'])
            $context['response_prefix'] = $txt['response_prefix'];
        else
        {
            loadLanguage('index', $language, false);
            $context['response_prefix'] = $txt['response_prefix'];
            loadLanguage('index');
        }
        cache_put_data('response_prefix', $context['response_prefix'], 600);
    }

    $form_subject = $pm['subject'];
    if (trim($context['response_prefix']) != '' && $func['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
        $form_subject = $context['response_prefix'] . $form_subject;

    // Remove any nested quotes and <br />...
    $form_message = preg_replace('~<br( /)?' . '>~i', "\n", $pm['body']);
    if (!empty($modSettings['removeNestedQuotes']))
        $form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

    $form_message = processBody($form_message);

    if (empty($pm['id_member_from']))
        $form_message = '[quote author=&quot;' . $pm['realName'] . "&quot;]\n" . $form_message . "\n[/quote]";
    else
        $form_message = '[quote author=' . $pm['realName'] . ' link=action=profile;u=' . $pm['id_member_from'] . ' date=' . $pm['msgtime'] . "]\n" . $form_message . "\n[/quote]";

    outputRPCResponse('
<params>
<param>
<value>
<struct>
<member>
<name>msg_id</name>
<value><string>' . $id_pm . '</string></value>
</member>
<member>
<name>msg_subject</name>
<value><base64>' . base64_encode(mobi_unescape_html(processSubject($form_subject))) . '</base64></value>
</member>
<member>
<name>text_body</name>
<value><base64>' .base64_encode(mobi_unescape_html($form_message)) . '</base64></value>
</member>
</struct>
</value>
</param>
</params>');
}

function method_report_post()
{
    global $context, $mobdb, $modSettings, $scripturl, $user_info, $sourcedir, $txt;

    // Get the message ID
    if (!isset($context['mob_request']['params'][0]))
        outputRPCResult(false, $txt['smf272']);
    $id_msg = (int) $context['mob_request']['params'][0][0];
    $reason = utf8ToAscii(base64_decode($context['mob_request']['params'][1][0]));

    require_once($sourcedir . '/Subs-Post.php');

    $mobdb->query("
        SELECT m.subject, m.ID_MEMBER, m.posterName, mem.realName, m.ID_TOPIC, m.ID_BOARD
        FROM {db_prefix}messages AS m
            LEFT JOIN {db_prefix}members AS mem ON (m.ID_MEMBER = mem.ID_MEMBER)
        WHERE m.ID_MSG = $id_msg
        LIMIT 1", array());
    if ($mobdb->num_rows() == 0)
        outputRPCResult(false, $txt['smf272']);
    $message_info = $mobdb->fetch_assoc();
    global $topic, $board;
    list ($subject, $member, $posterName, $realName, $topic, $board) = array($message_info['subject'], $message_info['ID_MEMBER'], $message_info['posterName'], $message_info['realName'], $message_info['ID_TOPIC'], $message_info['ID_BOARD']);
    $mobdb->free_result();

    loadBoard();
    loadPermissions();

    // You can't use this if it's off or you are not allowed to do it.
    if (!allowedTo('report_any'))
        outputRPCResult(false, $txt['cannot_report_any']);

    spamProtection('spam');

    if ($member == $user_info['id'])
        outputRPCResult(false, $txt['rtm_not_own']);

    $posterName = un_htmlspecialchars($realName) . ($realName != $posterName ? ' (' . $posterName . ')' : '');
    $reporterName = un_htmlspecialchars($user_info['name']) . ($user_info['name'] != $user_info['username'] && $user_info['username'] != '' ? ' (' . $user_info['username'] . ')' : '');
    $subject = un_htmlspecialchars($subject);

    // Get a list of members with the moderate_board permission.
    require_once($sourcedir . '/Subs-Members.php');
    $moderators = membersAllowedTo('moderate_board', $board);

    $mobdb->query("
        SELECT ID_MEMBER, emailAddress, lngfile
        FROM {db_prefix}members
        WHERE ID_MEMBER IN (" . implode(', ', $moderators) . ")
            AND notifyTypes != 4
        ORDER BY lngfile", array());

    // Check that moderators do exist!
    if ($mobdb->num_rows() == 0)
        outputRPCResult(false, $txt['rtm11']);

    // Send every moderator an email.
    while ($row = $mobdb->fetch_assoc())
    {
        loadLanguage('Post', empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'], false);

        // Send it to the moderator.
        sendmail($row['emailAddress'], $txt['rtm3'] . ': ' . $subject . ' ' . $txt['rtm4'] . ' ' . $posterName,
            sprintf($txt['rtm_email1'], $subject) . ' ' . $posterName . ' ' . $txt['rtm_email2'] . ' ' . (empty($user_info['id']) ? $txt['guest'] . ' (' . $user_info['ip'] . ')' : $reporterName) . ' ' . $txt['rtm_email3'] . ":\n\n" .
            $scripturl . '?topic=' . $topic . '.msg' . $id_msg . '#msg' . $id_msg . "\n\n" .
            $txt['rtm_email_comment'] . ":\n" .
            $reason . "\n\n" .
            $txt[130], $user_info['email']);
    }
    $mobdb->free_result();

    outputRPCResult(true);
}
