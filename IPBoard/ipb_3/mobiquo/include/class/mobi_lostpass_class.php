<?php
defined('IN_MOBIQUO') or exit;
require_once (IPS_ROOT_PATH . 'applications/core/modules_public/global/lostpass.php');
class mobi_lostpss extends public_core_global_lostpass {
	
	public $tt_reulst_text;
	/*
	 * Class entry point
	 *
	 * @param	object		Registry reference
	 * @return	@e void		[Outputs to screen/redirects]
	 */
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
		/* Load language */
		$this->registry->class_localization->loadLanguageFile( array( 'public_register' ), 'core' );

    	//-----------------------------------------
    	// Meta tags
    	//-----------------------------------------
    	
    	$this->registry->output->addMetaTag( 'robots', 'noindex' );
    	
    	
		/* What to do */
		switch( $this->request['do'] )
		{
			case '11':
				$result = $this->lostPasswordEnd();
			break;
		}
		return $result;			
	}
	
		
	/**
	 * Completes the lost password request form
	 *
	 * @return	@e void
	 */
	public function lostPasswordEnd()
	{
		
		/* Back to the usual programming! :o */
		if( $this->request['member_name'] == "" AND $this->request['email_addy'] == "" )
		{
			get_error('username is not allow empty');
		}
		
		/* Check for input and it's in a valid format. */
		$member_name = trim( strtolower( $this->request['member_name'] ) );
		$email_addy  = trim( strtolower( $this->request['email_addy'] ) );
		
		if( $member_name == "" AND $email_addy == "" )
		{
			get_error('username is invalid');
		}
		
		/* Attempt to get the user details from the DB */
		if( $member_name )
		{
			$this->DB->build( array( 'select' => 'members_display_name, name, member_id, email, member_group_id', 'from' => 'members', 'where' => "members_l_username='{$member_name}'" ) );
			$this->DB->execute();
		}
		else if( $email_addy )
		{
			$this->DB->build( array( 'select' => 'members_display_name, name, member_id, email, member_group_id', 'from' => 'members', 'where' => "email='{$email_addy}'" ) );
			$this->DB->execute();
		}

		if ( ! $this->DB->getTotalRows() )
		{
			get_error('username is not exist');
		}
		else
		{
			$member = $this->DB->fetch();
			//@todo 
			if(isset($_POST['tt_token']) && isset($_POST['tt_code']))
			{
				$user_email = tt_register_verify($_POST['tt_token'], $_POST['tt_code']);
		    	if(!empty($user_email->email) && ($user_email->email == $member['email']))
		    	{
		    		return 'verified';
		    	}
			}
			/* Is there a validation key? If so, we'd better not touch it */
			if( $member['member_id'] == "" )
			{
				get_error('username is not exist');
			}
			
			$validate_key = md5( IPSMember::makePassword() . uniqid( mt_rand(), TRUE ) );
			
			/* Get rid of old entries for this member */
			$this->DB->delete( 'validating', "member_id={$member['member_id']} AND lost_pass=1" );
			
			/* Update the DB for this member. */
			$db_str = array('vid'         => $validate_key,
							'member_id'   => $member['member_id'],
							'temp_group'  => $member['member_group_id'],
							'entry_date'  => time(),
							'coppa_user'  => 0,
							'lost_pass'   => 1,
							'ip_address'  => $this->member->ip_address,
						   );
					
			/* Are they already in the validating group? */
			if( $member['member_group_id'] != $this->settings['auth_group'] )
			{
				$db_str['real_group'] = $member['member_group_id'];
			}
						   
			$this->DB->insert( 'validating', $db_str );
			
			/* Send out the email. */
    		IPSText::getTextClass('email')->getTemplate( 'lost_pass', $member['language'] );
				
			IPSText::getTextClass('email')->buildMessage( array(
											'USERNAME'   => $member['name'],
											'NAME'       => $member['members_display_name'],
											'THE_LINK'   => $this->registry->getClass('output')->buildUrl( "app=core&module=global&section=lostpass&do=sendform&uid={$member['member_id']}&aid={$validate_key}", 'publicNoSession' ),
											'MAN_LINK'   => $this->registry->getClass('output')->buildUrl( 'app=core&module=global&section=lostpass&do=sendform', 'publicNoSession' ),
											'EMAIL'      => $member['email'],
											'ID'         => $member['member_id'],
											'CODE'       => $validate_key,
											'IP_ADDRESS' => $this->member->ip_address,
										)
									);
										
			IPSText::getTextClass('email')->subject = $this->lang->words['lp_subject'] . ' ' . $this->settings['board_name'];
			IPSText::getTextClass('email')->to      = $member['email'];			
			IPSText::getTextClass('email')->sendMail();
			$this->tt_reulst_text = $this->lang->words['lpass_text'];
			return true;
		}
    }	
	
	
}