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

function unsubscribe_forum_func($xmlrpc_params)
{
    global $vbulletin, $permissions, $db, $vbphrase;

    if (($vbulletin->userinfo['userid'] AND !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
        OR $vbulletin->userinfo['usergroupid'] == 4
        OR !($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
    {
        return_fault();
    }

    $params = php_xmlrpc_decode($xmlrpc_params);
    if(strpos($params[0], 's_') !== false)
    {
        $subsc_id_temp = explode('_', $params[0]);
        $subsc_id = isset($subsc_id_temp[1]) ? intval($subsc_id_temp[1]) : '';
        if(empty($subsc_id)) return_fault('invalid subscribe id');
        $substable = 'subscribe' . 'forum';
        $idfield = $substable . 'id';
        if ($db->query_first_slave("
                    SELECT $idfield
                    FROM " . TABLE_PREFIX . "$substable AS $substable
                    LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid=$substable.userid)
                    WHERE $idfield = " . $subsc_id . "
                        AND $substable.userid = " . $vbulletin->userinfo['userid'] . "
            "))
            {
                $db->query_write("
                    DELETE FROM " . TABLE_PREFIX . "$substable
                    WHERE $idfield = " . $subsc_id . "
                ");
                return new xmlrpcresp(new xmlrpcval(array(
                    'result' => new xmlrpcval(true, 'boolean'),
                ), 'struct'));
            }
        else
            return_fault('Invalid subscribtion id specified!');
    }
    if ($params[0] == 'ALL')
    {
        $forumidfilter = '';
    }
    else
    {
        $forumid = intval($params[0]);
        if (!$forumid) {
            return_fault(fetch_error('invalidid', $vbphrase['forum']));
        }
        $forumidfilter = " AND forumid = '$forumid'";
    }

    $db->query_write("
        DELETE FROM " . TABLE_PREFIX . "subscribeforum
        WHERE userid = " . $vbulletin->userinfo['userid'] .
        $forumidfilter
    );
    
    return new xmlrpcresp(new xmlrpcval(array(
        'result' => new xmlrpcval(true, 'boolean'),
    ), 'struct'));
}
