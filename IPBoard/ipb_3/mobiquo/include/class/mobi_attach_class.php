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
require_once(IPS_ROOT_PATH . 'applications/core/modules_public/attach/attach.php');

class mobi_attach_upload extends public_core_attach_attach
{
	public function doExecute( ipsRegistry $registry ) 
	{
		/* AJAX Class */
		require_once( IPS_KERNEL_PATH . '/classAjax.php' );
		$this->ajax = new classAjax();
		
		/* Attachment Class */
		//require_once( IPSLib::getAppDir( 'core' ) . '/sources/classes/attach/class_attach.php' );
		require_once( 'mobi_class_attach.php' );
		$this->class_attach = new mobi_class_attach( $registry );
				
		/* What to do... */
		switch( $this->request['do'] )
		{
			case 'attach_upload_show':
				$this->ajax->returnHtml( $this->attachmentUploadShow() );
			break;
			
			case 'attach_upload_process':
				$this->attachmentUploadProcess();
			break;
			
			case 'attach_upload_remove':
				return $this->attachmentUploadRemove();
			break;
			
			/* IFrame based  upload */
			/*
			case 'attachiFrame':
				$this->attachiFrame();
			break;
			*/
			case 'attachUploadiFrame':
				  return $this->attachUploadiFrame();
			break;
			/*
			default:
				$this->showPostAttachment();
			break;
			*/
		}
	}
	
		/**
	 * Perform the actual upload
	 *
	 * @access	public
	 * @return	void
	 */
	public function attachUploadiFrame()
	{
		/* INIT */
		$attach_post_key      = trim( IPSText::alphanumericalClean( $this->request['attach_post_key'] ) );
		$attach_rel_module    = trim( IPSText::alphanumericalClean( $this->request['attach_rel_module'] ) );
		$attach_rel_id        = intval( $this->request['attach_rel_id'] );
		$attach_current_items = '';
		
		$this->registry->getClass( 'class_localization')->loadLanguageFile( array( 'public_post' ), 'forums' );
		
		/* INIT module */
		$this->class_attach->type            = $attach_rel_module;
		$this->class_attach->attach_post_key = $attach_post_key;
		$this->class_attach->attach_rel_id   = $attach_rel_id;
		$this->class_attach->init();
		
		/* Process upload */
		$insert_id = $this->class_attach->processUpload();

		if( $this->class_attach->error )
		{
			get_error($this->class_attach->error);
		}
		
		return $insert_id;
		/* Got an error? */
		/*
		if( $this->class_attach->error )
		{
			$JSON = $this->attachmentUploadShow( $this->class_attach->error, 1, $insert_id );
		}
		else
		{
			$JSON = $this->attachmentUploadShow( 'upload_ok', 0, $insert_id );
		}

		$this->ajax->returnHtml( $this->registry->output->getTemplate( 'post' )->attachiFrame( $JSON, $attach_rel_id ) );
		*/
	}
	
	public function attachmentUploadRemove()
	{
		/* INIT */
		$attach_post_key      = trim( IPSText::alphanumericalClean( $this->request['attach_post_key'] ) );
		$attach_rel_module    = trim( IPSText::alphanumericalClean( $this->request['attach_rel_module'] ) );
		$attach_rel_id        = intval( $this->request['attach_rel_id'] );
		$attach_id            = intval( $this->request['attach_id'] );
		
		/* Setup Module */
		$this->class_attach->type            = $attach_rel_module;
		$this->class_attach->attach_post_key = $attach_post_key;
		$this->class_attach->attach_rel_id   = $attach_rel_id;
		$this->class_attach->attach_id       = $attach_id;
		$this->class_attach->init();
		
		/* Remove the attachment */
		$removed = $this->class_attach->removeAttachment();
		
        return $removed;
	}
}
