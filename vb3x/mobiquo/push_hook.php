<?php

$push_env_ready = (function_exists('curl_init') || ini_get('allow_url_fopen'));
if (defined('TT_PUSH_TYPE') && !defined('mobiquo_push_sent'))
{
    require_once(DIR.'/'.$vbulletin->options['tapatalk_directory'].'/include/function_push.php');

    if (TT_PUSH_TYPE == 'pm')
    {
        $ttp_touser = $user['userid'];
        $ttp_id = $pmtextid;
        $ttp_title = fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($this->pmtext['title'], true, false, false, true), 100)));
        $ttp_author = $this->pmtext['fromusername'];
        $ttp_dateline = $this->pmtext['dateline'];
        if($push_env_ready && $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "tapatalk_users WHERE userid='$user[userid]'"))
        {
            $ttp_data = array(
                'userid'    => $ttp_touser,
                'type'      => 'pm',
                'id'        => 'textid_' . $ttp_id,
                'title'     => tt_hook_encode($ttp_title),
                'author'    => tt_hook_encode($ttp_author),
                'dateline'  => $ttp_dateline,
            );

            $ttp_post_data = array(
                'url'  => $vbulletin->options['bburl'],
                'data' => base64_encode(serialize(array($ttp_data))),
            );
            
            if(isset($vbulletin->options['push_key']) && !empty($vbulletin->options['push_key']))
            {
                $ttp_post_data['key'] = $vbulletin->options['push_key'];
            }
            $return_status = do_post_request($ttp_post_data);
        }
        $ttp_title = $this->dbobject->escape_string($ttp_title);
        $ttp_author = $this->dbobject->escape_string($ttp_author);

        $this->dbobject->query_write("
            INSERT INTO " . TABLE_PREFIX . "tapatalk_push
                (userid, type, id, title, author, dateline)
            VALUES
                ('$ttp_touser', 'pm', 'textid_$ttp_id', '$ttp_title', '$ttp_author', '$ttp_dateline')"
        );
    }
    else if (TT_PUSH_TYPE == 'sub')
    {
        $push_users = array();
        if ($post['visible'] AND !in_coventry($vbulletin->userinfo['userid'], true) AND $post['postid'] AND $threadinfo['threadid'])
        {
            $userid = $vbulletin->userinfo['userid'];
            $postid = $post['postid'];
            $threadid = $threadinfo['threadid'];
            $threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
            $push_title = fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($threadinfo['title'], true, false, false, true), 100)));

            // get last reply time
            $dateline = $vbulletin->db->query_first("
                SELECT dateline
                FROM " . TABLE_PREFIX . "post
                WHERE postid = $postid
            ");

            $lastposttime = $vbulletin->db->query_first("
                SELECT MAX(dateline) AS dateline
                FROM " . TABLE_PREFIX . "post AS post
                WHERE threadid = $threadid
                    AND dateline < $dateline[dateline]
                    AND visible = 1
            ");

            $push_data = array();
            //Add sub push data
            if($type != 'thread')
            {
                $useremails = $vbulletin->db->query_read_slave("
                    SELECT user.*, subscribethread.emailupdate, subscribethread.subscribethreadid
                    FROM " . TABLE_PREFIX . "subscribethread AS subscribethread
                    INNER JOIN " . TABLE_PREFIX . "user AS user ON (subscribethread.userid = user.userid)
                    INNER JOIN " . TABLE_PREFIX . "tapatalk_users AS tt_user ON (subscribethread.userid = tt_user.userid)
                    LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
                    LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
                    WHERE subscribethread.threadid = $threadid AND
                        subscribethread.canview = 1 AND
                        " . ($userid ? "CONCAT(' ', IF(usertextfield.ignorelist IS NULL, '', usertextfield.ignorelist), ' ') NOT LIKE '% " . intval($userid) . " %' AND" : '') . "
                        user.usergroupid <> 3 AND
                        user.userid <> " . intval($userid) . " AND
                        user.lastactivity >= " . intval($lastposttime['dateline']) . " AND
                        (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
                ");
                
                $sub_users = array();
                while ($touser = $vbulletin->db->fetch_array($useremails))
                {
                    if (!($vbulletin->usergroupcache["$touser[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
                    {
                        continue;
                    }
                    if($vbulletin->userinfo['userid'] == $touser['userid']) continue; // Don't send to author himself.

                    $sub_users[] = $touser['userid'];
                    $push_users[] = $touser['userid'];
                }
                if(!empty($sub_users))
                {
                    $pre_uids = implode(',', $sub_users);

                    $push_data[] = array(
                        'userid'    => $pre_uids,
                        'type'      => 'sub',
                        'id'        => $threadid,
                        'subid'     => $postid,
                        'title'     => tt_hook_encode($push_title),
                        'author'    => tt_hook_encode($vbulletin->userinfo['username']),
                        'dateline'  => $dateline['dateline'],
                        'send_push' => 1,
                    );
                }
            }
            else
            {//Add new_topic push data
                if($threadinfo['forumid'])
                {
                    $results = $vbulletin->db->query_read_slave("
                        SELECT subscribe.userid
                        FROM " . TABLE_PREFIX . "subscribeforum AS subscribe
                        INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (subscribe.forumid = forum.forumid)
                        INNER JOIN " . TABLE_PREFIX . "tapatalk_users AS tt_user ON (subscribe.userid = tt_user.userid)
                        WHERE subscribe.forumid = " . $threadinfo['forumid']
                    );
                    
                    $new_topic_users = array();
                    while ($row = $vbulletin->db->fetch_array($results)) {
                        
                        if($vbulletin->userinfo['userid'] == $row['userid']) continue; // Don't send to author himself.

                        $new_topic_users[] = $row['userid'];
                        $push_users[] = $row['userid'];
                    }
                    if(!empty($new_topic_users))
                    {
                        $pre_uids = implode(',', $new_topic_users);
                        $push_data[] = array(
                            'userid'    => $pre_uids,
                            'type'      => 'newtopic',
                            'id'        => $threadid,
                            'subid'     => $postid,
                            'fid'       => $threadinfo['forumid'],
                            'title'     => tt_hook_encode($push_title),
                            'author'    => tt_hook_encode($vbulletin->userinfo['username']),
                            'dateline'  => $dateline['dateline'],
                            'send_push' => 1,
                        );
                    }
                }
            }
            
            //Add quote push data
            $message = $post['message'];
            $quotedUsers = array();
            if(preg_match_all('/\[quote=(.*?);(\d+)\]/si', $message, $quote_matches))
            {
                $quote_postids = $quote_matches[2];
                $quote_post_data = $vbulletin->db->query_read_slave("
                        SELECT post.postid, post.title, post.pagetext, post.dateline, post.userid, post.visible AS postvisible,
                            IF(user.username <> '', user.username, post.username) AS username,
                            thread.threadid, thread.title AS threadtitle, thread.postuserid, thread.visible AS threadvisible,
                            forum.forumid, forum.password
                            $hook_query_fields
                        FROM " . TABLE_PREFIX . "post AS post
                        LEFT JOIN " . TABLE_PREFIX . "user AS user ON (post.userid = user.userid)
                        INNER JOIN " . TABLE_PREFIX . "tapatalk_users AS tt_user ON (post.userid = tt_user.userid)
                        INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
                        INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (thread.forumid = forum.forumid)
                        $hook_query_joins
                        WHERE post.postid IN (" . implode(',', $quote_postids) . ")
                    ");
                $quote_status_users = array();
                $quote_posts = array();
                while ($quote_post = $vbulletin->db->fetch_array($quote_post_data))
                {
                    if (
                    ((!$quote_post['postvisible'] OR $quote_post['postvisible'] == 2) AND !can_moderate($quote_post['forumid'])) OR
                    ((!$quote_post['threadvisible'] OR $quote_post['threadvisible'] == 2) AND !can_moderate($quote_post['forumid']))
                    )
                    {
                        // no permission to view this post
                        continue;
                    }

                    $forumperms = fetch_permissions($quote_post['forumid']);
                    if (
                    (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])) OR
                    (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($quote_post['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)) OR
                    !verify_forum_password($quote_post['forumid'], $quote_post['password'], false) OR
                    (in_coventry($quote_post['postuserid']) AND !can_moderate($quote_post['forumid'])) OR
                    (in_coventry($quote_post['userid']) AND !can_moderate($quote_post['forumid']))
                    )
                    {
                        // no permission to view this post
                        continue;
                    }

                    if (($limit_thread == 'only' AND $quote_post['threadid'] != $threadid) OR
                    ($limit_thread == 'other' AND $quote_post['threadid'] == $threadid) OR $limit_thread == 'all')
                    {
                        $unquoted_posts++;
                        continue;
                    }

                    $skip_post = false;
                    ($hook = vBulletinHook::fetch_hook('quotable_posts_logic')) ? eval($hook) : false;

                    if ($skip_post)
                    {
                        continue;
                    }

                    $quote_posts["$quote_post[postid]"] = $quote_post;
                    $quote_status_users[$quote_post['userid']] = 1;
                }
                
                foreach($quote_posts as $post_id => $quote_post)
                {
                    //check if the quoted users has permission to view this forum
                    $userinfo = fetch_userinfo($quote_post['userid']);
                    $forumperms = fetch_permissions($threadinfo['forumid'], $quote_post['userid'], $userinfo);
                    if (
                    (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads'])) OR
                    (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($quote_post['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0)) OR
                    !verify_forum_password($quote_post['forumid'], $quote_post['password'], false) OR
                    (in_coventry($quote_post['postuserid']) AND !can_moderate($quote_post['forumid'])) OR
                    (in_coventry($quote_post['userid']) AND !can_moderate($quote_post['forumid']))
                    )
                    {
                        // no permission to view this post
                        continue;
                    }
                    $quotedUsers[] = $quote_post['userid'];
                }
                
                if(!empty($quotedUsers))
                {
                    foreach($quotedUsers as $quoteUser)
                    {
                        if(in_array($quoteUser, $push_users))
                            continue;   // If user already pushed with sub, no more rest push type.
                        if($vbulletin->userinfo['userid'] == $quoteUser)
                            continue;   // Don't send to author himself.
                        $push_data[] = array(
                            'userid'    => $quoteUser,
                            'type'      => 'quote',
                            'id'        => $threadid,
                            'subid'     => $postid,
                            'title'     => tt_hook_encode($push_title),
                            'author'    => tt_hook_encode($vbulletin->userinfo['username']),
                            'dateline'  => $dateline['dateline'],
                            'send_push' => $quote_status_users[$quoteUser],
                        );
                        $push_users[] = $quoteUser;
                    }
                }
            }

            //Add @/Tag push data
            if ( preg_match_all( '/(?<=^@|\s@)(#(.{1,50})#|\S{1,50}(?=[,\.;!\?]|\s|$))/U', $message, $tags ) )
            {
                foreach ($tags[2] as $index => $tag)
                {
                    if ($tag) $tags[1][$index] = $tag;
                }
                $tagged_usernames =  array_unique($tags[1]);
                if(!empty($tagged_usernames))
                {
                    foreach($tagged_usernames as $index => $tagged_username)
                        $tagged_usernames[$index] = $vbulletin->db->escape_string($tagged_username);
                }
                $tagged_users  = array();
                //initial query conditions
                global $vbphrase;
                $option = 0;
                $languageid = 0;

                $query_text = "
                    SELECT " .
                    iif(($option & FETCH_USERINFO_ADMIN), ' administrator.*, ') . "
                    user.*, UNIX_TIMESTAMP(passworddate) AS passworddate, user.languageid AS saved_languageid,
                    IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid" .
                    iif(($option & FETCH_USERINFO_AVATAR) AND $vbulletin->options['avatarenabled'], ', avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight, customavatar.height_thumb AS avheight_thumb, customavatar.width_thumb AS avwidth_thumb, customavatar.filedata_thumb').
                    iif(($option & FETCH_USERINFO_PROFILEPIC), ', customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight') .
                    iif(($option & FETCH_USERINFO_SIGPIC), ', sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight') .
                    (($option & FETCH_USERINFO_USERCSS) ? ', usercsscache.cachedcss, IF(usercsscache.cachedcss IS NULL, 0, 1) AS hascachedcss, usercsscache.buildpermissions AS cssbuildpermissions' : '') .
                    (isset($vbphrase) ? '' : fetch_language_fields_sql()) .
                    (($vbulletin->userinfo['userid'] AND ($option & FETCH_USERINFO_ISFRIEND)) ?
                        ", IF(userlist1.friend = 'yes', 1, 0) AS isfriend, IF (userlist1.friend = 'pending' OR userlist1.friend = 'denied', 1, 0) AS ispendingfriend" .
                        ", IF(userlist1.userid IS NOT NULL, 1, 0) AS u_iscontact_of_bbuser, IF (userlist2.friend = 'pending', 1, 0) AS requestedfriend" .
                        ", IF(userlist2.userid IS NOT NULL, 1, 0) AS bbuser_iscontact_of_user" : "") . "
                    $hook_query_fields
                    FROM " . TABLE_PREFIX . "user AS user
                    LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
                    INNER JOIN " . TABLE_PREFIX . "tapatalk_users AS tt_user ON (user.userid = tt_user.userid)
                    LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid) " .
                    iif(($option & FETCH_USERINFO_AVATAR) AND $vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) ") .
                    iif(($option & FETCH_USERINFO_PROFILEPIC), "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) ") .
                    iif(($option & FETCH_USERINFO_ADMIN), "LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON (administrator.userid = user.userid) ") .
                    iif(($option & FETCH_USERINFO_SIGPIC), "LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON (user.userid = sigpic.userid) ") .
                    (($option & FETCH_USERINFO_USERCSS) ? 'LEFT JOIN ' . TABLE_PREFIX . 'usercsscache AS usercsscache ON (user.userid = usercsscache.userid)' : '') .
                    iif(!isset($vbphrase), "LEFT JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = " . (!empty($languageid) ? $languageid : "IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid)") . ") ") .
                    (($vbulletin->userinfo['userid'] AND ($option & FETCH_USERINFO_ISFRIEND)) ?
                        "LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist1 ON (userlist1.relationid = user.userid AND userlist1.type = 'buddy' AND userlist1.userid = " . $vbulletin->userinfo['userid'] . ")" .
                        "LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist2 ON (userlist2.userid = user.userid AND userlist2.type = 'buddy' AND userlist2.relationid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
                    WHERE user.username IN ('" . implode('\',\'', $tagged_usernames) . "')
                    ";

                $results = $vbulletin->db->query_read_slave($query_text);
                $tag_status_users = array();
                while ($result = $vbulletin->db->fetch_array($results))
                {
                    $tag_status_users[$result['userid']] = 1;
                    $tagged_users[] = $result['userid'];
                }
                if(!empty($tagged_users))
                {
                    foreach($tagged_users as $tagged_user)
                    {
                        if(in_array($tagged_user, $push_users))
                            continue;   // If user already pushed with sub, no more rest push type.
                        if($vbulletin->userinfo['userid'] == $tagged_user)
                            continue;   // Don't send to author himself.
                        $push_data[] = array(
                            'userid'    => $tagged_user,
                            'type'      => 'tag',
                            'id'        => $threadid,
                            'subid'     => $postid,
                            'title'     => tt_hook_encode($push_title),
                            'author'    => tt_hook_encode($vbulletin->userinfo['username']),
                            'dateline'  => $dateline['dateline'],
                            'send_push' => $tag_status_users[$tagged_user],
                        );
                        $push_users[] = $tagged_user;
                    }
                }
            }
            //Process all push data
            if (!empty($push_data))
            {
                //Fill in all push data for get_alert use.
                $ttp_title = $vbulletin->db->escape_string($push_title);
                $ttp_author = $vbulletin->db->escape_string($vbulletin->userinfo['username']);
                $send_push_data = array();
                foreach($push_data as $item)
                {
                    $uids = explode(',', $item['userid']);
                    foreach($uids as $uid)
                    {
                        $vbulletin->db->query_write("
                            INSERT INTO " . TABLE_PREFIX . "tapatalk_push
                                (userid, type, id, subid, title, author, dateline)
                            VALUES
                                ('$uid', '$item[type]', '$threadid', '$postid', '$ttp_title', '$ttp_author', '$item[dateline]')"
                        );
                    }
                    if($item['send_push'])
                    {
                        unset($item['send_push']);
                        $send_push_data[] = $item;
                    }
                }
                if($push_env_ready && !empty($send_push_data))
                {
                    $ttp_post_data = array(
                        'url'  => $vbulletin->options['bburl'],
                        'data' => base64_encode(serialize($send_push_data)),
                    );
                    if(isset($vbulletin->options['push_key']) && !empty($vbulletin->options['push_key']))
                    {
                        $ttp_post_data['key'] = $vbulletin->options['push_key'];
                    }
                    $return_status = do_post_request($ttp_post_data);
                }
            }
        }
    }
    define('mobiquo_push_sent', true);
}


