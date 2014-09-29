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

require_once (IPS_ROOT_PATH . 'applications/core/modules_public/reports/reports.php');

class mobi_public_core_reports_reports extends public_core_reports_reports
{
    public function doExecute( ipsRegistry $registry ) 
    {
        $this->registry->class_localization->loadLanguageFile( array( 'public_reports' ) );
        $this->DB->loadCacheFile( IPSLib::getAppDir('core') . '/sql/' . ips_DBRegistry::getDriverType() . '_report_queries.php', 'report_sql_queries' );
        $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir('core') .'/sources/classes/reportLibrary.php', 'reportLibrary' );
        $this->registry->setClass( 'reportLibrary', new $classToLoad( $this->registry ) );

        $this->_initReportForm();
        return true;
    }
    
    public function _initReportForm()
    {
        $this->request["message"] = to_local($this->request["message"]);
        $rcom = IPSText::alphanumericalClean($this->request['rcom']);

        if( !$rcom )
        {
            //$this->registry->output->showError( 'reports_what_now', 10134 );
            get_error('reports_what_now');
        }
        
        $row = $this->caches['report_plugins'][ $rcom ];
        
        if( !$row['com_id'] )
        {
            //$this->registry->output->showError( 'reports_what_now', 10135 );
            get_error('reports_what_now');
        }
        else
        {
            if( !$row['my_class'] OR !IPSMember::isInGroup( $this->memberData, explode( ',', IPSText::cleanPermString( $row['group_can_report'] ) ) ) )
            {
                //$this->registry->output->showError( 'reports_cant_report', 10136, null, null, 403 );
                
                // no nothing when user can not report
                //get_error('reports_cant_report');
                return;
            }
            
            $classToLoad = IPSLib::loadLibrary( IPSLib::getAppDir('core') . '/sources/classes/reportNotifications.php', 'reportNotifications' );
            $notify = new $classToLoad( $this->registry );

            $this->registry->getClass('reportLibrary')->loadPlugin( $row['my_class'], $row['app'] );
            
            if( !is_object($this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]) )
            {
                //$this->registry->output->showError( 'reports_no_plugin', 10136.1, null, null, 403 );
                get_error('reports_no_plugin');
                
            }
            
            if( $row['extra_data'] && $row['extra_data'] != 'N;' )
            {
                $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->_extra = unserialize( $row['extra_data'] );
            }
            else
            {
                $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->_extra = array();
            }

            if( !trim(strip_tags($this->request['message'])) )
            {
                //$this->registry->output->showError( 'reports_cant_empty', 10181 );
                get_error('reports_cant_empty');
            }
            
            if (isset($this->request['post_id']))
            {
                $post_id = intval($this->request['post_id']);
                $post = $this->DB->buildAndFetch( array(
                                                        'select'   => 'p.*',
                                                        'from'     => array( 'posts' => 'p' ),
                                                        'where'    => "p.pid={$post_id}",
                                                        'add_join'    => array(
                                                                            array(
                                                                                'select'    => 't.forum_id',
                                                                                'from'      => array( 'topics' => 't' ),
                                                                                'where'     => 't.tid=p.topic_id',
                                                                                'type'      => 'left',
                                                                                ),
                                                                            )
                                            ) );
                
                $this->request['forum_id'] = $post['forum_id'];
                $this->request['topic_id'] = $post['topic_id'];
            }
            else if (isset($this->request['msg']))
            {
                $msg = intval($this->request['msg']);
                $message = $this->DB->buildAndFetch( array(
                                                        'select'   => 'mp.*',
                                                        'from'     => array( 'message_posts' => 'mp' ), 
                                                        'where'    => "mp.msg_id={$msg}",
                                            ) );
                
                $this->request['topic'] = $message['msg_topic_id'];
            }

            $report_data = $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->processReport( $row );
            
            $this->registry->getClass('reportLibrary')->updateCacheTime();

            $notify->initNotify( $this->registry->getClass('reportLibrary')->plugins[ $row['my_class'] ]->getNotificationList( IPSText::cleanPermString( $row['mod_group_perm'] ), $report_data ), $report_data );
            $notify->sendNotifications();
        }
    }
}