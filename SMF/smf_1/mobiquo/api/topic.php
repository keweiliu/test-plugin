<?php
/**
 * Topic API functions
 *
 * @author Shitiz Garg
 * @copyright Copyright 2009 Quoord Systems Ltd. All Rights Reserved.
 * @license This file or any content of the file should not be
 *             redistributed in any form of matter. This file is a part of
 *             Tapatalk package and should not be used and distributed
 *             in any form not approved by Quoord Systems Ltd.
 *             http://tapatalk.com | http://taptatalk.com/license.html
 */

if (!defined('IN_MOBIQUO'))
	die('Hacking Attempt...');

function mob_get_topic($rpcmsg)
{
    global $mobdb, $mobsettings, $modSettings, $context, $scripturl, $user_info, $board;

    $id_board = $board = (int) $rpcmsg->getScalarValParam(0);

    loadBoard();
    loadPermissions();

    // Load the start and end
    $start = $rpcmsg->getScalarValParam(1);
    $end = $rpcmsg->getScalarValParam(2);
    $count = $end - $start > 50 ? 50 : $end - $start + 1;
    
    if($rpcmsg->getParam(3) && $rpcmsg->getScalarValParam(3) == 'ANN')
        mob_error('No announcement');

    $sticky = false;
    // Are we requesting sticky topics only?
    if ($rpcmsg->getParam(3) && $rpcmsg->getScalarValParam(3) == 'TOP')
        $sticky = true;

    // Can you access this board?
    $mobdb->query('
        SELECT b.ID_BOARD AS id_board, b.name AS board_name
        FROM {db_prefix}boards AS b
        WHERE {query_see_board}
            AND b.ID_BOARD = {int:board}',
        array(
            'board' => $id_board,
        )
    );
    if ($mobdb->num_rows() == 0)
        mob_error('invalid board');
    $board_info = $mobdb->fetch_assoc();
    $mobdb->free_result();

    $board_info['can_post_new'] = allowedTo('post_new');

    // Get unread sticky topics num
    $board_info['unread_sticky_count'] = 0;
//    if (!$user_info['is_guest'])
//    {
//        $mobdb->query('
//            SELECT COUNT(*), IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1 AS new_from, lm.Id_MSG_MODIFIED as id_msg_modified
//            FROM {db_prefix}topics AS t
//                LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
//                LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
//                LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = {int:board} AND lmr.ID_MEMBER = {int:current_member})
//            WHERE t.ID_BOARD = {int:board}
//                AND t.isSticky = 1
//            HAVING new_from <= id_msg_modified',
//            array(
//                'current_member' => $user_info['id'],
//                'board' => $id_board,
//            )
//        );
//        list ($board_info['unread_sticky_count']) = $mobdb->fetch_row();
//        $mobdb->free_result();
//    }

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
    return new xmlrpcresp(new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($board_info['total_topic_num'], 'int'),
        'forum_id' => new xmlrpcval($board_info['id_board'], 'string'),
        'forum_name' => new xmlrpcval(processSubject($board_info['board_name']), 'base64'),
        'can_post' => new xmlrpcval($board_info['can_post_new'], 'boolean'),
        'unread_sticky_count' => new xmlrpcval($board_info['unread_sticky_count'], 'int'),
        'topics' => new xmlrpcval(get_topics('t.ID_BOARD = {int:board} AND t.isSticky = ' . ($sticky ? 1 : 0), array('board' => $id_board), $start, $count, true), 'array'),
    ), 'struct'));
}

// Returns the new topics
function mob_get_latest_topic($rpcmsg)
{
    global $mobdb, $scripturl, $user_info, $modSettings;

    $start = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : 0;
    $end = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : $start + 9;
    $count = $end - $start > 50 ? 50 : $end - $start + 1;
    
    $total_topic_num = $modSettings['totalTopics'] < 100 ? $modSettings['totalTopics'] : 100;
    
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'total_topic_num' => new xmlrpcval($total_topic_num, 'int'),
        'topics' => new xmlrpcval(get_topics('', array(), $start, $count, false), 'array'),
    ), 'struct'));
}

// Returns the unread topics
function mob_get_unread_topic($rpcmsg)
{
    global $mobdb, $scripturl, $user_info, $modSettings, $sourcedir, $context;

    if ($user_ino['is_guest'])
        mob_error('guests not allowed');

    $start = $rpcmsg->getParam(0) ? $rpcmsg->getScalarValParam(0) : 0;
    $end = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : $start + 20;
    $count = $end - $start > 50 ? 50 : $end - $start;

    require_once($sourcedir . '/Recent.php');
    $_REQUEST['action'] = 'unread';
    $_REQUEST['all'] = true;
    $_REQUEST['start'] = $start;
    $modSettings['defaultMaxTopics'] = $count;
    UnreadTopics();

    $stids = get_subscribed_tids();
    $uids = array();
    if (!empty($context['topics']))
        foreach($context['topics'] as $tid => $topic)
        {
            $context['topics'][$tid]['is_notify'] = in_array($tid, $stids);
            $uids[] = $topic['last_post']['member']['id'];
        }

    if (!empty($uids))
    {
        $avatars = get_avatar_by_ids($uids);
        foreach($context['topics'] as $tid => $topic)
        {
            $context['topics'][$tid]['new'] = true;
            $context['topics'][$tid]['last_post']['member']['avatar']['href'] = $avatars[$topic['last_post']['member']['id']];
        }
    }

    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'total_topic_num' => new xmlrpcval($context['num_topics'], 'int'),
        'topics' => new xmlrpcval(get_topics_xmlrpc($context['topics'], false), 'array'),
    ), 'struct'));
}

function mob_get_participated_topic($rpcmsg)
{
    global $mobdb, $scripturl, $user_info, $settings, $modSettings, $sourcedir;

    require_once($sourcedir . '/Subs-Auth.php');

    // Load the parameters, username must always be there
    $username = $rpcmsg->getScalarValParam(0);
    $start = $rpcmsg->getParam(1) ? $rpcmsg->getScalarValParam(1) : 0;
    $end = $rpcmsg->getParam(2) ? $rpcmsg->getScalarValParam(2) : $start + 9;
    $id_user = $rpcmsg->getParam(3) ? (int) $rpcmsg->getScalarValParam(3) : null;
    $count = $end - $start + 1;

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

    // Get the topic's count
    $request = $mobdb->query('
        SELECT COUNT(*)
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (m.ID_TOPIC = t.ID_TOPIC)
        WHERE m.ID_MEMBER = {int:member}
        GROUP BY m.ID_TOPIC',
        array(
            'member' => $id_user,
        )
    );
    list ($topic_count) = $mobdb->fetch_row($request);
    $mobdb->free_result($request);

    // Get the topics themselves
    $request = $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic
        FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (m.ID_TOPIC = t.ID_TOPIC)
            INNER JOIN {db_prefix}boards AS b ON (b.ID_BOARD = m.ID_BOARD)
            INNER JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
        WHERE m.ID_MEMBER = {int:member}
            AND {query_see_board}
        GROUP BY m.ID_TOPIC
        ORDER BY lm.posterTime DESC
        LIMIT {int:start}, {int:limit}',
        array(
            'member' => $id_user,
            'start' => $start,
            'limit' => $count,
        )
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc($request))
        $topics[] = $row['id_topic'];
    $mobdb->free_result($request);

    // Return the topics
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
        'total_topic_num' => new xmlrpcval($topic_count, 'int'),
        'topics' => new xmlrpcval(!empty($topics) ? get_topics('t.ID_TOPIC IN ({array_int:topics})', array('topics' => $topics), $start, $count, false) : array(), 'array'),
    ), 'struct'));
}

// Internal function to return topic responses, makes our work easy
function get_topics($where = '', $where_params = array(), $start = 0, $count = 20, $use_first = true)
{
    global $mobdb, $scripturl, $settings, $user_info, $modSettings;

    $mobdb->query("SET OPTION SQL_BIG_SELECTS=1");
    // Perform the query to fetch the topics
    $mobdb->query('
        SELECT t.ID_TOPIC AS id_topic, t.numReplies AS num_replies, t.locked, t.numViews AS num_views, t.isSticky AS is_sticky, t.ID_POLL AS id_poll,
                ' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.ID_MSG, IFNULL(lmr.ID_MSG, -1)) + 1') . ' AS new_from,
                ' . ($user_info['is_guest'] ? '0' : 'IFNULL(ln.ID_TOPIC, 0)') . ' AS is_notify,
                b.ID_BOARD AS id_board, b.name AS board_name,
                t.ID_LAST_MSG AS id_last_msg, lm.posterTime AS last_poster_time, lm.ID_MSG_MODIFIED AS id_msg_modified,
                lm.subject AS last_subject, lm.icon AS last_icon, lm.posterName AS last_member_name,
                lm.ID_MEMBER AS last_id_member, IFNULL(ml.realName, lm.posterName) AS last_display_name,
                t.ID_FIRST_MSG AS id_first_msg, fm.posterTime AS first_poster_time,
                fm.subject AS first_subject, fm.icon AS first_icon, fm.posterName AS first_member_name,
                fm.ID_MEMBER AS first_id_member, IFNULL(mf.realName, fm.posterName) AS first_display_name,
                lm.body AS last_body, fm.body AS first_body,
                IFNULL(af.ID_ATTACH, 0) AS first_id_attach, af.filename AS first_filename, af.attachmentType AS first_attachment_type, mf.avatar AS first_avatar,
                IFNULL(al.ID_ATTACH, 0) AS last_id_attach, al.filename AS last_filename, al.attachmentType AS last_attachment_type, ml.avatar AS last_avatar
        FROM {db_prefix}topics AS t
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            LEFT JOIN {db_prefix}messages AS fm ON (t.ID_FIRST_MSG = fm.ID_MSG)
            LEFT JOIN {db_prefix}messages AS lm ON (t.ID_LAST_MSG = lm.ID_MSG)
            LEFT JOIN {db_prefix}members AS mf ON (fm.ID_MEMBER = mf.ID_MEMBER)
            LEFT JOIN {db_prefix}members AS ml ON (lm.ID_MEMBER = ml.ID_MEMBER)' . ($user_info['is_guest'] ? '' : '
            LEFT JOIN {db_prefix}log_notify AS ln ON (ln.ID_TOPIC = t.ID_TOPIC AND ln.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.ID_TOPIC = t.ID_TOPIC AND lt.ID_MEMBER = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.ID_BOARD = t.ID_BOARD AND lmr.ID_MEMBER = {int:current_member})') . '
            LEFT JOIN {db_prefix}attachments AS af ON (af.ID_MEMBER = mf.ID_MEMBER)
            LEFT JOIN {db_prefix}attachments AS al ON (al.ID_MEMBER = ml.ID_MEMBER)
        WHERE {query_see_board} ' . (!empty($where) ? ' AND ' . $where : '') . '
        ORDER BY IFNULL(lm.posterTime, fm.posterTime) DESC
        LIMIT {int:start}, {int:count}',
        array_merge(array(
            'current_member' => $user_info['id'],
            'start' => $start,
            'count' => $count,
        ), $where_params)
    );
    $topics = array();
    while ($row = $mobdb->fetch_assoc())
    {
        $first_avatar =  $row['first_avatar'] == '' ? ($row['first_id_attach'] > 0 ? (empty($row['first_attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['first_id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['first_filename']) : '') : (stristr($row['first_avatar'], 'http://') ? $row['first_avatar'] : $modSettings['avatar_url'] . '/' . $row['first_avatar']);
        $last_avatar =  $row['last_avatar'] == '' ? ($row['last_id_attach'] > 0 ? (empty($row['last_attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $row['last_id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $row['last_filename']) : '') : (stristr($row['last_avatar'], 'http://') ? $row['last_avatar'] : $modSettings['avatar_url'] . '/' . $row['last_avatar']);

        $topics[] = array(
            'id' => $row['id_topic'],
            'first_post' => array(
                'id' => $row['id_first_msg'],
                'member' => array(
                    'id' => $row['first_id_member'],
                    'name' => $row['first_display_name'],
                    'avatar' => array(
                        'href' => $first_avatar,
                    ),
                ),
                'time' => $row['first_poster_time'],
                'subject' => $row['first_subject'],
                'body' => $row['first_body'],
            ),
            'last_post' => array(
                'id' => $row['id_last_msg'],
                'member' => array(
                    'id' => $row['last_id_member'],
                    'name' => $row['last_display_name'],
                    'avatar' => array(
                        'href' => $last_avatar,
                    ),
                ),
                'time' => $row['last_poster_time'],
                'subject' => $row['last_subject'],
                'body' => $row['last_body'],
            ),
            'board' => array(
                'id' => $row['id_board'],
                'name' => $row['board_name'],
            ),
            'new' => $row['new_from'] <= $row['id_msg_modified'],
            'new_from' => $row['new_from'],
            'is_sticky' => $row['is_sticky'],
            'is_locked' => $row['locked'],
            'is_notify' => $row['is_notify'],
            'subject' => $row['first_subject'],
            'replies' => $row['num_replies'],
            'views' => $row['num_views'],
        );
    }
    $mobdb->free_result();

    return get_topics_xmlrpc($topics, $use_first);
}

// Returns the topics in a XMLRPC formatted array
function get_topics_xmlrpc($_topics, $use_first = true)
{
    global $user_info;

    $topics = array();
    $permission = array();
    $perms = array('mark_notify', 'remove_any', 'remove_own', 'lock_any', 'lock_own', 'make_sticky', 'move_any', 'move_own', 'modify_any', 'modify_own', 'manage_bans');
    foreach ($_topics as $topic)
    {
        $started = !$user_info['is_guest'] && $user_info['id'] == $topic['first_post']['member']['id'];
        if ($use_first)
            $message = isset($topic['first_post']['preview']) ? $topic['first_post']['preview'] : $topic['first_post']['body'];
        else
            $message = isset($topic['last_post']['preview']) ? $topic['last_post']['preview'] : $topic['last_post']['body'];
        
        
        if ($use_first)
        {
            if (!is_numeric($topic['first_post']['time']) && isset($topic['first_post']['timestamp']))
                $post_time = mobiquo_time($topic['first_post']['timestamp'], true);
            else
                $post_time = mobiquo_time($topic['first_post']['time']);
        }
        else
        {
            if (!is_numeric($topic['last_post']['time']) && isset($topic['last_post']['timestamp']))
                $post_time = mobiquo_time($topic['last_post']['timestamp'], true);
            else
                $post_time = mobiquo_time($topic['last_post']['time']);
        }
        
        $fid = $topic['board']['id'];
        foreach ($perms as $perm)
            if (!isset($permission[$fid][$perm])) $permission[$fid][$perm] = allowedTo($perm, $fid);
        
        // Add stuff to the array
        $topics[] = new xmlrpcval(array(
            'topic_id'          => new xmlrpcval($topic['id'], 'string'),
            'topic_title'       => new xmlrpcval(processSubject($topic['subject']), 'base64'),
            'reply_number'      => new xmlrpcval($topic['replies'], 'int'),
            'view_number'       => new xmlrpcval($topic['views'], 'int'),
            'topic_author_id'   => new xmlrpcval($topic['first_post']['member']['id'], 'string'),
            'topic_author_name' => new xmlrpcval(processUsername($topic['first_post']['member']['name']), 'base64'),
            'post_author_id'    => new xmlrpcval($topic['last_post']['member']['id'], 'string'),
            'post_author_name'  => new xmlrpcval(processUsername($topic['last_post']['member']['name']), 'base64'),
            'forum_id'          => new xmlrpcval($topic['board']['id'], 'string'),
            'forum_name'        => new xmlrpcval(processSubject($topic['board']['name']), 'base64'),
            'post_id'           => new xmlrpcval($topic['last_post']['id'], 'string'),
            'is_subscribed'     => new xmlrpcval($topic['is_notify'], 'boolean'),
            'can_subscribe'     => new xmlrpcval($permission[$fid]['mark_notify'] && !$user_info['is_guest'], 'boolean'),
            'is_closed'         => new xmlrpcval(isset($topic['locked']) ? $topic['locked'] : $topic['is_locked'], 'boolean'),
            'new_post'          => new xmlrpcval($topic['new'], 'boolean'),
            'short_content'     => new xmlrpcval(processShortContent($message), 'base64'),
            'post_time'         => new xmlrpcval($post_time, 'dateTime.iso8601'),
            'last_reply_time'   => new xmlrpcval($post_time, 'dateTime.iso8601'),
            'icon_url'          => new xmlrpcval($use_first ? $topic['first_post']['member']['avatar']['href'] : $topic['last_post']['member']['avatar']['href'], 'string'),
            'can_delete'        => new xmlrpcval($permission[$fid]['remove_any'] || ($started && $permission[$fid]['remove_own']), 'boolean'),
            'can_close'         => new xmlrpcval($permission[$fid]['lock_any'] || ($started && $permission[$fid]['lock_own']), 'boolean'),
            'can_approve'       => new xmlrpcval(false, 'boolean'),
            'can_stick'         => new xmlrpcval($permission[$fid]['make_sticky'], 'boolean'),
            'can_move'          => new xmlrpcval($permission[$fid]['move_any'] || ($started && $permission[$fid]['move_own']), 'boolean'),
            'can_rename'        => new xmlrpcval($permission[$fid]['modify_any'] || ($started && $permission[$fid]['modify_own']), 'boolean'),
            'can_ban'           => new xmlrpcval($permission[$fid]['manage_bans'], 'boolean'),
            'is_sticky'         => new xmlrpcval($topic['is_sticky'], 'boolean'),
        ), 'struct');
    }

    return $topics;
}