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
require_once (IPS_ROOT_PATH . 'applications/core/modules_public/usercp/manualResolver.php');

class mobi_public_core_usercp_manualResolver extends public_core_usercp_manualResolver
{
	public function doExecute( ipsRegistry $registry )
	{
		
		$_thisNav = array();
		$this->registry->getClass('class_localization')->loadLanguageFile( array( 'public_usercp' ) );

		if ( ! $this->memberData['member_id'] )
		{
			get_error("Please Login!");
		}
		
		$this->request['tab'] = IPSText::alphanumericalClean( $this->request['tab'] );
		$this->request['area'] = IPSText::alphanumericalClean( $this->request['area'] );
		
		//-----------------------------------------
		// Set up some basics...
		//-----------------------------------------

		$_TAB  = ( $this->request['tab'] )  ? $this->request['tab']  : 'core';
		$_AREA = ( $this->request['area'] ) ? $this->request['area'] : 'settings';
		$_DO   = ( $this->request['do'] )   ? $this->request['do']   : 'show';
		$_FUNC = ( $_DO == 'show' ) ? 'showForm' : ( $_DO == 'save' ? 'saveForm' : $_DO );
		$tabs  = array();
		$errors = array();
		
		//-----------------------------------------
		// Got a plug in?
		//-----------------------------------------

		require_once( IPS_ROOT_PATH . 'sources/interfaces/interface_usercp.php' );

		$EXT_DIR  = IPSLib::getAppDir(  $_TAB ) . '/extensions';
		$_CLASS = 'usercpForms_' . $_TAB;

		if ( ! file_exists($EXT_DIR . '/usercpForms.php') )
		{
			get_error("Forum do not have such extension!");
		}
		
		
		//-----------------------------------------
		// Cycle through applications and load
		// usercpForm extensions
		//-----------------------------------------
		foreach( ipsRegistry::$applications as $app_dir => $app_data )
		{
			// Check that the application is installed.
			if ( !IPSLib::appIsInstalled( $app_dir ) )
			{
				continue;
			}

			$ext_dir  = IPSLib::getAppDir( $app_dir ) . '/extensions';

			// Make sure the extension exists
			if ( !file_exists( $ext_dir . '/usercpForms.php' ) )
			{
				continue;
			}

			require_once( $ext_dir . '/usercpForms.php' );
			$__class = 'usercpForms_' . $app_dir;

			//-----------------------------------------
			// Support for extending usercpForms
			//-----------------------------------------
			if ( file_exists( $ext_dir . '/usercpFormsExt.php' ) )
			{
				require_once( $ext_dir . '/usercpFormsExt.php' );

				//-----------------------------------------
				// The class must exist and extend
				// the usercpForm class to be valid.
				//-----------------------------------------
				if ( class_exists( 'usercpFormsExt_' . $app_dir ) )
				{
					$parent = get_parent_class( 'usercpFormsExt_' . $app_dir );

					if ( $parent == $__class )
					{
						$__class = 'usercpFormsExt_' . $app_dir;
					}
				}
			}

			$_usercp_module = new $__class();
			$_usercp_module->makeRegistryShortcuts( $this->registry );

			if ( is_callable( array( $_usercp_module, 'init' ) ) )
			{
				$_usercp_module->init();

				/* Set default area? */
				if (  ( $_TAB == $app_dir ) AND ! isset( $_REQUEST['area'] ) )
				{
					if ( isset( $_usercp_module->defaultAreaCode ) )
					{
						$this->request['area'] = $_AREA = $_usercp_module->defaultAreaCode;
					}
				}
			}

			if ( is_callable( array( $_usercp_module, 'getLinks' ) ) )
			{
				$tabs[ $app_dir ]['_name'] = $_usercp_module->tab_name ? $_usercp_module->tab_name : IPSLib::getAppTitle( $app_dir );
				$tabs[ $app_dir ]['_menu'] = $_usercp_module->getLinks();

				if ( ! $tabs[ $app_dir ]['_menu'] )
				{
					unset( $tabs[ $app_dir ] );
				}

				/* Add in 'last' element */
				$tabs[ $app_dir ]['_menu'][ count( $tabs[ $app_dir ]['_menu'] ) - 1 ]['last'] = 1;

//				/* This nav? */
//				if ( ! count( $_thisNav ) AND $app_dir == $_TAB )
//				{
//					foreach( $tabs[ $app_dir ]['_menu'] as $_navData )
//					{
//						if ( $_navData['url'] == 'area=' . $_AREA )
//						{
//							$_thisNav = array( 'app=core&amp;module=usercp&amp;tab=' . $_TAB . '&amp;area=' . $_AREA, $_navData['title'] );
//						}
//					}
//				}
			}
		}

		//-----------------------------------------
		// Set up basic navigation
		//-----------------------------------------

		$this->_nav[] = array( $this->lang->words['t_title'], '&amp;app=core&amp;module=usercp' );
		$this->_nav[] = array( $this->lang->words['tab__' . $_TAB ] ? $this->lang->words['tab__' . $_TAB ] : IPSLib::getAppTitle( $_TAB ) , '&amp;app=core&amp;module=usercp&amp;tab=' . $_TAB );

		if ( isset( $_thisNav[0] ) )
		{
			$this->_nav[] = array( $_thisNav[1], $_thisNav[0] );
		}

		//-----------------------------------------
		// Begin initilization routine for extension
		//-----------------------------------------
//		require_once( $EXT_DIR . '/usercpForms.php' );
//
//		//-----------------------------------------
//		// Support for extending usercpForms
//		//-----------------------------------------
//		if ( file_exists( $EXT_DIR . '/usercpFormsExt.php' ) )
//		{
//			require_once( $EXT_DIR . '/usercpFormsExt.php' );
//
//			//-----------------------------------------
//			// The class must exist and extend
//			// the usercpForm class to be valid.
//			//-----------------------------------------
//			if ( class_exists( 'usercpFormsExt_' . $_TAB ) )
//			{
//				$parent = get_parent_class( 'usercpFormsExt_' . $_TAB );
//
//				if ( $parent == $_CLASS )
//				{
//					$_CLASS = 'usercpFormsExt_' . $_TAB;
//				}
//			}
//		}
		
		#################################
		require_once "usercpForms.php";
    	$_CLASS = 'mobi_usercpForms_' . $_TAB;
		#################################
		
		$usercp_module =  new $_CLASS();
		$usercp_module->makeRegistryShortcuts( $this->registry );
		$usercp_module->init();

		if ( ( $_DO == 'saveForm' || $_DO == 'showForm' ) AND ! is_callable( array( $usercp_module, $_FUNC ) ) )
		{
			get_error("Parameters Error!");
		}

		//-----------------------------------------
		// Run it...
		//-----------------------------------------

		if ( $_FUNC == 'showForm' )
		{
			//-----------------------------------------
			// Facebook email
			//-----------------------------------------
 
			//@facebook concession
			if ( IPSLib::fbc_enabled() === TRUE )
			{
				if ( ! $this->memberData['fb_emailallow'] AND strstr( $this->memberData['email'], '@proxymail.facebook.com' ) )
				{
					require_once( IPS_ROOT_PATH . 'sources/classes/facebook/connect.php' );
					$fb = new facebook_connect( $this->registry );

					try
					{
						$fb->testConnectSession();
						$result = $fb->users_hasAppPermission( 'email' );

						IPSMember::save( $this->memberData['member_id'], array( 'core' => array( 'fb_emailallow' => intval( $result ) ) ) );
					}
					catch( Exception $error )
					{
					}
				}
			}

			return $usercp_module->showForm( $_AREA );
		}
		else if ( $_FUNC == 'saveForm' )
		{
			//-----------------------------------------
			// Check secure key...
			//-----------------------------------------

//			if ( $this->request['secure_hash'] != $this->member->form_hash )
//			{
//				$html = $usercp_module->showForm( $_AREA );
//				$errors[] = $this->lang->words['securehash_not_secure'];
//			}
//			else
//			{

				$errors = $usercp_module->saveForm( $_AREA );

				$do = ( $usercp_module->do_url ) ? $usercp_module->do_url : 'show';

				if ( is_array( $errors ) AND count( $errors ) )
				{
				    get_error($errors[0]);
					//$html = $usercp_module->showForm( $_AREA, $errors );
				}
				else if ( $usercp_module->ok_message )
				{
				    return true;
					//$this->registry->getClass('output')->redirectScreen( $usercp_module->ok_message, $this->settings['base_url'] . 'app=' . IPS_APP_COMPONENT . '&module=usercp&tab=' . $_TAB . '&area=' . $_AREA . '&do='.$do.'&saved=1', 1 );
				}
				else
				{
				    return true;
					//$this->registry->getClass('output')->silentRedirect( $this->settings['base_url_with_app'] . 'module=usercp&tab=' . $_TAB . '&area=' . $_AREA . '&do='.$do.'&saved=1'.'&_r='.time() );
				}
//			}
		}
		else
		{
			if ( ! is_callable( array( $usercp_module, 'runCustomEvent' ) ) )
			{
				get_error("Problem in Forum!");
				//$html = $usercp_module->showForm( $_AREA );
				//$errors[] = $this->lang->words['called_invalid_function'];
			}
			else
			{
				$result = $usercp_module->runCustomEvent( $_AREA );
			}
		}
		if ( is_callable( array( $usercp_module, 'resetArea' ) ) )
		{
			$_AREA = $usercp_module->resetArea( $_AREA );
		}
		
		return $result;
	}
	
}	
