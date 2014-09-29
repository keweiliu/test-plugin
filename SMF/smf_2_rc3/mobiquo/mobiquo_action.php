<?php

defined('IN_MOBIQUO') or exit;

function action_get_config(){}

function action_get_forum()
{
    global $modSettings, $user_info, $context, $smcFunc;
    
    // Find all the boards this user is allowed to see.
    $request = $smcFunc['db_query']('order_by_board_order', '
        SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.id_parent, b.description, b.redirect,
               IFNULL(mem.member_name, m.poster_name) AS poster_name, '.
               ($user_info['is_guest'] ? ' 1 AS is_read' : '(IFNULL(lb.id_msg, 0) >= b.id_msg_updated) AS is_read').'
        FROM {db_prefix}boards AS b
            LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
            LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})').'
        WHERE {query_see_board}',
        array(
            'current_member' => $user_info['id']
        )
    );

    $cats = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        // This category hasn't been set up yet..
        if (!isset($cats[$row['id_cat']]))
            $cats[$row['id_cat']] = array(
                'id'            => 'c' . $row['id_cat'],
                'name'          => $row['cat_name'],
                'description'   => '',
                'id_parent'     => -1,
                'redirect'      => '',
                'new'           => false,
                'children_new'  => false
            );

        // If this board has new posts in it (and isn't the recycle bin!) then the category is new.
        if (empty($modSettings['recycle_enable']) || $modSettings['recycle_board'] != $row['id_board'])
            $cats[$row['id_cat']]['new'] |= empty($row['is_read']) && $row['poster_name'] != '';
        
        // Set this board up
        $cats[$row['id_cat']]['boards'][$row['id_board']] = array(
            'id'           => $row['id_board'],
            'name'         => $row['name'],
            'description'  => $row['description'],
            'id_parent'    => $row['id_parent'],
            'id_cat'       => $row['id_cat'],
            'redirect'     => $row['redirect'],
            'new'          => empty($row['is_read']) && $row['poster_name'] != '',
            'children_new' => false
        );
        
        if ($row['id_parent'])
            $cats[$row['id_cat']]['boards'][$row['id_parent']]['children_new'] |= empty($row['is_read']) && $row['poster_name'] != '';
    }
    $smcFunc['db_free_result']($request);
    
    // Load up the tree
    foreach ($cats as $id_cat => $cat_data)
        foreach ($cat_data['boards'] as $id_board => $board_data)
            if (!empty($board_data['id_parent']))
                $cats[$id_cat]['boards'][$board_data['id_parent']]['boards'][$id_board] = &$cats[$id_cat]['boards'][$id_board];

    // Only add the base item to this array
    foreach ($cats as $id_cat => $cat_data)
        foreach ($cat_data['boards'] as $id_board => $board_data)
            if (!empty($board_data['id_parent']))
                unset($cats[$id_cat]['boards'][$id_board]);
    
    $context['forum_tree'] = build_board($cats, true);
}

function action_get_board_stat()
{
    global $context, $sourcedir;
    
    // Get the user online list.
    require_once($sourcedir . '/Subs-MembersOnline.php');
    $membersOnlineOptions = array(
        'show_hidden' => allowedTo('moderate_forum'),
        'sort' => 'log_time',
        'reverse_sort' => true,
    );
    $context += getMembersOnlineStats($membersOnlineOptions);
}

function build_board($boards, $is_cat = false)
{
    global $settings, $context, $user_info, $smcFunc, $boardurl, $boarddir;
    
    $response = array();    
    foreach ($boards as $id => $board)
    {
        $new_post = false;
        if ($board['new'] || $board['children_new']) {
            $new_post = true;
            $logo_url = $settings['images_url'].'/'.$context['theme_variant_url'].'on'.($board['new'] ? '' : '2').'.png';
        }
        elseif ($board['redirect'])
            $logo_url = $settings['images_url'].'/'.$context['theme_variant_url'].'redirect.png';
        else
            $logo_url = $settings['images_url'].'/'.$context['theme_variant_url'].'off.png';
        
        $logo_dir = str_replace($boardurl, $boarddir, $logo_url);
        if (!file_exists($logo_dir)) {
            $logo_url = preg_replace('/png$/', 'gif', $logo_url);
        }
        
        if (!$is_cat && !$user_info['is_guest'] && allowedTo('mark_notify', $board['id'])) {
            $can_subscribe = true;
            $request = $smcFunc['db_query']('', '
                SELECT sent
                FROM {db_prefix}log_notify
                WHERE id_board = {int:current_board}
                    AND id_member = {int:current_member}
                LIMIT 1',
                array(
                    'current_board' => $board['id'],
                    'current_member' => $user_info['id'],
                )
            );
            $is_subscribed = $smcFunc['db_num_rows']($request) != 0;
            $smcFunc['db_free_result']($request);
        } else {
            $can_subscribe = false;
            $is_subscribed = false;
        }
        
        $xmlrpc_forum = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($board['id'], 'string'),
            'forum_name'    => new xmlrpcval(basic_clean($board['name']), 'base64'),
            'description'   => new xmlrpcval(basic_clean($board['description']), 'base64'),
            'parent_id'     => new xmlrpcval($board['id_parent'] ? $board['id_parent'] : 'c'.$board['id_cat'], 'string'),
            'logo_url'      => new xmlrpcval($logo_url, 'string'),
            'new_post'      => new xmlrpcval($new_post, 'boolean'),
            'url'           => new xmlrpcval($board['redirect'], 'string'),
            'sub_only'      => new xmlrpcval($is_cat, 'boolean'),
            'can_subscribe' => new xmlrpcval($can_subscribe, 'boolean'),
            'is_subscribed' => new xmlrpcval($is_subscribed, 'boolean'),
            'is_protected'  => new xmlrpcval(false, 'boolean'),
        ), 'struct');
        
        if (isset($board['boards']) && !empty($board['boards']))
        {
            $xmlrpc_forum->addStruct(array('child' => new xmlrpcval(build_board($board['boards']), 'array')));
        }
        
        $response[] = $xmlrpc_forum;
    }
    
    return $response;
}

function before_action_get_topic()
{
    global $modSettings, $board, $context, $board_info, $user_info, $smcFunc, $topic_per_page, $mode, $start_num;
    
    $board_info['sticky_num'] = 0;
    $board_info['unread_sticky_num'] = 0;
    if (!empty($modSettings['enableStickyTopics']))
    {
        // Find the number of sticky topic
        $request = $smcFunc['db_query']('', '
            SELECT t.id_topic, ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from, ml.id_msg_modified
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg) ' . ($user_info['is_guest'] ? '' : '
                LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
                LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})'). '
            WHERE t.id_board = {int:current_board} AND t.is_sticky = {int:is_sticky}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
                AND (t.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:current_member}') . ')'),
            array(
                'current_board' => $board,
                'current_member' => $user_info['id'],
                'is_approved'   => 1,
                'is_sticky'     => 1,
            )
        );
        
        while ($row = $smcFunc['db_fetch_assoc']($request))
        {
            if ($row['new_from'] <= $row['id_msg_modified']) $board_info['unread_sticky_num']++;
            $board_info['sticky_num']++;
        }
        $smcFunc['db_free_result']($request);
    }
    
    $context['num_allowed_attachments'] = empty($modSettings['attachmentNumPerPostLimit']) ? 50 : $modSettings['attachmentNumPerPostLimit'];
    $context['can_post_attachment'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments'))) && $context['num_allowed_attachments'] > 0;
    
    if ($mode == 'TOP')
    {
        if ($board_info['sticky_num'] == 0)
        {
            $context['can_post_new'] = allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
            $context['topics'] = array();
            $_REQUEST['action'] = 'get_sticky_topic';
        }
        
        $_REQUEST['start'] = 0;
        $modSettings['defaultMaxTopics'] = $board_info['sticky_num'];
    } else {
        $modSettings['enableStickyTopics'] = 0;
        $_REQUEST['start'] = $start_num;
        $modSettings['defaultMaxTopics'] = $topic_per_page;
    }
}

function action_get_topic(){}

// Callback for the message display.
function get_post_detail($reset = false)
{
    global $settings, $txt, $modSettings, $scripturl, $options, $user_info, $smcFunc;
    global $memberContext, $context, $messages_request, $topic, $attachments, $topicinfo;

    static $counter = null;

    // If the query returned false, bail.
    if ($messages_request == false)
        return false;

    // Remember which message this is.  (ie. reply #83)
    if ($counter === null || $reset)
        $counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'];

    // Start from the beginning...
    if ($reset)
        return @$smcFunc['db_data_seek']($messages_request, 0);

    // Attempt to get the next message.
    $message = $smcFunc['db_fetch_assoc']($messages_request);
    if (!$message)
    {
        $smcFunc['db_free_result']($messages_request);
        return false;
    }

    // $context['icon_sources'] says where each icon should come from - here we set up the ones which will always exist!
    if (empty($context['icon_sources']))
    {
        $stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless', 'clip');
        $context['icon_sources'] = array();
        foreach ($stable_icons as $icon)
            $context['icon_sources'][$icon] = 'images_url';
    }

    // Message Icon Management... check the images exist.
    if (empty($modSettings['messageIconChecks_disable']))
    {
        // If the current icon isn't known, then we need to do something...
        if (!isset($context['icon_sources'][$message['icon']]))
            $context['icon_sources'][$message['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['icon'] . '.gif') ? 'images_url' : 'default_images_url';
    }
    elseif (!isset($context['icon_sources'][$message['icon']]))
        $context['icon_sources'][$message['icon']] = 'images_url';

    // If you're a lazy bum, you probably didn't give a subject...
    $message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

    // Are you allowed to remove at least a single reply?
    $context['can_remove_post'] |= allowedTo('delete_own') && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()) && $message['id_member'] == $user_info['id'];

    // If it couldn't load, or the user was a guest.... someday may be done with a guest table.
    if (!loadMemberContext($message['id_member'], true))
    {
        // Notice this information isn't used anywhere else....
        $memberContext[$message['id_member']]['name'] = $message['poster_name'];
        $memberContext[$message['id_member']]['id'] = 0;
        $memberContext[$message['id_member']]['group'] = $txt['guest_title'];
        $memberContext[$message['id_member']]['link'] = $message['poster_name'];
        $memberContext[$message['id_member']]['email'] = $message['poster_email'];
        $memberContext[$message['id_member']]['show_email'] = showEmailAddress(true, 0);
        $memberContext[$message['id_member']]['is_guest'] = true;
    }
    else
    {
        $memberContext[$message['id_member']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member'] == $user_info['id'] && allowedTo('profile_view_own'));
        $memberContext[$message['id_member']]['is_topic_starter'] = $message['id_member'] == $context['topic_starter_id'];
        $memberContext[$message['id_member']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member']]['warning_status'] && (($context['user']['can_mod'] || !empty($modSettings['warning_show'])) || ($memberContext[$message['id_member']]['id'] == $context['user']['id'] && !empty($modSettings['warning_show']) && $modSettings['warning_show'] == 1));
    }

    $memberContext[$message['id_member']]['ip'] = $message['poster_ip'];
    
    // Do the censor thang.
    censorText($message['body']);
    censorText($message['subject']);
    
    // Run BBC interpreter on the message.
    $message['body'] = mobiquo_parse_bbc($message['body'], 0, $message['id_msg']);
    
    // Compose the memory eat- I mean message array.
    $output = array(
        'smileys_enabled' => $message['smileys_enabled'],
        'attachment' => loadAttachmentContext($message['id_msg']),
        'alternate' => $counter % 2,
        'id' => $message['id_msg'],
        'href' => $scripturl . '?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'],
        'link' => '<a href="' . $scripturl . '?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'] . '" rel="nofollow">' . $message['subject'] . '</a>',
        'member' => &$memberContext[$message['id_member']],
        'icon' => $message['icon'],
        'icon_url' => $settings[$context['icon_sources'][$message['icon']]] . '/post/' . $message['icon'] . '.gif',
        'subject' => $message['subject'],
        'time' => timeformat($message['poster_time']),
        'timestamp' => forum_time(true, $message['poster_time']),
        'counter' => $counter,
        'modified' => array(
            'time' => timeformat($message['modified_time']),
            'timestamp' => forum_time(true, $message['modified_time']),
            'name' => $message['modified_name']
        ),
        'body' => $message['body'],
        'new' => empty($message['is_read']),
        'approved' => $message['approved'],
        'first_new' => isset($context['start_from']) && $context['start_from'] == $counter,
        'is_ignored' => !empty($modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($message['id_member'], $context['user']['ignoreusers']),
        'can_approve' => !$message['approved'] && $context['can_approve'],
        'can_unapprove' => $message['approved'] && $context['can_approve'],
        'can_modify' => (!$context['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $context['user']['started']) || (allowedTo('modify_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || !$message['approved'] || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time()))),
        'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']) || (allowedTo('delete_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
        'can_see_ip' => allowedTo('moderate_forum') || ($message['id_member'] == $user_info['id'] && !empty($user_info['id'])),
    );

    // Is this user the message author?
    $output['is_message_author'] = $message['id_member'] == $user_info['id'];

    if (empty($options['view_newest_first']))
        $counter++;
    else
        $counter--;

    return $output;
}

function action_get_new_topic()
{
    global $context, $settings, $scripturl, $db_prefix, $user_info, $topic_per_page, $start_num;
    global $modSettings, $smcFunc;

    $num_recent = $topic_per_page;
    $exclude_boards = null;
    $include_boards = null;

    if ($exclude_boards === null && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0)
        $exclude_boards = array($modSettings['recycle_board']);
    else
        $exclude_boards = empty($exclude_boards) ? array() : (is_array($exclude_boards) ? $exclude_boards : array($exclude_boards));

    // Only some boards?.
    if (is_array($include_boards) || (int) $include_boards === $include_boards)
    {
        $include_boards = is_array($include_boards) ? $include_boards : array($include_boards);
    }
    elseif ($include_boards != null)
    {
        $output_method = $include_boards;
        $include_boards = array();
    }

    $stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless');
    $icon_sources = array();
    foreach ($stable_icons as $icon)
        $icon_sources[$icon] = 'images_url';

    // Find all the posts in distinct topics.  Newer ones will have higher IDs.
    $request = $smcFunc['db_query']('substring', '
        SELECT
            m.poster_time, SUBSTRING(m.body, 1, 384) AS body, m.smileys_enabled, m.icon, ms.subject, m.id_topic, m.id_member, m.id_msg, b.id_board, b.name AS board_name, t.num_replies, t.num_views, 
            t.id_member_started, t.approved, t.is_sticky, locked, t.id_topic,
            IFNULL(mem.real_name, m.poster_name) AS poster_name, mem.avatar as avatar_last' . ($user_info['is_guest'] ? ', 1 AS is_read, 0 AS new_from' : ', 
            IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
            IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from,
            IFNULL(al.id_attach, 0) AS id_attach_last, al.filename as filename_last, al.attachment_type as attachment_type_last') . '
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . (!$user_info['is_guest'] ? '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
            LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = mem.id_member)' : '') . '
        WHERE t.id_last_msg >= {int:min_message_id}
            ' . (empty($exclude_boards) ? '' : '
            AND b.id_board NOT IN ({array_int:exclude_boards})') . '
            ' . (empty($include_boards) ? '' : '
            AND b.id_board IN ({array_int:include_boards})') . '
            AND {query_wanna_see_board}' . ($modSettings['postmod_active'] ? '
            AND t.approved = {int:is_approved}
            AND m.approved = {int:is_approved}' : '') . '
        ORDER BY t.id_last_msg DESC
        LIMIT {int:offset}, {int:items_per_page}',
        array(
            'current_member' => $user_info['id'],
            'include_boards' => empty($include_boards) ? '' : $include_boards,
            'exclude_boards' => empty($exclude_boards) ? '' : $exclude_boards,
            'min_message_id' => $modSettings['maxMsgID'] - 35 * min($num_recent, 5),
            'is_approved'    => 1,
            'offset'         => $start_num,
            'items_per_page' => $topic_per_page,
        )
    );
    
    $posts = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $row['body'] = preg_replace('/\[quote.*?\].*\[\/quote\]/si', '', $row['body']);
        $row['body'] = preg_replace('/\[img.*?\].*?\[\/img\]/si', '###img###', $row['body']);
        $row['body'] = strip_tags(strtr(mobiquo_parse_bbc($row['body'], false, $row['id_msg']), array('<br />' => ' ')));
        $row['body'] = preg_replace('/###img###/si', '[img]', $row['body']);
        if ($smcFunc['strlen']($row['body']) > 128)
            $row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

        // Censor the subject.
        censorText($row['subject']);
        censorText($row['body']);

        if (empty($modSettings['messageIconChecks_disable']) && !isset($icon_sources[$row['icon']]))
            $icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.gif') ? 'images_url' : 'default_images_url';

        // Check for notifications on this topic
        $req = $smcFunc['db_query']('', '
            SELECT sent, id_topic
            FROM {db_prefix}log_notify
            WHERE id_topic = {int:current_topic} AND id_member = {int:current_member}
            LIMIT 1',
            array(
                'current_member' => $user_info['id'],
                'current_topic' => $row['id_topic'],
            )
        );
        
        $started = $row['id_member_started'] == $user_info['id'];

        // Build the array.
        $posts[] = array(
            'board' => array(
                'id' => $row['id_board'],
                'name' => $row['board_name'],
                'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
                'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>'
            ),
            'topic' => $row['id_topic'],
            'poster' => array(
                'id' => $row['id_member'],
                'name' => $row['poster_name'],
                'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
                'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>',
                'avatar' => $row['avatar_last'] == '' ? ($row['id_attach_last'] > 0 ? (empty($row['attachment_type_last']) ? $scripturl . '?action=dlattach;attach=' . $row['id_attach_last'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['filename_last']) : '') : (stristr($row['avatar_last'], 'http://') ? $row['avatar_last'] : $modSettings['avatar_url'] . '/' . $row['avatar_last']),
            ),
            'subject' => $row['subject'],
            'replies' => $row['num_replies'],
            'views' => $row['num_views'],
            'short_subject' => shorten_subject($row['subject'], 25),
            'preview' => $row['body'],
            'time' => timeformat($row['poster_time']),
            'timestamp' => forum_time(true, $row['poster_time']),
            'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#new',
            'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#new" rel="nofollow">' . $row['subject'] . '</a>',
            // Retained for compatibility - is technically incorrect!
            'new' => !empty($row['is_read']),
            'is_new' => empty($row['is_read']),
            'new_from' => $row['new_from'],
            'icon' => $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.gif" align="middle" alt="' . $row['icon'],
            
            'can_lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
            'can_sticky' => allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']),
            'can_move' => allowedTo('move_any') || ($started && allowedTo('move_own')),
            'can_modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
            'can_remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
            'can_approve' => allowedTo('approve_posts'),
            'can_mark_notify' => allowedTo('mark_notify') && !$user_info['is_guest'],
              
            'is_sticky' => !empty($modSettings['enableStickyTopics']) && !empty($row['is_sticky']),
            'is_locked' => !empty($row['locked']),
            'is_approved' => !empty($row['approved']),
            'is_marked_notify' => $smcFunc['db_num_rows']($req) ? true : false,
            //'new' => $topic['new_from'] <= $topic['id_msg_modified'],
        );
        $smcFunc['db_free_result']($req);
    }
    $smcFunc['db_free_result']($request);
    
    $context['posts'] = $posts;
}

function action_get_subscribed_forum()
{
    global $context, $smcFunc, $scripturl, $user_info, $settings;

    $request = $smcFunc['db_query']('', '
        SELECT b.id_board, b.name, IFNULL(lb.id_msg, 0) AS board_read, b.id_msg_updated, b.redirect
        FROM {db_prefix}log_notify AS ln
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
            LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = b.id_board AND lb.id_member = {int:current_member})
        WHERE ln.id_member = {int:current_member}
            AND {query_see_board}
        ORDER BY b.board_order',
        array(
            'current_member' => $user_info['id'],
        )
    );
    $notification_boards = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        if ($row['board_read'] < $row['id_msg_updated'])
            $logo_url = $settings['images_url'].'/'.$context['theme_variant_url'].'on.png';
        elseif ($row['redirect'])
            $logo_url = $settings['images_url'].'/'.$context['theme_variant_url'].'redirect.png';
        else
            $logo_url = $settings['images_url'].'/'.$context['theme_variant_url'].'off.png';
        
        $notification_boards[] = array(
            'id' => $row['id_board'],
            'name' => $row['name'],
            'logo' => $logo_url,
            'new' => $row['board_read'] < $row['id_msg_updated']
        );
    }
    $smcFunc['db_free_result']($request);

    $context['boards'] = $notification_boards;
}

function action_get_subscribed_topic()
{
    global $smcFunc, $scripturl, $user_info, $context, $modSettings;

    // All the topics with notification on...
    $request = $smcFunc['db_query']('', '
        SELECT t.id_topic, b.id_board, b.name AS board_name
        FROM {db_prefix}log_notify AS ln
            INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic' . ($modSettings['postmod_active'] ? ' AND t.approved = {int:is_approved}' : '') . ')
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
        WHERE ln.id_member = {int:current_member}
        ORDER BY t.id_last_msg DESC
        LIMIT {int:offset}, {int:items_per_page}',
        array(
            'current_member' => $user_info['id'],
            'is_approved' => 1,
            'offset' => 0,
            'items_per_page' => 20,
        )
    );
    
    $notification_topics = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $notification_topics[] = $row;
    }
    $smcFunc['db_free_result']($request);

    $context['topics'] = $notification_topics;
    
    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*)
        FROM {db_prefix}log_notify AS ln' . (!$modSettings['postmod_active'] && $user_info['query_see_board'] === '1=1' ? '' : '
            INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)') . ($user_info['query_see_board'] === '1=1' ? '' : '
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)') . '
        WHERE ln.id_member = {int:selected_member}' . ($user_info['query_see_board'] === '1=1' ? '' : '
            AND {query_see_board}') . ($modSettings['postmod_active'] ? '
            AND t.approved = {int:is_approved}' : ''),
        array(
            'selected_member' => $user_info['id'],
            'is_approved' => 1,
        )
    );
    list ($totalNotifications) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    
    $context['topic_num'] = $totalNotifications;
}

function action_get_participated_topic()
{
    global $smcFunc, $scripturl, $user_info, $context, $modSettings, $topic_per_page, $start_num, $search_user;
    
    $searchz_user_id = $user_info['id'];
    
    if ($search_user)
    {
        $memberResult = loadMemberData($search_user, true, 'profile');
        if (!is_array($memberResult))
            fatal_lang_error('not_a_user', false);
            
        list ($searchz_user_id) = $memberResult;
    }

    // All the topics with notification on...
    $request = $smcFunc['db_query']('', '
        SELECT DISTINCT(m.id_topic), b.id_board, b.name AS board_name
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
        WHERE m.id_member = {int:current_member}
        ORDER BY m.id_topic DESC
        LIMIT {int:offset}, {int:items_per_page}',
        array(
            'current_member' => $searchz_user_id,
            'offset' => $start_num,
            'items_per_page' => $topic_per_page,
        )
    );
    
    $participated_topics = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $participated_topics[] = $row;
    }
    $smcFunc['db_free_result']($request);

    $context['topics'] = $participated_topics;
    
    $request = $smcFunc['db_query']('', '
        SELECT COUNT(DISTINCT m.id_topic)
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
        WHERE m.id_member = {int:current_member}',
        array(
            'current_member' => $searchz_user_id,
        )
    );
    
    list ($totalParticipated) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    
    $context['topic_num'] = $totalParticipated;
}

function set_topic_and_board_by_message()
{
    global $smcFunc;
    
    $id_msg = isset($_GET['msg']) && $_GET['msg'] ? $_GET['msg'] : (isset($_GET['quote']) ? $_GET['quote'] : '');
    
    if(empty($id_msg)) return;
    
    $request = $smcFunc['db_query']('', '
        SELECT id_topic, id_board
        FROM {db_prefix}messages
        WHERE id_msg = {int:message_id}',
        array(
            'message_id' => $id_msg,
        )
    );
    
    list ($topic_id, $board_id) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    
    $GLOBALS['topic'] = $topic_id;
    $GLOBALS['board'] = $board_id;
}

function action_get_inbox_stat(){}

function action_get_box_info()
{
    global $txt, $context, $user_info, $smcFunc;
    
    // No guests!
    is_not_guest();

    // You're not supposed to be here at all, if you can't even read PMs.
    isAllowedTo('pm_read');
    
    loadLanguage('PersonalMessage');
    
    // Load up the members maximum message capacity.
    if ($user_info['is_admin'])
        $context['message_limit'] = 360;
    elseif (($context['message_limit'] = cache_get_data('msgLimit:' . $user_info['id'], 360)) === null)
    {
        // !!! Why do we do this?  It seems like if they have any limit we should use it.
        $request = $smcFunc['db_query']('', '
            SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
            FROM {db_prefix}membergroups
            WHERE id_group IN ({array_int:users_groups})',
            array(
                'users_groups' => $user_info['groups'],
            )
        );
        list ($maxMessage, $minMessage) = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);
        
        $context['message_limit'] = $minMessage == 0 ? 360 : $maxMessage;
        
        // Save us doing it again!
        cache_put_data('msgLimit:' . $user_info['id'], $context['message_limit'], 360);
    }
    
    $context['message_remain'] = $context['message_limit'] - $user_info['messages'];
    
    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*)
        FROM {db_prefix}personal_messages AS pm
        WHERE pm.id_member_from = {int:current_member}
            AND pm.deleted_by_sender = {int:not_deleted}',
        array(
            'current_member' => $user_info['id'],
            'not_deleted' => 0,
        )
    );

    list ($sent_messages) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    

    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*)
        FROM {db_prefix}pm_recipients AS pmr
        WHERE pmr.id_member = {int:current_member}
            AND pmr.deleted = {int:not_deleted}',
        array(
            'current_member' => $user_info['id'],
            'not_deleted' => 0,
        )
    );
    
    list ($inbox_messages) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    
    $context['boxes']['inbox'] = array(
        'id' => 'inbox',
        'name' => $txt['inbox'],
        'msg_count' => $inbox_messages,
        'unread_count' => $user_info['unread_messages'],
        'box_type' => 'INBOX',
    );
    
    if (allowedTo('pm_send')) {
        $context['boxes']['sent'] = array(
            'id' => 'sent',
            'name' => $txt['sent_items'],
            'msg_count' => $sent_messages,
            'unread_count' => 0,
            'box_type' => 'SENT',
        );
    }
}

function after_action_get_box()
{
    global $txt, $context, $user_info, $smcFunc, $box_id;
    
    // Figure out how many messages there are.
    if ($box_id == 'sent')
    {
        $request = $smcFunc['db_query']('', '
            SELECT COUNT(*)
            FROM {db_prefix}personal_messages AS pm
            WHERE pm.id_member_from = {int:current_member}
                AND pm.deleted_by_sender = {int:not_deleted}',
            array(
                'current_member' => $user_info['id'],
                'not_deleted' => 0,
            )
        );
        list ($send_messages) = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);
        
        $context['messages_count'] = $send_messages;
    } else {
        $context['messages_count'] = $user_info['messages'];
    }
    
    $unread_messages = 0;
    $context['messages'] = array();
    while ($message = get_pm_list('subject'))
    {
        $context['messages'][] = $message;
        if($message['is_unread']) $unread_messages++;
    }
    $context['unread_messages'] = $unread_messages;
}

// Get a personal message for the theme.  (used to save memory.)
function get_pm_list($type = 'subject', $reset = false)
{
    global $txt, $user_profile, $scripturl, $modSettings, $context, $messages_request, $memberContext, $recipients, $smcFunc;
    global $user_info, $subjects_request;

    // Count the current message number....
    static $counter = null;
    if ($counter === null || $reset)
        $counter = $context['start'];

    static $temp_pm_selected = null;
    if ($temp_pm_selected === null)
    {
        $temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
        $_SESSION['pm_selected'] = array();
    }

    // Bail if it's false, ie. no messages.
    if ($messages_request == false)
        return false;

    // Reset the data?
    if ($reset == true)
        return @$smcFunc['db_data_seek']($messages_request, 0);

    // Get the next one... bail if anything goes wrong.
    $message = $smcFunc['db_fetch_assoc']($messages_request);
    if (!$message)
    {
        if ($type != 'subject')
            $smcFunc['db_free_result']($messages_request);

        return false;
    }

    // Use '(no subject)' if none was specified.
    $message['subject'] = $message['subject'] == '' ? $txt['no_subject'] : $message['subject'];
    
    // add for tapatalk
    if ($context['folder'] == 'sent') {
        $id_member = preg_replace('/^.*?u=(\d+).*?$/s', '$1', $recipients[$message['id_pm']]['to'][0]);
        if (!isset($user_profile[$id_member]))
            loadMemberData($id_member);
    } else 
        $id_member = $message['id_member_from'];
            

    // Load the message's information - if it's not there, load the guest information.
    if (!loadMemberContext($id_member, true))
    {
        $memberContext[$id_member]['name'] = $message['from_name'];
        $memberContext[$id_member]['id'] = 0;
        // Sometimes the forum sends messages itself (Warnings are an example) - in this case don't label it from a guest.
        $memberContext[$id_member]['group'] = $message['from_name'] == $context['forum_name'] ? '' : $txt['guest_title'];
        $memberContext[$id_member]['link'] = $message['from_name'];
        $memberContext[$id_member]['email'] = '';
        $memberContext[$id_member]['show_email'] = showEmailAddress(true, 0);
        $memberContext[$id_member]['is_guest'] = true;
    }
    else
    {
        $memberContext[$id_member]['can_view_profile'] = allowedTo('profile_view_any') || ($id_member == $user_info['id'] && allowedTo('profile_view_own'));
        $memberContext[$id_member]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$id_member]['warning_status'] && (($context['user']['can_mod'] || !empty($modSettings['warning_show'])) || ($memberContext[$id_member]['id'] == $context['user']['id'] && !empty($modSettings['warning_show']) && $modSettings['warning_show'] == 1));
    }

    // Censor all the important text...
    censorText($message['body']);
    censorText($message['subject']);

    // Run UBBC interpreter on the message.
    $message['body'] = mobiquo_parse_bbc($message['body'], false, 'pm' . $message['id_pm']);

    // Send the array.
    $output = array(
        'alternate' => $counter % 2,
        'id' => $message['id_pm'],
        'member' => &$memberContext[$id_member],
        'subject' => $message['subject'],
        'time' => timeformat($message['msgtime']),
        'timestamp' => forum_time(true, $message['msgtime']),
        'counter' => $counter,
        'body' => $message['body'],
        'recipients' => &$recipients[$message['id_pm']],
        'number_recipients' => count($recipients[$message['id_pm']]['to']),
        'labels' => &$context['message_labels'][$message['id_pm']],
        'fully_labeled' => count($context['message_labels'][$message['id_pm']]) == count($context['labels']),
        'is_replied_to' => &$context['message_replied'][$message['id_pm']],
        'is_unread' => &$context['message_unread'][$message['id_pm']],
        'is_selected' => !empty($temp_pm_selected) && in_array($message['id_pm'], $temp_pm_selected),
        'msg_from' => $context['folder'] == 'sent' ? $context['user']['username'] : $memberContext[$id_member]['username'],
    );

    $counter++;

    return $output;
}

// Loads a single PM
function action_get_message()
{
    global $context, $smcFunc, $modSettings, $scripturl, $sourcedir, $user_info, $msg_id, $box_id, $user_profile, $memberContext;
    
    require_once('include/PersonalMessage.php');
    
    // No guests!
    is_not_guest();

    // You're not supposed to be here at all, if you can't even read PMs.
    isAllowedTo('pm_read');

    loadLanguage('PersonalMessage');
    
    $request = $smcFunc['db_query']('', '
        SELECT pm.id_member_from, pm.msgtime, pm.subject, pm.body, m.member_name, m.real_name
        FROM {db_prefix}personal_messages AS pm
            LEFT JOIN {db_prefix}members AS m ON (pm.id_member_from = m.id_member)
        WHERE pm.id_pm = {int:id_message} ' . ($box_id == 'sent' ? 'AND pm.id_member_from = {int:current_member} AND pm.deleted_by_sender = 0' : ''),
        array(
            'id_message' => $msg_id,
            'current_member' => $user_info['id'],
        )
    );
    $pm = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    
    if (empty($pm))
        fatal_lang_error('no_access', false);
        
    censorText($pm['subject']);
    censorText($pm['body']);
    
    $context['pm'] = array(
        'username' => $pm['member_name'],
        'name' => $pm['real_name'],
        'time' => timeformat($pm['msgtime']),
        'subject' => $pm['subject'],
        'body' => mobiquo_parse_bbc($pm['body'], false, 'pm' . $msg_id),
        'recipients' => array(),
    );
    
    $request = $smcFunc['db_query']('', '
        SELECT pmr.id_member, m.member_name, m.real_name
        FROM {db_prefix}pm_recipients AS pmr
            LEFT JOIN {db_prefix}members AS m ON (pmr.id_member = m.id_member)
        WHERE pmr.id_pm = {int:id_message} ' . ($box_id == 'inbox' ? 'AND ((pmr.id_member = {int:current_member} AND pmr.deleted = 0) OR (pmr.id_member != {int:current_member} AND pmr.bcc = 0))' : ''),
        array(
            'id_message' => $msg_id,
            'current_member' => $user_info['id'],
        )
    );
    
    $no_member = true;
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $context['pm']['recipients'][] = new xmlrpcval(array(
            'username' => new xmlrpcval(basic_clean($row['member_name']), 'base64'),
            'display_name' => new xmlrpcval(basic_clean($row['real_name']), 'base64'),
        ), 'struct');
        
        if ($no_member)
        {
            $display_member_id = ($box_id == 'inbox' ? $pm['id_member_from'] : $row['id_member']);
            $no_member = false;
        }
    }
    $smcFunc['db_free_result']($request);
    
    loadMemberData($display_member_id);
    loadMemberContext($display_member_id);
    $context['pm']['member'] = $memberContext[$display_member_id];
    
    if ($no_avatar)
        fatal_lang_error('no_access', false);
        
    // Mark this as read, if it is not already
    markMessages(array($msg_id));
}

function action_attach_image()
{
    global $image, $modSettings, $sourcedir, $context, $user_info;
    
    require_once('include/Subs-Post.php');
    
    if (isset($_FILES['attachment']['name']))
    {
        // Verify they can post them!
        if (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_attachments'))
            isAllowedTo('post_attachment');
        
        // Make sure we're uploading to the right place.
        if (!empty($modSettings['currentAttachmentUploadDir']))
        {
            if (!is_array($modSettings['attachmentUploadDir']))
                $modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

            // The current directory, of course!
            $current_attach_dir = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
        }
        else
            $current_attach_dir = $modSettings['attachmentUploadDir'];
        
        // prepare for attach image
        $tmp_name = 'post_tmp_' . $user_info['id'] . '_' . rand(1, 1000);
        $destination = $current_attach_dir . '/' . $tmp_name;
        $fp = fopen($destination, 'w');
        $size = @filesize($destination);
        fwrite($fp, $image);
        fclose($fp);
        
        $_FILES['attachment']['tmp_name'][] = $tmp_name;
        $_FILES['attachment']['size'][] = $size ? $size : strlen($image);
        
        $quantity = 0;
        $total_size = 0;

        if (!isset($_FILES['attachment']['name']))
            $_FILES['attachment']['tmp_name'] = array();

        $attachIDs = array();
        foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
        {
            if ($_FILES['attachment']['name'][$n] == '')
                continue;

            // Have we reached the maximum number of files we are allowed?
            $quantity++;
            if (!empty($modSettings['attachmentNumPerPostLimit']) && $quantity > $modSettings['attachmentNumPerPostLimit'])
            {
                checkSubmitOnce('free');
                fatal_lang_error('attachments_limit_per_post', false, array($modSettings['attachmentNumPerPostLimit']));
            }

            // Check the total upload size for this post...
            $total_size += $_FILES['attachment']['size'][$n];
            if (!empty($modSettings['attachmentPostLimit']) && $total_size > $modSettings['attachmentPostLimit'] * 1024)
            {
                checkSubmitOnce('free');
                fatal_lang_error('file_too_big', false, array($modSettings['attachmentPostLimit']));
            }

            $attachmentOptions = array(
                'post' => 0,
                'poster' => $user_info['id'],
                'name' => $_FILES['attachment']['name'][$n],
                'tmp_name' => $_FILES['attachment']['tmp_name'][$n],
                'size' => $_FILES['attachment']['size'][$n],
                'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
            );
            
            if (createAttachment($attachmentOptions))
            {
                $attachIDs[] = $attachmentOptions['id'];
                if (!empty($attachmentOptions['thumb']))
                    $attachIDs[] = $attachmentOptions['thumb'];
            }
            else
            {
                if (in_array('could_not_upload', $attachmentOptions['errors']))
                {
                    checkSubmitOnce('free');
                    fatal_lang_error('attach_timeout', 'critical');
                }
                if (in_array('too_large', $attachmentOptions['errors']))
                {
                    checkSubmitOnce('free');
                    fatal_lang_error('file_too_big', false, array($modSettings['attachmentSizeLimit']));
                }
                if (in_array('bad_extension', $attachmentOptions['errors']))
                {
                    checkSubmitOnce('free');
                    fatal_error($attachmentOptions['name'] . ".\n" . $txt['cant_upload_type'] . ' ' . $modSettings['attachmentExtensions'] . '.', false);
                }
                if (in_array('directory_full', $attachmentOptions['errors']))
                {
                    checkSubmitOnce('free');
                    fatal_lang_error('ran_out_of_space', 'critical');
                }
                if (in_array('bad_filename', $attachmentOptions['errors']))
                {
                    checkSubmitOnce('free');
                    fatal_error(basename($attachmentOptions['name']) . ".\n" . $txt['restricted_filename'] . '.', 'critical');
                }
                if (in_array('taken_filename', $attachmentOptions['errors']))
                {
                    checkSubmitOnce('free');
                    fatal_lang_error('filename_exists');
                }
            }
        }
        $context['attachids'] = $attachIDs;
    }
}

function before_action_create_message()
{
    global $txt, $smcFunc;
    
    // Figure out how many messages there are.
    foreach ($_POST['recipient_to'] as  $index => $name)
    {
        $request = $smcFunc['db_query']('', '
            SELECT id_member
            FROM {db_prefix}members
            WHERE member_name = {string:current_member}',
            array(
                'current_member' => $name,
            )
        );
        list ($id_member) = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);
        
        if ($id_member) 
            $_POST['recipient_to'][$index] = $id_member;
        else
            fatal_lang_error('error_bad_to');
    }
}

function before_action_reply_topic()
{
    check_topic_notify();
}

function before_action_save_raw_post()
{
    check_topic_notify();
}

function check_topic_notify()
{
    global $smcFunc, $topic, $user_info;
    
    if (!$topic || !isset($user_info['id'])) return;
    
    $request = $smcFunc['db_query']('', '
        SELECT IFNULL(id_topic, 0) AS notify
        FROM {db_prefix}log_notify
        WHERE id_topic = {int:current_topic} and id_member = {int:current_member}
        LIMIT 1',
        array(
            'current_member' => $user_info['id'],
            'current_topic' => $topic,
        )
    );
    list ($notify) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);
    
    $_POST['notify'] = !empty($notify);
}

function before_action_authorize_user()
{
    global $smcFunc, $request_params, $sc;
    
    $_POST['hash_passwrd'] = sha1(sha1(($smcFunc['db_case_sensitive'] ? $_REQUEST['user'] : strtolower($_REQUEST['user'])).$request_params[1]).$sc);
    $_REQUEST['hash_passwrd'] = $_POST['hash_passwrd'];
}

function before_action_login()
{
    global $smcFunc, $request_params, $sc;
    
    $_POST['hash_passwrd'] = sha1(sha1(($smcFunc['db_case_sensitive'] ? $_REQUEST['user'] : strtolower($_REQUEST['user'])).$request_params[1]).$sc);
    $_REQUEST['hash_passwrd'] = $_POST['hash_passwrd'];
}

function after_action_get_topic()
{
    global $context, $smcFunc, $user_info, $subscribed_tids;
    
    $subscribed_tids = array();
    $topic_ids = array_keys($context['topics']);
    if (!empty($topic_ids))
    {
        $request = $smcFunc['db_query']('', '
            SELECT id_topic
            FROM {db_prefix}log_notify
            WHERE id_topic IN ({array_int:topic_list})
                AND id_member = {int:current_member}',
            array(
                'current_member' => $user_info['id'],
                'topic_list' => $topic_ids,
            )
        );
        
        while ($row = $smcFunc['db_fetch_assoc']($request))
            $subscribed_tids[] = $row['id_topic'];
    }
}