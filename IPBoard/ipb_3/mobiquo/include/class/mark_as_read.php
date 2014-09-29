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
require_once (IPS_ROOT_PATH . 'applications/forums/modules_public/forums/markasread.php');
class mobi_markasread extends public_forums_forums_markasread
{
		/**
	 * Main execution point
	 *
	 * @access	public
	 * @param	object	ipsRegistry reference
	 * @return	void
	 */
	public function doExecute( ipsRegistry $registry )
	{
		//-----------------------------------------
		// What are we doing?
		//-----------------------------------------
		
		switch( $this->request['marktype'] )
		{
			default:
			
			case 'forum':
				return $this->markForumAsRead();
			break;
			
			case 'all':
				$this->markBoardAsRead();
		}
	}
	
	/**
	 * Mark all forums and topics as read
	 *
	 * @access	public
	 * @return	void
	 */
 	public function markBoardAsRead()
 	{
		$this->registry->classItemMarking->disableInstantSave();
        
		//-----------------------------------------
        // Reset board markers
        //-----------------------------------------
        
		foreach( ipsRegistry::$applications as $app_dir => $data )
		{
			if ( isset( $data['app_enabled'] ) AND $data['app_enabled'] )
			{
				$this->registry->classItemMarking->markAppAsRead( $app_dir );
			}
		}
	}
	
	public function markForumAsRead()
	{
		$forum_id      = intval( $this->request['forumid'] );
        $return_to_id  = intval( $this->request['returntoforumid'] );
        $forum_data    = $this->registry->getClass('class_forums')->forum_by_id[ $forum_id ];
        $children      = $this->registry->getClass('class_forums')->forumsGetChildren( $forum_data['id'] );
        $save          = array();
        
        if ( ! $forum_data['id'] )
        {
        	get_error('markread_no_id');
        }

		/* Turn off instant updates and write back tmp markers in destructor */
		$this->registry->classItemMarking->disableInstantSave();
        
        //-----------------------------------------
        // Come from the index? Add kids
        //-----------------------------------------
       
        if ( $this->request['i'] )
        {
			if ( is_array( $children ) and count($children) )
			{
				foreach( $children as $id )
				{
					$this->registry->classItemMarking->markRead( array( 'forumID' => $id ) );
				}
			}
        }
        
        $this->registry->classItemMarking->markRead( array( 'forumID' => $forum_id ) );
    }
}
