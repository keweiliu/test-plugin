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
define('NOROBOT', TRUE);
define('CURSCRIPT', 'logging');

require_once( IPS_ROOT_PATH . 'applications/core/modules_public/global/login.php' );

class tapatalk_login extends public_core_global_login
{
    public $result = true;
    public $result_text = '';
    public $status = 2;
    
    public function doExecute( ipsRegistry $registry )
    {
        $this->registry->getClass( 'class_localization' )->loadLanguageFile( array( 'public_login' ), 'core' );
        $this->initHanLogin();

        switch( $this->request['do'] )
        {
            case 'process':
                $this->request['ips_username'] = to_local($this->request['ips_username']);
                $this->request['ips_password'] = to_local($this->request['ips_password']);
                $this->request['auth_key'] = $this->member->form_hash;
                $return = $this->doLogin();

                if( $return[2] )
                {
                    $this->result = false;
                    if( $return[3] )
                    {
                        $this->result_text = sprintf( $this->lang->words[ $return[2] ], $return[3] );
                    }
                    else
                    {
                        $this->result_text = $this->lang->words[ $return[2] ];
                    }
                    
                    // return 2 when failed with occupied username
                    if(IPSMember::load( $this->request['ips_username'], '', 'username')) $this->status = 0;
                }
                else
                {
                    $this->member->setMember($this->han_login->member_data['member_id']);
                    if($this->han_login->member_data['member_banned'] || $this->han_login->member_data['member_group_id'] == $this->settings['banned_group'])
                    {
                        $this->registry->getClass('class_localization')->loadLanguageFile( array( 'public_error' ), 'core' );
                        $this->result_text = $this->lang->words['you_are_banned'];
                    }
                }

                if ( ! ( $this->registry->cache()->getCache('attachtypes') ) OR ! is_array( $this->registry->cache()->getCache('attachtypes') ) )
                {
                    $attachtypes = array();
        
                    $this->DB->build( array(
                                            'select'    => 'atype_extension,atype_mimetype,atype_post,atype_img',
                                            'from'      => 'attachments_type',
                                            'where'     => "atype_post=1" 
                                        )   );
                    $this->DB->execute();
            
                    while ( $r = $this->DB->fetch() )
                    {
                        $attachtypes[ $r['atype_extension'] ] = $r;
                    }
                    
                    $this->registry->cache()->updateCacheWithoutSaving( 'attachtypes', $attachtypes );
                }
            break;

            case 'logout':
                $this->request['k'] = $this->member->form_hash;
                $this->doLogout();
            break;

            case 'deleteCookies':
                $this->deleteCookies();
            break;

            default:
                $this->result = false;
            break;
        }
    }
}
