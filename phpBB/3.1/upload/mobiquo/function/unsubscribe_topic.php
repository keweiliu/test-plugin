<?php
/**
*
* @copyright (c) 2009, 2010, 2011 Quoord Systems Limited
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License (GPLv2)
*
*/

defined('IN_MOBIQUO') or exit;

function unsubscribe_topic_func()
{
    global $db, $user, $request_params;
    
    $user->setup('viewtopic');
    
    $params = $request_params;
    
    // get topic id from parameters
    $topic_id = intval($params[0]);
    if (!$topic_id) trigger_error('NO_TOPIC');
    $user_id = $user->data['user_id'];
    $uns_result = false;

    // Is user login?
    if ($user_id != ANONYMOUS)
    {
        $sql = 'SELECT notify_status
                FROM ' . TOPICS_WATCH_TABLE . "
                WHERE topic_id = $topic_id
                AND user_id = $user_id";
        $result = $db->sql_query($sql);

        $notify_status = ($row = $db->sql_fetchrow($result)) ? $row['notify_status'] : NULL;
        $db->sql_freeresult($result);

        if (!is_null($notify_status) && $notify_status !== '')
        {
            $sql = 'DELETE FROM ' . TOPICS_WATCH_TABLE . "
                WHERE topic_id = $topic_id
                    AND user_id = $user_id";
            $db->sql_query($sql);
            $uns_result = true;
        }
    }
    
    $response = array(
            'result'        => (boolean)$s_result,
            'result_text'   => $s_result ? '' : 'Unsubscribe failed',
    );
    
    return $response;
}
