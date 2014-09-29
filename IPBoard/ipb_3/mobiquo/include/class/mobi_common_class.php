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

class mobi_common {	
	public function __construct( ipsRegistry $registry )
	{
		/* Make object */
		$this->registry = $registry;
		$this->DB	   = $this->registry->DB();
		$this->settings =& $this->registry->fetchSettings();
		$this->request  =& $this->registry->fetchRequest();
		$this->lang	 = $this->registry->getClass('class_localization');
		$this->member   = $this->registry->member();
		$this->memberData =& $this->registry->member()->fetchMemberData();
		$this->cache	= $this->registry->cache();
		$this->caches   =& $this->registry->cache()->fetchCaches();
	}
	
	
	public function get_display_by_login_name($login_name) {
		$member = $this->DB->buildAndFetch( array( 
													'select' => 'member_id', 
													'from'   => 'members', 
													'where'  => "name='{$login_name}'" 
											)	 );	
		$display_name = $member['members_display_name'];
		if ($display_name) {
			return $display_name;
		} else {
			return "";
		}
	}

	public function get_login_by_display_name($display_name) {
		$member = $this->DB->buildAndFetch( array( 
													'select' => 'member_id', 
													'from'   => 'members', 
													'where'  => "members_display_name='{$display_name}'" 
											)	 );	
		$login_name = $member['name'];
		if ($login_name) {
			return $login_name;
		} else {
			return "";
		}
	}
	
}

?>