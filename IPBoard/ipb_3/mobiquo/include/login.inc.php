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
define('NOROBOT', TRUE);
define('CURSCRIPT', 'logging');
require_once ('class/authorize_user_class.php');
//require_once (FORUM_ROOT.'/mobiquo/include/class/authorize_user_class.php');
$authorize_user = new authorize_user($registry);
$result = $authorize_user->doExecute($registry);

if ($result[0]) {
    $login_result = true;
    $result_text = '';
    $member = IPSMember::load($username, 'all', 'username');
    if(empty($member))
    {
        $member = IPSMember::load($username, 'all', 'email');
    }
    $max_single_upload = intval(IPSLib::getMaxPostSize());
    
    // update push status for tapatalk user
    $push_type = array();
    $table = 'tapatalk_users';
    if( $member['member_id'] && ipsRegistry::DB()->checkForTable( $table ))
    {
        $check = ipsRegistry::DB()->buildAndFetch( array( 'select' => 'userid', 'from' => $table, 'where' => 'userid=' . intval($member['member_id']) ) );
        
        if( !$check['userid'] )
        {
            $data = array('userid' => $member['member_id']);
            ipsRegistry::DB()->insert( $table, $data );
        }
        else
        {
            $data = array('updated' => date("Y-m-d H:i:s"));
            ipsRegistry::DB()->update( $table, $data, 'userid=' . $member['member_id'] );
        }
        
        $userPushType = array('conv', 'sub', 'like', 'quote', 'newtopic', 'tag');
        foreach ($userPushType as $type)
        {
            $push_type[] = new xmlrpcval(array(
                'name'  => new xmlrpcval($type, 'string'),
                'value' => new xmlrpcval(true, 'boolean'),
            ), 'struct');
        }
    }
    
} else {
    $login_result = false;
    $result_text = $result[2];
    $max_single_upload = 0;
}
