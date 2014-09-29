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
require_once (IPS_ROOT_PATH . 'applications/core/modules_public/global/login.php');

class authorize_user extends public_core_global_login
{
    public function doExecute( ipsRegistry $registry )
    {
        $this->registry   =  $registry;
        $this->DB         =  $this->registry->DB();
        $this->settings   =& $this->registry->fetchSettings();
        $this->request    =& $this->registry->fetchRequest();
        $this->lang       =  $this->registry->getClass('class_localization');
        $this->member     =  $this->registry->member();
        $this->memberData =& $this->registry->member()->fetchMemberData();
        $this->cache      =  $this->registry->cache();
        $this->caches     =& $this->registry->cache()->fetchCaches();

        $this->registry->getClass( 'class_localization' )->loadLanguageFile( array( 'public_login' ), 'core' );
        ###########################
        //$_POST['username'] = to_local($_POST['username']);
        //$_POST['password'] = to_local($_POST['password']);

        //$this->request['password'] = IPSText::parseCleanValue($_POST['password'], false);
        //$this->request['username'] = IPSText::parseCleanKey($_POST['username']);

        $this->request['password'] = to_local($this->request['password']);
        $this->request['username'] = to_local($this->request['username']);

        // 3.2.0 compatibility
        $this->request['ips_username'] = $this->request['username'];
        $this->request['ips_password'] = $this->request['password'];

        $this->request['auth_key'] = $this->member->form_hash;
        global $username;
        $username = $this->request['username'];
        $this->initHanLogin();
        $result = $this->try_login();
        if(empty($result[0]))
        {
            if((IPSText::checkEmailAddress( $this->request['ips_username'] )))
            {
                $this->han_login->setForceEmailCheck( TRUE );
                $result = $this->try_login();
            }
        }
        
        return $result;
    }
    
    public function try_login()
    {
        $result = $this->doLogin();

        if ($result[2])
        {
            if( $result[3] )
            {
                $result[2] = sprintf( $this->lang->words[$result[2]], $result[3]);
            }
            else
            {
                $result[2] = $this->lang->words[$result[2]];
            }
        }
        return $result;
    }
}

