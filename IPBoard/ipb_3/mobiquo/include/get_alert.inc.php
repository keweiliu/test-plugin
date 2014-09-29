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

$alert = new tapatalk_alert($registry);
$alert->makeRegistryShortcuts($registry);
$alertData = $alert->doExecute($registry);

class tapatalk_alert extends ipsCommand
{
    public function doExecute( ipsRegistry $registry )
    {
        $this->request['st'] = isset($this->request['st']) ? intval($this->request['st']) : 0;
        $this->request['perpage'] = isset($this->request['perpage']) ? intval($this->request['perpage']) : 20;
        $table = "tapatalk_push_data";
        
        if(!$this->DB->checkForTable($table))
        {
            get_error('tapatalk_push_data table does not exist');
        }
        
        if(empty($this->memberData['member_id']))
        {
            get_error('Please login');
        }
        
        $lang = array(
            'reply_to_you'      => '%s replied to "%s"',
            'quote_to_you'      => '%s quoted your post in thread "%s"',
            'tag_to_you'        => '%s mentioned you in thread "%s"',
            'post_new_topic'    => '%s started a new thread "%s"',
            'like_your_thread'  => '%s liked your post in thread "%s"',
            'pm_to_you'         => '%s sent you a message "%s"',
        );
        
        $nowtime = time();
        $monthtime = 30*24*60*60;
        $preMonthtime = $nowtime-$monthtime;
        // remove old alert items, only keep 30 days records
        $this->DB->delete($table, 'create_time < ' . $preMonthtime);
        
        // calculate total alerts number for requested user
        $this->DB->build( array( 'select' => 'COUNT(*) as total', 'from' => $table, 'where' => 'user_id=' . intval($this->memberData['member_id']) ) );
        $this->DB->execute();
        $calerts = $this->DB->fetch();
        $total = $calerts['total'];
        
        // fetch requested alert data
        $this->DB->build(array( 'select' => '*',
                                'from'  => $table,
                                'where' => 'user_id=' . intval($this->memberData['member_id']),
                                'order' => 'create_time DESC',
                                'limit' => array($this->request['st'], $this->request['perpage']),
        ));
        
        $query = $this->DB->execute();
        while($data = $this->DB->fetch($query))
        {
            if (empty($data['author_id']))
            {
                $member_data = IPSMember::load( $data['author'], '', 'displayname' );
                $data['author_id'] = $member_data['member_id'];
            }
            
            $data['icon_url'] = get_avatar($data['author_id']);
            
            switch ($data['data_type'])
            {
                case 'sub':
                    $data['message'] = sprintf($lang['reply_to_you'],$data['author'],$data['title']);
                    break;
                case 'tag':
                    $data['message'] = sprintf($lang['tag_to_you'],$data['author'],$data['title']);
                    break;
                case 'newtopic':
                    $data['message'] = sprintf($lang['post_new_topic'],$data['author'],$data['title']);
                    break;
                case 'quote':
                    $data['message'] = sprintf($lang['quote_to_you'],$data['author'],$data['title']);
                    break;
                case 'conv':
                    $data['position'] = $data['sub_id'];
                    $data['message'] = sprintf($lang['pm_to_you'],$data['author'],$data['title']);
                    break;
                case 'like':
                    $data['message'] = sprintf($lang['like_your_thread'],$data['author'],$data['title']);
                    break;
            }
            
            $return_data[] = $data;
        }
        
        if(empty($return_data))
        {
            $return_data = array();
        }
        
        return array('total' => $total, 'data'  => $return_data);
    }
}