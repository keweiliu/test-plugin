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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/forums.php');

class login_forum extends public_forums_forums_forums
{
	public function doExecute( ipsRegistry $registry)
	{
        $this->request['f'] =  intval($this->request['f']  ); 
        ######################################
        if( ! $this->request['f'] ) {
        	get_error("Forum ID Error!");
        }
        
        ####################################          
        $this->initForums();
     
        $this->forum = $this->registry->getClass('class_forums')->forum_by_id[ $this->request['f'] ];
        if( ! $this->forum['id'] ) {
        	get_error("No Such Forum!");
        }        

        if( isset( $this->forum['redirect_on'] ) AND $this->forum['redirect_on'] ) {
        	get_error("Forum Redirect On!");
        }
        
		if ( $this->forum['sub_can_post'] )
		{ 
    		if( $this->request['f_password'] == "" )
    		{
    			return false;
    		}
    		
    		if( $this->request['f_password'] != $this->forum['password'] )
    		{
    			return false;
    		}
    		
    		IPSCookie::set( "ipbforumpass_".$this->forum['id'], md5( $this->request['f_password'] ) );
    		return true;
		}
		else
		{
			return false;
		}
	}
}
	

?>