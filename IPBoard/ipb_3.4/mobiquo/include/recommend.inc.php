<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | https://tapatalk.com/license.php       # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;

class tapatalk_recommend extends ipsCommand
{
    private $encryptKey = '';
    
    public $total = 0;
    public $recommendUsers = array();
    
    // for get_contact
    public $contact = array();
    
    // for admin_invite
    public $totalMember = 0;
    public $maxMemberId = 0;
    public $users = array();
    
    public function doExecute( ipsRegistry $registry )
    {
        if ($this->request['do'] == 'contact')
        {
            return $this->getContact();
        }
        else if($this->request['do'] == 'user_sync')
        {
            return $this->user_sync();
        }
        
        if (empty($this->memberData['member_id']))
        {
            $this->registry->getClass('output')->showError( 'You must be logged in to access this feature' );
        }
        
        $this->loadEncryptKey();
        
        if (empty($this->encryptKey))
        {
            //$this->registry->getClass('output')->showError( 'Failed to load Tapatalk API Key' );
            return;
        }
        
        $userConv = $this->getConvUsers();
        $userLike = $this->getLikeUsers();
        $userFriends = $this->getFriendsUsers();
        $userWatch = $this->getWatchUsers();
        
        $userRecommend = array();
        foreach ($userConv    as $uid => $score) $userRecommend[$uid] += $score;
        foreach ($userLike    as $uid => $score) $userRecommend[$uid] += $score;
        foreach ($userFriends as $uid => $score) $userRecommend[$uid] += $score;
        foreach ($userWatch   as $uid => $score) $userRecommend[$uid] += $score;
        
        if ($this->request['mode'] == 2)
        {
            foreach ($userRecommend as $uid => $score)
            {
                if ($this->isTapatalkUser($uid))
                    unset($userRecommend[$uid]);
            }
        }
        
        if (isset($userRecommend[$this->memberData['member_id']]))
            unset($userRecommend[$this->memberData['member_id']]);
        
        if (isset($userRecommend[0]))
            unset($userRecommend[0]);
        
        arsort($userRecommend);
        $all_uids = array_keys($userRecommend);
        $this->total = count($all_uids);
        $uids = array_slice($all_uids, $this->request['st'], $this->request['perpage']);
        $uid_filter = implode(',', array_map('intval', $uids));
        
        if ($uid_filter)
        {
            ipsRegistry::DB()->build(array('select' => 'm.member_id, m.members_display_name, m.email',
                                            'from'  => array( 'members' => 'm' ),
                                            'where' => 'm.member_id IN (' . $uid_filter . ')',
                                    ));
            ipsRegistry::DB()->execute();
            
            while ( $r = ipsRegistry::DB()->fetch() )
            {
                $r['encrypt_email'] = $this->encrypt($r['email']);
                $r['score'] = $userRecommend[$r['member_id']];
                $this->recommendUsers[] = $r;
            }
        }
    }
    
    protected function getContact()
    {
        if ($this->request['uid'] && $member = IPSMember::load( $this->request['uid'] ))
        {
            $this->loadEncryptKey();
            
            if ($this->encryptKey)
            {
                $member['encrypt_email'] = $this->encrypt($member['email']);
                $this->contact = $member;
            }
        }
    }
    
    protected function user_sync()
    {
        $_GET['st'] = intval(isset($_POST['start']) ? $_POST['start'] : 0);
        $_GET['perpage'] = intval(isset($_POST['limit']) ? $_POST['limit'] : 1000);
        
        $this->loadEncryptKey();
        
        if ($this->encryptKey)
        {
            $start = isset($this->request['start']) ? intval($this->request['start']) : 0;
            $limit = isset($this->request['limit']) ? intval($this->request['limit']) : 1000;
            
            // get total members count
            $queryData = array(
                'select'    => 'count(*) as count, max(m.member_id) as maxid',
                'from'      => array( 'members' => 'm' )
            );
            
            $countmembers = $this->DB->buildAndFetch( $queryData );
            $this->totalMember = $countmembers['count'];
            $this->maxMemberId = $countmembers['maxid'];
            
            // get members
            $this->DB->build( array( 'select'   => 'm.member_id, m.members_display_name, m.allow_admin_mails, m.email',
                                     'from'     => array( 'members' => 'm'),
                                     'order'    => 'm.member_id ASC',
                                     'limit'    => array( $start, $limit ),
                                    ) );
            $this->DB->execute();
            while( $row = $this->DB->fetch() )
            {
                $row['encrypt_email'] = $this->encrypt($row['email']);
                unset($row['email']);
                $this->users[] = array_values($row);
            }
            
            $data = array(
                'result' => true,
                'total' => $this->totalMember,
                'maxid' => $this->maxMemberId,
                'users' => $this->users,
            );
        }
        else
        {
            $data = array(
                'result' => false,
                'error' => 'Failed to load API key',
            );
        }
        
        $response = function_exists('json_encode') ? json_encode($data) : serialize($data);
        
        @ob_end_clean();
        echo $response;
        exit;
    }
    
    protected function getConvUsers()
    {
        $userConv = array();
        
        // get contacts older than 3 months
        $this->DB->build( array(  'select'      => 'DISTINCT (mp.msg_author_id)',
                                  'from'        => array( 'message_topic_user_map' => 'mm' ),
                                  'where'       => "mm.map_user_id=" . intval($this->memberData['member_id']) . " and mp.msg_date < unix_timestamp()-90*86400",
                                  'limit'       => array( 0, 1000 ),
                                  'add_join'    => array(
                                                        array( 'from'   => array( 'message_posts' => 'mp' ),
                                                               'where'  => 'mm.map_topic_id=mp.msg_topic_id',
                                                               'type'   => 'left'
                                                            ) ) ) );
        $this->DB->execute();
        
        while( $row = $this->DB->fetch() )
        {
            $userConv[$row['msg_author_id']] = 2;
        }
        
        // get contacts in 3 months
        $this->DB->build( array( 'select'   => 'DISTINCT (mp.msg_author_id)',
                                 'from'     => array( 'message_topic_user_map' => 'mm' ),
                                 'where'    => "mm.map_user_id=" . intval($this->memberData['member_id']) . " and mp.msg_date >= unix_timestamp()-90*86400",
                                 'limit'    => array( 0, 1000 ),
                                 'add_join' => array(
                                                    array( 'from'   => array( 'message_posts' => 'mp' ),
                                                           'where'  => 'mm.map_topic_id=mp.msg_topic_id',
                                                           'type'   => 'left'
                                                        ) ) ) );
        $this->DB->execute();
        
        while( $row = $this->DB->fetch() )
        {
            $userConv[$row['msg_author_id']] = 10;
        }
        
        return $userConv;
    }
    
    protected function getLikeUsers()
    {
        $userLike = array();
        
        // users I liked
        $this->DB->build( array( 'select'   => 'DISTINCT (p.author_id)',
                                 'from'     => array( 'reputation_index' => 'r'),
                                 'where'    => "r.app='forums' AND r.type='pid' AND r.member_id=" . intval($this->memberData['member_id']),
                                 'limit'    => array( 0, 1000 ),
                                 'add_join' => array(
                                                    array(
                                                        'from'  => array( 'posts' => 'p' ),
                                                        'where' => "p.pid=r.type_id"
                                                        )
                                                    ),
                                ) );
        $this->DB->execute();
        
        while( $row = $this->DB->fetch() )
        {
            $userLike[$row['author_id']] = 2;
        }
        
        // users liked me
        $this->DB->build( array( 'select'   => 'DISTINCT (r.member_id)',
                                 'from'     => array( 'reputation_index' => 'r'),
                                 'where'    => "r.app='forums' AND r.type='pid' AND p.author_id=" . intval($this->memberData['member_id']),
                                 'limit'    => array( 0, 1000 ),
                                 'add_join' => array(
                                                    array(
                                                        'from'  => array( 'posts' => 'p' ),
                                                        'where' => "p.pid=r.type_id"
                                                        )
                                                    ),
                                ) );
        $this->DB->execute();
        
        while( $row = $this->DB->fetch() )
        {
            $userLike[$row['member_id']] += 1;
        }
        
        return $userLike;
    }
    
    protected function getFriendsUsers()
    {
        $userFriends = array();
        if( $this->settings['friends_enabled'] )
        {
            $this->DB->build( array( 'select'   => 'f.friends_friend_id',
                                     'from'     => array( 'profile_friends' => 'f'),
                                     'where'    => 'f.friends_approved=1 AND f.friends_member_id=' . intval($this->memberData['member_id']),
                                     'limit'    => array( 0, 1000 ),
                                    ) );
            $this->DB->execute();
            
            while( $row = $this->DB->fetch() )
            {
                $userFriends[$row['friends_friend_id']] = 10;
            }
        }
        
        return $userFriends;
    }
    
    protected function getWatchUsers()
    {
        $userWatch = array();
        
        // users I watched
        $this->DB->build( array( 'select'   => 't.starter_id',
                                 'from'     => array( 'core_like' => 'l'),
                                 'where'    => "l.like_app = 'forums' AND l.like_area = 'topics' AND l.like_member_id=" . intval($this->memberData['member_id']),
                                 'limit'    => array( 0, 1000 ),
                                 'add_join'    => array(
                                                        array( 'from'   => array( 'topics' => 't' ),
                                                               'where'  => 't.tid=l.like_rel_id',
                                                               'type'   => 'left'
                                                            ) )
                                ) );
        $this->DB->execute();
        
        while( $row = $this->DB->fetch() )
        {
            $userWatch[$row['starter_id']] = 3;
        }
        
        // users watched me
        $this->DB->build( array( 'select'   => 'l.like_member_id',
                                 'from'     => array( 'core_like' => 'l'),
                                 'where'    => "l.like_app = 'forums' AND l.like_area = 'topics' AND t.starter_id=" . intval($this->memberData['member_id']),
                                 'limit'    => array( 0, 1000 ),
                                 'add_join'    => array(
                                                        array( 'from'   => array( 'topics' => 't' ),
                                                               'where'  => 't.tid=l.like_rel_id',
                                                               'type'   => 'left'
                                                            ) )
                                ) );
        $this->DB->execute();
        
        while( $row = $this->DB->fetch() )
        {
            $userWatch[$row['like_member_id']] += 1;
        }
        
        return $userWatch;
    }
    
    protected function isTapatalkUser($userid)
    {
        $user = $this->DB->buildAndFetch( array( 'select' => 'userid', 'from' => 'tapatalk_users', 'where' => 'userid=' . intval($userid) ) );
        return isset($user['userid']) && $user ? true : false;
    }
    
    protected function loadEncryptKey()
    {
        if(empty($this->encryptKey))
        {
            if($this->settings['tapatalk_push_key'])
            {
                $this->encryptKey = $this->settings['tapatalk_push_key'];
            }
            else if ($this->settings['board_url'])
            {
                $data = array('url' => $this->settings['board_url']);
                $response = getContentFromRemoteServer("http://directory.tapatalk.com/au_reg_verify.php", 10, $error, 'POST', $data);
                if($response)
                {
                    $result = json_decode($response, true);
                    if(isset($result['api_key']))
                    {
                        $this->encryptKey = $result['api_key'];
                    }
                }
            }
        }
    }
    
    protected function keyED($txt)
    {
        $key = md5($this->encryptKey);
        $ctr=0;
        $tmp = "";
        for ($i=0; $i < strlen($txt); $i++)
        {
            if ($ctr == strlen($key)) $ctr=0;
            $tmp .= substr($txt,$i,1) ^ substr($key, $ctr, 1);
            $ctr++;
        }
        return $tmp;
    }
     
    protected function encrypt($txt)
    {
        srand((double)microtime()*1000000);
        $encrypt_key = md5(rand(0,32000));
        $ctr=0;
        $tmp = "";
        for ($i=0; $i < strlen($txt); $i++)
        {
            if ($ctr == strlen($encrypt_key)) $ctr=0;
            $tmp .= substr($encrypt_key, $ctr,1) .
            (substr($txt, $i, 1) ^ substr($encrypt_key, $ctr, 1));
            $ctr++;
        }
        return $this->keyED($tmp);
    }
} 