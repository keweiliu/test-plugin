<?php

defined('IN_MOBIQUO') or exit;

function update_push_status_func($xmlrpc_params)
{
    global $member, $settings;
    
    $decode_params = php_xmlrpc_decode($xmlrpc_params);
    
    $userid = $member['member_id'];
    $status = false;
    
    if (ipsRegistry::DB()->checkForTable( 'tapatalk_users' ) && is_array($decode_params[0]) && !empty($decode_params[0]))
    {
        if (empty($userid) && isset($decode_params[1]) && isset($decode_params[2]))
        {
            $username = to_local($decode_params[1]);
            $password = to_local($decode_params[2]);
            
            $member = IPSMember::load( IPSText::parseCleanValue( $username ), 'all', 'username' );
            
            if ( $member['member_id'] )
            {
                $result = IPSMember::authenticateMember( $member['member_id'], md5( IPSText::parseCleanValue( $password ) ) );
                
                if ( $result !== false )
                {
                    $userid = $member['member_id'];
                }
            }
        }
        
        if ($userid)
        {
            $data = array(
                'url'  => $settings['board_url'],
                'key'  => (!empty($settings['tapatalk_push_key']) ? $settings['tapatalk_push_key'] : ''),
                'uid'  => $userid,
                'data' => base64_encode(serialize($decode_params[0])),
            );
            $url = 'https://directory.tapatalk.com/au_update_push_setting.php';
            getContentFromRemoteServer($url, 0, $error_msg, 'POST', $data);
            
            $status = true;
        }
    }
    
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval($status, 'boolean'),
    ), 'struct'));
}