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
require_once( IPSLib::getAppDir( 'forums' ) . "/sources/classes/post/classPost.php" );

class mobi_classPost extends classPost
{
	/**
	 * Cheap and probably nasty way of killing quotes
	 *
	 * @access	private
	 * @return  string
	 */
	protected function _recursiveKillQuotes( $t )
	{
		return IPSText::getTextClass( 'bbcode' )->stripQuotes( $t );
	}
	
	
	/**
	 * Alter the topic based on moderation options, etc
	 *
	 * @param	array 	Topic data from the DB
	 * @return	array 	Altered topic data
	 * @access	private
	 */
	protected function _modTopicOptions( $topic )
	{
		/* INIT */
		$topic['state'] = ( $topic['state'] == 'closed' ) ? 'closed' : 'open';
		
		if ( ( $this->request['mod_options'] != "") or ( $this->request['mod_options'] != 'nowt' ) )
		{			
			if ($this->request['mod_options'] == 'pin')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or $this->moderator['pin_topic'] == 1)
				{
					$topic['pinned'] = 1;
					
					$this->addToModLog( $this->lang->words['modlogs_pinned'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'unpin')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or $this->moderator['unpin_topic'] == 1)
				{
					$topic['pinned'] = 0;
					
					$this->addToModLog( $this->lang->words['modlogs_unpinned'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'close')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or $this->moderator['close_topic'] == 1)
				{
					$topic['state'] = 'closed';
					
					$this->addToModLog( $this->lang->words['modlogs_closed'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'open')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or $this->moderator['open_topic'] == 1)
				{
					$topic['state'] = 'open';
					
					$this->addToModLog( $this->lang->words['modlogs_opened'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'move')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or $this->moderator['move_topic'] == 1)
				{
					$topic['_returnToMove'] = 1;
				}
			}
			else if ($this->request['mod_options'] == 'pinclose')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or ( $this->moderator['pin_topic'] == 1 AND $this->moderator['close_topic'] == 1 ) )
				{
					$topic['pinned'] = 1;
					$topic['state']  = 'closed';
					
					$this->addToModLog( $this->lang->words['modlogs_pinclose'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'pinopen')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or ( $this->moderator['pin_topic'] == 1 AND $this->moderator['open_topic'] == 1 ) )
				{
					$topic['pinned'] = 1;
					$topic['state']  = 'open';
					
					$this->addToModLog( $this->lang->words['modlogs_pinopen'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'unpinclose')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or ( $this->moderator['unpin_topic'] == 1 AND $this->moderator['close_topic'] == 1 ) )
				{
					$topic['pinned'] = 0;
					$topic['state']  = 'closed';
					
					$this->addToModLog( $this->lang->words['modlogs_unpinclose'], $topic['title']);
				}
			}
			else if ($this->request['mod_options'] == 'unpinopen')
			{
				if ($this->getAuthor('g_is_supmod') == 1 or ( $this->moderator['unpin_topic'] == 1 AND $this->moderator['open_topic'] == 1 ) )
				{
					$topic['pinned'] = 0;
					$topic['state']  = 'open';
					
					$this->addToModLog( $this->lang->words['modlogs_unpinopen'], $topic['title']);
				}
			}
		}
		
		//-----------------------------------------
		// Check close times...
		//-----------------------------------------
		
		if ( $topic['state'] == 'open' AND ( $this->times['close'] AND $this->times['close'] <= time() ) )
		{
			$topic['state'] = 'closed';
		}
		else if ( $topic['state'] == 'closed' AND ( $this->times['open'] AND $this->times['open'] >= time() ) )
		{
			$topic['state'] = 'open';
		}
		
		if ( $topic['state'] == 'open' AND ( $this->times['open'] OR $this->times['close'] )
				AND ( $this->times['close'] <= time() OR ( $this->times['open'] > time() AND ! $this->times['close'] ) ) )
		{
			$topic['state'] = 'closed';
		}
		
		if ( $topic['state'] == 'open' AND ( $this->times['open'] AND $this->times['close'] )
				AND ( $this->times['close'] >= $this->times['open'] ) )
		{
			$topic['state'] = 'closed';
		}
		
		$topic['state'] = ( $topic['state'] == 'closed' ) ? 'closed' : 'open';
		
		return $topic;
	}
	
		/**
	 * Performs set up for adding a reply
	 *
	 * @access	public
	 * @return	array    Topic data
	 *
	 * Exception Error Codes
	 * NO_SUCH_TOPIC		No topic could be found matching the topic ID and forum ID
	 * NO_REPLY_PERM		Viewer does not have permission to reply
	 * TOPIC_LOCKED		The topic is locked
	 * NO_REPLY_POLL		This is a poll only topic
	 * NO_TOPIC_ID		No topic ID (durrrrrrrrrrr)
	 */
	public function replySetUp()
	{
		//-----------------------------------------
		// Check for a topic ID
		//-----------------------------------------
		if( ! $this->getTopicID() )
		{
			throw new Exception( 'NO_TOPIC_ID' );
		}
		
        /* Minimum Posts Check */        
		if( $this->getForumData('min_posts_post') && $this->getForumData('min_posts_post') > $this->getAuthor('posts') )
		{
			if ( $this->_bypassPermChecks !== TRUE )
			{
				throw new Exception( 'Posting Not Enough Posts!' );
				//$this->registry->output->showError( 'posting_not_enough_posts', 103140 );
			}
		}		
		
		//-----------------------------------------
		// Set up post key
		//-----------------------------------------
		
		$this->post_key = ( $this->request['attach_post_key'] AND $this->request['attach_post_key'] != "" ) ? $this->request['attach_post_key'] : md5( microtime() );
		
		//-----------------------------------------
		// Load and set topic
		//-----------------------------------------

		$topic = $this->getTopicData();
		
		if( ! $topic['tid'] )
		{
			throw new Exception("NO_SUCH_TOPIC");
		}
		
		//-----------------------------------------
		// Checks
		//-----------------------------------------
		
		if( $topic['poll_state'] == 'closed' and $this->getAuthor('g_is_supadmin') != 1 )
		{
			throw new Exception( 'NO_REPLY_PERM' );
		}
		
		if( $topic['starter_id'] == $this->getAuthor('member_id') )
		{
			if( ! $this->getAuthor('g_reply_own_topics'))
			{
				if ( $this->_bypassPermChecks !== TRUE )
				{
					throw new Exception( 'NO_REPLY_PERM' );
				}
			}
		}

		if( $topic['starter_id'] != $this->getAuthor('member_id') )
		{
			if( ! $this->getAuthor('g_reply_other_topics') )
			{
				if ( $this->_bypassPermChecks !== TRUE )
				{
					throw new Exception( 'NO_REPLY_PERM' );
				}
			}
		}

		$perm_id	= $this->getAuthor('org_perm_id') ? $this->getAuthor('org_perm_id') : $this->getAuthor('g_perm_id');
		$perm_array = explode( ",", $perm_id );

		if ( $this->registry->permissions->check( 'reply', $this->getForumData(), $perm_array ) === FALSE )
		{
			if ( $this->_bypassPermChecks !== TRUE )
			{
				throw new Exception( 'NO_REPLY_PERM' );
			}
		}
		
		if( $topic['state'] != 'open')
		{
			if( $this->getAuthor('g_post_closed') != 1 )
			{
				throw new Exception( 'TOPIC_LOCKED' );
			}
		}
		
		if( isset($topic['poll_only']) AND $topic['poll_only'] )
		{
			if( $this->getAuthor('g_post_closed') != 1 )
			{
				throw new Exception( 'NO_REPLY_POLL' );
			}
		}
		
		//-----------------------------------------
		// POLL BOX ( Either topic starter or admin)
		// and without a current poll
		//-----------------------------------------
		
		if ( $this->can_add_poll )
		{
			$this->can_add_poll = 0;
			
			if ( ! $topic['poll_state'] )
			{
				if ( $this->getAuthor('member_id') AND $this->getPublished() )
				{
					if ( $this->getAuthor('g_is_supmod') == 1 )
					{
						$this->can_add_poll = 1;
					}
					else if ( $topic['starter_id'] == $this->getAuthor('member_id') )
					{
						if ( ($this->settings['startpoll_cutoff'] > 0) AND ( $topic['start_date'] + ($this->settings['startpoll_cutoff'] * 3600) > time() ) )
						{
							$this->can_add_poll = 1;
						}
					}
				}
			}
		}
		
		//-----------------------------------------
		// Mod options...
		//-----------------------------------------
		
		$topic = $this->_modTopicOptions( $topic );
		
		return $topic;
	}
	
		/**
	 * Performs set up for editing a post
	 *
	 * @access	public
	 * @return	array    Topic data
	 *
	 * Exception Error Codes
	 * NO_SUCH_TOPIC		No topic could be found matching the topic ID and forum ID
	 * NO_SUCH_POST		Post could not be loaded
	 * NO_EDIT_PERM		Viewer does not have permission to edit
	 * TOPIC_LOCKED		The topic is locked
	 * NO_REPLY_POLL		This is a poll only topic
	 * NO_TOPIC_ID		No topic ID (durrrrrrrrrrr)
	 */
	public function editSetUp()
	{
		//-----------------------------------------
		// Check for a topic ID
		//-----------------------------------------
		
		if ( ! $this->getTopicID() )
		{
			throw new Exception( 'NO_TOPIC_ID' );
		}
		
		//-----------------------------------------
		// Load and set topic
		//-----------------------------------------
		
		$forum_id = intval( $this->getForumID() );
		
		$topic = $this->getTopicData();

		if ( ! $topic['tid'] )
		{
			throw new Exception("NO_SUCH_TOPIC");
		}
		
		if ( $forum_id != $topic['forum_id'] )
		{
			throw new Exception("NO_SUCH_TOPIC");
		}

		//-----------------------------------------
		// Load the old post
		//-----------------------------------------
		
		$this->_originalPost = $this->DB->buildAndFetch( array( 'select' => '*', 'from' => 'posts', 'where' => "pid=" . $this->getPostID() ) );

		if ( ! $this->_originalPost['pid'] )
		{
			throw new Exception( "NO_SUCH_POST" );
		}

		if ( $this->getIsAjax() === TRUE )
		{
			$this->setSettings( array( 'enableSignature'	=> intval($this->_originalPost['use_sig']),
									   'enableEmoticons'	=> intval($this->_originalPost['use_emo']),
									   'post_htmlstatus'	=> $this->getSettings('post_htmlstatus') !== '' ? $this->getSettings('post_htmlstatus') : intval($this->_originalPost['post_htmlstate']),
							) 		);
		}

		//-----------------------------------------
		// Same topic?
		//-----------------------------------------
		
		if ( $this->_originalPost['topic_id'] != $topic['tid'] )
		{
			throw new Exception( "Posting Mismatch Topic!" );
            //ipsRegistry::getClass('output')->showError( 'posting_mismatch_topic', 20311 );
        }
		
		//-----------------------------------------
		// Generate post key (do we have one?)
		//-----------------------------------------
		
		if ( ! $this->_originalPost['post_key'] )
		{
			//-----------------------------------------
			// Generate one and save back to post and attachment
			// to ensure 1.3 < compatibility
			//-----------------------------------------
			
			$this->post_key = md5(microtime());
			
			$this->DB->update( 'posts'      , array( 'post_key' => $this->post_key ), 'pid='.$this->_originalPost['pid'] );
			$this->DB->update( 'attachments', array( 'attach_post_key' => $this->post_key ), "attach_rel_module='post' AND attach_rel_id=".$this->_originalPost['pid'] );
		}
		else
		{
			$this->post_key = $this->_originalPost['post_key'];
		}
		
		//-----------------------------------------
		// Lets do some tests to make sure that we are
		// allowed to edit this topic
		//-----------------------------------------
		
		$_canEdit = 0;
		
		if ( $this->getAuthor('g_is_supmod') )
		{
			$_canEdit = 1;
		}
		
		if ( isset( $this->moderator['edit_post'] ) && $this->moderator['edit_post'] )
		{
			$_canEdit = 1;
		}
		
		if ( ($this->_originalPost['author_id'] == $this->getAuthor('member_id')) and ($this->getAuthor('g_edit_posts')) )
		{ 
			//-----------------------------------------
			// Have we set a time limit?
			//-----------------------------------------
			
			if ( $this->getAuthor('g_edit_cutoff') > 0 )
			{
				if ( $this->_originalPost['post_date'] > ( time() - ( intval($this->getAuthor('g_edit_cutoff')) * 60 ) ) )
				{
					$_canEdit = 1;
				}
			}
			else
			{
				$_canEdit = 1;
			}
		}
		
		//-----------------------------------------
		// Is the topic locked?
		//-----------------------------------------
		
		if ( ( $topic['state'] != 'open' ) and ( ! $this->memberData['g_is_supmod'] AND ! $this->moderator['edit_post'] ) )
		{
			if ( $this->memberData['g_post_closed'] != 1 )
			{
				$_canEdit = 0;
			}
		}
		
		if ( $_canEdit != 1 )
		{
			if ( $this->_bypassPermChecks !== TRUE )
			{
				throw new Exception( "NO_EDIT_PERMS" );
			}
		}
		
		//-----------------------------------------
		// If we're not a mod or admin
		//-----------------------------------------

		if ( ! $this->getAuthor('g_is_supmod') AND ! $this->moderator['edit_post'] )
		{
			$perm_id	= $this->getAuthor('org_perm_id') ? $this->getAuthor('org_perm_id') : $this->getAuthor('g_perm_id');
			$perm_array = explode( ",", $perm_id );
			if ( $this->registry->permissions->check( 'reply', $this->getForumData(), $perm_array ) !== TRUE )
			{
				$_ok = 0;
			
				//-----------------------------------------
				// Are we a member who started this topic
				// and are editing the topic's first post?
				//-----------------------------------------
			
				if ( $this->getAuthor('member_id') )
				{
					if ( $topic['topic_firstpost'] )
					{
						$_post = $this->DB->buildAndFetch( array( 'select' => 'pid, author_id, topic_id',
																  'from'   => 'posts',
																  'where'  => 'pid=' . intval( $topic['topic_firstpost'] ) ) );
																			
						if ( $_post['pid'] AND $_post['topic_id'] == $topic['tid'] AND $_post['author_id'] == $this->getAuthor('member_id') )
						{
							$_ok = 1;
						}
					}
				}
			
				if ( ! $_ok )
				{
					if ( $this->_bypassPermChecks !== TRUE )
					{
						throw new Exception( "NO_EDIT_PERMS" );
					}
				}
			}
		}
		
		//-----------------------------------------
		// Do we have edit topic abilities?
		//-----------------------------------------
		
		# For edit, this means there is a poll and we have perm to edit
		$this->can_add_poll_mod = 0;
		
		if ( $this->_originalPost['new_topic'] == 1 )
		{
			if ( $this->getAuthor('g_is_supmod') == 1 )
			{
				$this->edit_title       = 1;
				$this->can_add_poll_mod = 1;
			}
			else if ( $this->moderator['edit_topic'] == 1 )
			{
				$this->edit_title       = 1;
				$this->can_add_poll_mod = 1;
			}
			else if ( $this->getAuthor('g_edit_topic') == 1 AND ($this->_originalPost['author_id'] == $this->getAuthor('member_id')) )
			{
				$this->edit_title = 1;
			}
		}
		else
		{
			$this->can_add_poll = 0;
		}
		
		return $topic;
	}
	
    public function sendOutQuoteNotifications( $post, $subscriptionSentTo = array() )
    {
        if ($this->DB->checkForTable( 'tapatalk_users' ) && file_exists( DOC_IPS_ROOT_PATH . $this->settings['tapatalk_directory'] . '/lib/class_push.php' ))
        {
            $connect_status = function_exists('curl_init') || ini_get('allow_url_fopen');
            $classToLoad    = IPSLib::loadLibrary( DOC_IPS_ROOT_PATH . $this->settings['tapatalk_directory'] . '/lib/class_push.php', 'tapatalk_push' );
            $notifyLibrary  = new $classToLoad( $this->registry );
            $notifyLibrary->notifyTag( $post, $subscriptionSentTo, $connect_status );
        }
        
        parent::sendOutQuoteNotifications( $post, $subscriptionSentTo );
    }
}

?>