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
require_once( IPSLib::getAppDir( 'core' ) . '/sources/classes/attach/class_attach.php' );

class mobi_class_attach extends class_attach	
{
	/**
	 * Swaps the HTML for the nice attachments.
	 *
	 * @access	public
	 * @param	array 	Array of HTML blocks to convert: [ rel_id => $html ]
	 * @param	array 	Relationship ids
	 * @param	string	Skin group to use
	 * @return	array 	Array of converted HTML blocks and attach code: [ id => $html ]
	 */
	public function renderAttachments( $htmlArray, $rel_ids=array(), $skin_name='topic' )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$attach_ids              = array();
		$map_attach_id_to_rel_id = array();
		$final_out               = array();
		$final_blocks            = array();
		$returnArray			 = array();
		$_seen                   = 0;
		
		//-----------------------------------------
		// Check..
		//-----------------------------------------
		
		if ( ! is_array( $htmlArray ) )
		{
			$htmlArray = array( 0 => $htmlArray );
		}
		
		//-----------------------------------------
		// Rel ids
		//-----------------------------------------
		
		if ( ! is_array( $rel_ids ) OR ! count( $rel_ids ) )
		{
			$rel_ids = array_keys( $htmlArray );
		}
		
		//-----------------------------------------
		// Parse HTML blocks for attach ids
		// [attachment=32:attachFail.gif]
		//-----------------------------------------
		
		foreach( $htmlArray as $id => $html )
		{
			$returnArray[ $id ] = array( 'html' => $html, 'attachmentHtml' => '' , 'attachments' => array());
			
			preg_match_all( "#\[attachment=(\d+?)\:(?:[^\]]+?)\]#is", $html, $match );
			
			if ( is_array( $match[0] ) and count( $match[0] ) )
			{
				for ( $i = 0 ; $i < count( $match[0] ) ; $i++ )
				{
					if ( intval($match[1][$i]) == $match[1][$i] )
					{
						$attach_ids[ $match[1][$i] ]                = $match[1][$i];
						$map_attach_id_to_rel_id[ $match[1][$i] ][] = $id;
					}
				}
			}
		}
		
		//-----------------------------------------
		// Get data from the plug in
		//-----------------------------------------
		
		$rows = $this->plugin->renderAttachment( $attach_ids, $rel_ids, $this->attach_post_key );
		
		//-----------------------------------------
		// Got anything?
		//-----------------------------------------
		
		if ( is_array( $rows ) AND count( $rows ) )
		{
			//-----------------------------------------
			// Got attachment types?
			//-----------------------------------------
			if ( ! is_array( $this->registry->cache()->getCache('attachtypes') ) )
			{
				$attachtypes = array();

				$this->DB->build( array( 'select' => 'atype_extension,atype_mimetype,atype_img', 'from' => 'attachments_type' ) );
				$outer = $this->DB->execute();

				while ( $r = $this->DB->fetch( $outer ) )
				{
					$attachtypes[ $r['atype_extension'] ] = $r;
				}
				
				$this->registry->cache()->updateCacheWithoutSaving( 'attachtypes', $attachtypes );
			}
			
			$_seen_rows = 0;
		
			foreach( $rows as $_attach_id => $row )
			{
				//-----------------------------------------
				// INIT
				//-----------------------------------------
				
				$row = $rows[ $_attach_id ];
				
				if( $this->attach_rel_id != $row['attach_rel_id'] )
				{
					// Reset if we are onto a new post..
					$_seen_rows = 0;
				}

				$this->attach_rel_id = $row['attach_rel_id'];
				
				if ( ! isset( $final_blocks[ $row['attach_rel_id'] ] ) )
				{
					$final_blocks[ $row['attach_rel_id'] ] = array( 'attach' => '', 'thumb' => '', 'image' => '' );
				}
				
				$returnArray[ $row['attach_rel_id'] ]['attachments'][$row['attach_id']] = $row;
				
				//-----------------------------------------
				// Is it an image, and are we viewing the image in the post?
				//-----------------------------------------
				
				if ( $this->settings['show_img_upload'] and $row['attach_is_image'] )
				{
					if ( $this->attach_settings['siu_thumb'] AND $row['attach_thumb_location'] AND $row['attach_thumb_width'] )
					{
						//-----------------------------------------
						// Make sure we've not seen this ID
						//-----------------------------------------
						
						$row['_attach_id'] = $row['attach_id'] . '-' . str_replace( ".", "-", microtime( true ) );
						
						$not_inline = $_seen_rows > 0 ? $_seen_rows%$this->settings['topic_attach_no_per_row'] : 1;
						
						$tmp = $this->registry->getClass('output')->getTemplate( $skin_name )->Show_attachments_img_thumb( array( 't_location'  => $row['attach_thumb_location'],
																											  		 't_width'     => $row['attach_thumb_width'],
																											  		 't_height'    => $row['attach_thumb_height'],
																											         'o_width'     => $row['attach_img_width'],
																											  		 'o_height'    => $row['attach_img_height'],
																											  	     'attach_id'   => $row['attach_id'],
																													 '_attach_id'  => $row['_attach_id'],
																											    	 'file_size'   => IPSLib::sizeFormat( $row['attach_filesize'] ),
																											  		 'attach_hits' => $row['attach_hits'],
																											  		 'location'    => $row['attach_file'],
																													 'type'        => $this->type,
																													 'notinline'   => $not_inline,
																													 'attach_rel_id' => $row['attach_rel_id']
																										)	);

						//-----------------------------------------
						// Convert HTML
						//-----------------------------------------
						
						if ( is_array( $map_attach_id_to_rel_id[ $_attach_id ] ) AND count( $map_attach_id_to_rel_id[ $_attach_id ] ) )
						{
							foreach( $map_attach_id_to_rel_id[ $_attach_id ] as $idx => $_rel_id )
							{
								$_count = substr_count( $htmlArray[ $_rel_id ], '[attachment='.$row['attach_id'].':' );
						
								if ( $_count > 1 )
								{
									# More than 1 of the same thumbnail to show?
									$this->_current = array( 'type'      => $this->type,
															 'row'       => $row,
															 'skin_name' => $skin_name );
							
									#$returnArray[ $_rel_id ]['html'] = preg_replace_callback( "#\[attachment=".$row['attach_id']."\:(?:[^\]]+?)[\n|\]]#is", array( &$this, '_parseThumbnailInline' ), $returnArray[ $_rel_id ]['html'] );
								}
								else if ( $_count )
								{
									# Just the one, then?
									#$returnArray[ $_rel_id ]['html'] = preg_replace( "#\[attachment=".$row['attach_id']."\:(?:[^\]]+?)[\n|\]]#is", $tmp, $returnArray[ $_rel_id ]['html'] );
								}
								else
								{
									# None. :(
									$_seen++;
							
									if ( $_seen == $this->settings['topic_attach_no_per_row'] )
									{
										$tmp .= "\n<br />\n";
										$_seen = 0;
									}
								
									$final_blocks[ $_rel_id ]['thumb'][] = $tmp;
								}
							}
						}
						else
						{
							$final_blocks[ $row['attach_rel_id'] ]['thumb'][] = $tmp;
						}
					}
					else
					{
						//-----------------------------------------
						// Standard size..
						//-----------------------------------------
						
						$tmp = $this->registry->getClass('output')->getTemplate( $skin_name )->Show_attachments_img( $row['attach_location'] );
						
						if ( is_array( $map_attach_id_to_rel_id[ $_attach_id ] ) AND count( $map_attach_id_to_rel_id[ $_attach_id ] ) )
						{
							foreach( $map_attach_id_to_rel_id[ $_attach_id ] as $idx => $_rel_id )
							{
								if ( strstr( $htmlArray[ $_rel_id ], '[attachment='.$row['attach_id'].':' ) )
								{
								#	$returnArray[ $_rel_id ]['html'] = preg_replace( "#\[attachment=".$row['attach_id']."\:(?:[^\]]+?)[\n|\]]#is", $tmp, $returnArray[ $_rel_id ]['html'] );
								}
								else
								{
									$final_blocks[ $_rel_id ]['image'][] = $tmp;
								}
							}
						}
						else
						{
							$final_blocks[ $row['attach_rel_id'] ]['image'][] = $tmp;
						}
					}
				}
				/*
				else
				{
					//-----------------------------------------
					// Full attachment thingy
					//-----------------------------------------
					
					$attach_cache = $this->registry->cache()->getCache('attachtypes');
					
					$tmp = $this->registry->getClass('output')->getTemplate( $skin_name )->Show_attachments( array (
																										'attach_hits'  => $row['attach_hits'],
																										'mime_image'   => $attach_cache[ $row['attach_ext'] ]['atype_img'],
																										'attach_file'  => $row['attach_file'],
																										'attach_id'    => $row['attach_id'],
																										'type'         => $this->type,
																										'file_size'    => IPSLib::sizeFormat( $row['attach_filesize'] ),
																							  )  	  );
					if ( is_array( $map_attach_id_to_rel_id[ $_attach_id ] ) AND count( $map_attach_id_to_rel_id[ $_attach_id ] ) )
					{
						foreach( $map_attach_id_to_rel_id[ $_attach_id ] as $idx => $_rel_id )
						{
							if ( strstr( $htmlArray[ $_rel_id ], '[attachment='.$row['attach_id'].':' ) )
							{
								$returnArray[ $_rel_id ]['html'] = preg_replace( "#\[attachment=".$row['attach_id']."\:(?:[^\]]+?)[\n|\]]#is", $tmp, $returnArray[ $_rel_id ]['html'] );
							}
							else
							{
								$final_blocks[ $_rel_id ]['attach'][] = $tmp;
							}
						}
					}
					else
					{
						$final_blocks[ $row['attach_rel_id'] ]['attach'][] = $tmp;
					}
				}
				*/
				$_seen_rows++;
			}

			//-----------------------------------------
			// Anthing to add?
			//-----------------------------------------

			if ( count( $final_blocks ) )
			{
				foreach( $final_blocks as $rel_id => $type )
				{
					$temp_out = "";

					if ( $final_blocks[ $rel_id ]['thumb'] )
					{
						$temp_out .= $this->registry->getClass('output')->getTemplate( $skin_name )->show_attachment_title( $this->lang->words['attach_thumbs'], $final_blocks[ $rel_id ]['thumb'], 'thumb' );
					}

					if ( $final_blocks[ $rel_id ]['image'] )
					{
						$temp_out .= $this->registry->getClass('output')->getTemplate( $skin_name )->show_attachment_title( $this->lang->words['attach_images'], $final_blocks[ $rel_id ]['image'], 'image' );
					}

					if ( $final_blocks[ $rel_id ]['attach'] )
					{
						$temp_out .= $this->registry->getClass('output')->getTemplate( $skin_name )->show_attachment_title( $this->lang->words['attach_normal'], $final_blocks[ $rel_id ]['attach'], 'attach' );
					}
		
					if ( $temp_out )
					{
						$returnArray[ $rel_id ]['attachmentHtml'] = $temp_out;
					}
				}
			}
		}

		return $returnArray;
	}
	
	public function processUpload()
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$this->error = '';
		
		$this->getUploadFormSettings();
		
		//-----------------------------------------
		// Check upload dir
		//-----------------------------------------
		
		if ( ! $this->checkUploadDirectory() )
		{
			if ( $this->error )
			{
				return;
			}
		}

		//-----------------------------------------
		// Got attachment types?
		//-----------------------------------------
		
		if ( ! ( $this->registry->cache()->getCache('attachtypes') ) OR ! is_array( $this->registry->cache()->getCache('attachtypes') ) )
		{
			$attachtypes = array();

			$this->DB->build( array(
											'select' => 'atype_extension,atype_mimetype,atype_post,atype_photo,atype_img',
											'from'   => 'attachments_type',
											'where'  => "atype_photo=1 OR atype_post=1" 
								)	);
			$this->DB->execute();
	
			while ( $r = $this->DB->fetch() )
			{
				$attachtypes[ $r['atype_extension'] ] = $r;
			}
			
			$this->registry->cache()->updateCacheWithoutSaving( 'attachtypes', $attachtypes );
		}

		//-----------------------------------------
		// Can upload?
		//-----------------------------------------
		
		if ( ! $this->attach_stats['allow_uploads'] )
		{
			$this->error = 'upload_failed';
			return;
		}
		
		//-----------------------------------------
		// Set up array
		//-----------------------------------------
		
		$attach_data = array( 
							  'attach_ext'            => "",
							  'attach_file'           => "",
							  'attach_location'       => "",
							  'attach_thumb_location' => "",
							  'attach_hits'           => 0,
							  'attach_date'           => time(),
							  //'attach_temp'           => 0,
							  'attach_post_key'       => $this->attach_post_key,
							  'attach_member_id'      => $this->memberData['member_id'],
							  'attach_rel_id'         => $this->attach_rel_id,
							  'attach_rel_module'     => $this->type,
							  'attach_filesize'       => 0,
							);
		
		//-----------------------------------------
		// Load the library
		//-----------------------------------------
		
		require_once( 'mobi_classUpload.php' );
		$upload = new mobi_classUpload();
		
		//-----------------------------------------
		// Set up the variables
		//-----------------------------------------
		
		$upload->out_file_name    = $this->type . '-' . $this->memberData['member_id'] . '-' . str_replace( '.', '', microtime( true ) );
		$upload->out_file_dir     = $this->upload_path;
		$upload->max_file_size    = $this->attach_stats['max_single_upload'] ? $this->attach_stats['max_single_upload'] : 1000000000;
		$upload->make_script_safe = 1;
		$upload->force_data_ext   = 'ipb';
		
		//-----------------------------------------
		// Populate allowed extensions
		//-----------------------------------------

		if ( is_array( $this->registry->cache()->getCache('attachtypes') ) and count( $this->registry->cache()->getCache('attachtypes') ) )
		{
			/* SKINNOTE: I had to add [attachtypes] to this cache to make it work, may need fixing? */
			//$tmp = $this->registry->cache()->getCache('attachtypes');
			foreach( $this->registry->cache()->getCache('attachtypes') as $idx => $data )
			{
				if ( $data['atype_post'] )
				{
					$upload->allowed_file_ext[] = $data['atype_extension'];
				}
			}
		}
		
		//-----------------------------------------
		// Upload...
		//-----------------------------------------
		
		$upload->process();
		
		//-----------------------------------------
		// Error?
		//-----------------------------------------
		
		if ( $upload->error_no )
		{
			switch( $upload->error_no )
			{
				case 1:
					// No upload
					$this->error = 'upload_no_file';
					return $attach_data;
					break;
				case 2:
					// Invalid file ext
					$this->error = 'invalid_mime_type';
					return $attach_data;
					break;
				case 3:
					// Too big...
					$this->error = 'upload_too_big';
					return $attach_data;
					break;
				case 4:
					// Cannot move uploaded file
					$this->error = 'upload_failed';
					return $attach_data;
					break;
				case 5:
					// Possible XSS attack (image isn't an image)
					$this->error = 'upload_failed';
					return $attach_data;
					break;
			}
		}
					
		//-----------------------------------------
		// Still here?
		//-----------------------------------------

		if ( $upload->saved_upload_name and @file_exists( $upload->saved_upload_name ) )
		{
			//-----------------------------------------
			// Strip off { } and [ ]
			//-----------------------------------------
			
			$upload->original_file_name = str_replace( array( '[', ']', '{', '}' ), "", $upload->original_file_name );
			
			$attach_data['attach_filesize']   = @filesize( $upload->saved_upload_name  );
			$attach_data['attach_location']   = $this->upload_dir . $upload->parsed_file_name;
			
			if( IPSText::isUTF8( $upload->original_file_name ) )
			{
				$attach_data['attach_file']       = IPSText::convertCharsets( $upload->original_file_name, "UTF-8", IPS_DOC_CHAR_SET );
			}
			else
			{
				$attach_data['attach_file']       = $upload->original_file_name;
			}
			
			$attach_data['attach_is_image']   = $upload->is_image;
			$attach_data['attach_ext']        = $upload->real_file_extension;
			
			if ( $attach_data['attach_is_image'] == 1 )
			{
				require_once( IPS_KERNEL_PATH."classImage.php" ); 
				require_once( IPS_KERNEL_PATH."classImageGd.php" );
				$image = new classImageGd();
				$image->force_resize = true;
				
				$image->init( array( 
				                         'image_path'     => $this->upload_path, 
				                         'image_file'     => $upload->parsed_file_name, 
				               )          );
				
				if ( $this->attach_settings['siu_thumb'] )
				{
					$_thumbName = preg_replace( "#^(.*)\.(\w+?)$#", "\\1_thumb.\\2", $upload->parsed_file_name );
					
					if( $thumb_data = $image->resizeImage( $this->attach_settings['siu_width'], $this->attach_settings['siu_height'] ) )
					{
						$image->writeImage( $this->upload_path . '/' . $_thumbName );
						
						if ( is_array( $thumb_data ) )
						{
							$thumb_data['thumb_location'] = $_thumbName;
						}
					}
				}
				
				if ( $thumb_data['thumb_location'] )
				{
					$attach_data['attach_img_width']      = $thumb_data['originalWidth'];
					$attach_data['attach_img_height']     = $thumb_data['originalHeight'];
					$attach_data['attach_thumb_width']    = $thumb_data['newWidth'];
					$attach_data['attach_thumb_height']   = $thumb_data['newHeight'];
					$attach_data['attach_thumb_location'] = $this->upload_dir . $thumb_data['thumb_location'];
				}
			}
			
			//-----------------------------------------
			// Add into Database
			//-----------------------------------------
			
			$this->DB->insert( 'attachments', $attach_data );
			
			$newid = $this->DB->getInsertId();
			
			return $newid;
		}	
	}
	
}
