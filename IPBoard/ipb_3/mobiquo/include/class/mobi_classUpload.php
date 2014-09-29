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
require_once( IPS_KERNEL_PATH.'classUpload.php' );

class mobi_classUpload extends classUpload
{
	protected function _cleanPaths()
	{
		$this->out_file_dir = rtrim( $this->out_file_dir, '/' );
	}
	
	protected function _checkXSSInfile()
	{
		// HTML added inside an inline file is not good in IE...
		
		$fh = fopen( $this->saved_upload_name, 'rb' );
		
		$file_check = fread( $fh, 512 );
		
		fclose( $fh );
		
		if( !$file_check )
		{
			@unlink( $this->saved_upload_name );
			$this->error_no = 5;
			return false;
		}
		
		# Thanks to Nicolas Grekas from comments at www.splitbrain.org for helping to identify all vulnerable HTML tags
		
		else if( preg_match( "#<script|<html|<head|<title|<body|<pre|<table|<a\s+href|<img|<plaintext|<cross\-domain\-policy#si", $file_check ) )
		{
			@unlink( $this->saved_upload_name );
			$this->error_no = 5;
			return false;
		}
	}
	
	public function process()
	{
		$this->_cleanPaths();
		
		//-------------------------------------------------
		// Check for getimagesize
		//-------------------------------------------------
		
		if ( ! function_exists( 'getimagesize' ) )
		{
			$this->image_check = 0;
		}
		
		//-------------------------------------------------
		// Set up some variables to stop carpals developing
		//-------------------------------------------------
		
		$FILE_NAME = isset($_FILES[ $this->upload_form_field ]['name']) ? $_FILES[ $this->upload_form_field ]['name'] : '';
		$FILE_SIZE = isset($_FILES[ $this->upload_form_field ]['size']) ? $_FILES[ $this->upload_form_field ]['size'] : '';
		$FILE_TYPE = isset($_FILES[ $this->upload_form_field ]['type']) ? $_FILES[ $this->upload_form_field ]['type'] : '';

		//-------------------------------------------------
		// Naughty Opera adds the filename on the end of the
		// mime type - we don't want this.
		//-------------------------------------------------
		
		$FILE_TYPE = preg_replace( "/^(.+?);.*$/", "\\1", $FILE_TYPE );
		
		//-------------------------------------------------
		// Naughty Mozilla likes to use "none" to indicate an empty upload field.
		// I love universal languages that aren't universal.
		//-------------------------------------------------
		if ( !isset($_FILES[ $this->upload_form_field ]['name'])
			or $_FILES[ $this->upload_form_field ]['name'] == ""
			or !$_FILES[ $this->upload_form_field ]['name']
			or !$_FILES[ $this->upload_form_field ]['size']
			or ($_FILES[ $this->upload_form_field ]['name'] == "none") 
			)
		{
			if( $_FILES[ $this->upload_form_field ]['error'] == 2 )
			{
				$this->error_no = 3;
			}
			else
			{
				$this->error_no = 1;
			}
			
			return false;
		}
		/*
		if( !is_uploaded_file( $_FILES[ $this->upload_form_field ]['tmp_name'] ) )
		{
			$this->error_no = 1;
			return false;
		}
		*/
		//-------------------------------------------------
		// Do we have allowed file_extensions?
		//-------------------------------------------------
		
		if( $this->check_file_ext )
		{
			if ( ! is_array( $this->allowed_file_ext ) or ! count( $this->allowed_file_ext ) )
			{
				$this->error_no = 2;
				return false;
			}
		}
		
		//-------------------------------------------------
		// Get file extension
		//-------------------------------------------------
		
		$this->file_extension = $this->_getFileExtension( $FILE_NAME );

		if ( ! $this->file_extension )
		{
			$this->error_no = 2;
			return false;
		}
		
		$this->real_file_extension = $this->file_extension;
		
		//-------------------------------------------------
		// Valid extension?
		//-------------------------------------------------
		
		if ( $this->check_file_ext AND !in_array( $this->file_extension, $this->allowed_file_ext ) )
		{
			$this->error_no = 2;
			return false;
		}
		
		//-------------------------------------------------
		// Check the file size
		//-------------------------------------------------
		
		if ( ( $this->max_file_size ) and ( $FILE_SIZE > $this->max_file_size ) )
		{
			$this->error_no = 3;
			return false;
		}
		
		//-------------------------------------------------
		// Make the uploaded file safe
		// Storing original_file_name before replacements
		//-------------------------------------------------
		
		$this->original_file_name = $FILE_NAME;
		
		$FILE_NAME = preg_replace( "/[^\w\.]/", "_", $FILE_NAME );

		//-------------------------------------------------
		// Convert file name?
		// In any case, file name is WITHOUT extension
		//-------------------------------------------------
		
		if ( $this->out_file_name )
		{
			$this->parsed_file_name = $this->out_file_name;
		}
		else
		{
			$this->parsed_file_name = str_replace( '.' . $this->file_extension, "", $FILE_NAME );
		}
		
		//-------------------------------------------------
		// Make safe?
		//-------------------------------------------------
		
		$renamed = 0;
		
		if ( $this->make_script_safe )
		{
			if ( preg_match( "/\.(cgi|pl|js|asp|php|html|htm|jsp|jar)(\.|$)/i", $FILE_NAME ) )
			{
				$FILE_TYPE                 = 'text/plain';
				$this->file_extension      = 'txt';
				$this->parsed_file_name	   = preg_replace( "/\.(cgi|pl|js|asp|php|html|htm|jsp|jar)(\.|$)/i", "$2", $this->parsed_file_name );
				
				$renamed = 1;
			}
		}
		
		//-------------------------------------------------
		// Is it an image?
		//-------------------------------------------------

		if ( is_array( $this->image_ext ) and count( $this->image_ext ) )
		{
			if ( in_array( $this->real_file_extension, $this->image_ext ) )
			{
				$this->is_image = 1;
			}
		}

		//-------------------------------------------------
		// Add on the extension...
		//-------------------------------------------------
		
		if ( $this->force_data_ext and ! $this->is_image )
		{
			$this->file_extension = str_replace( ".", "", $this->force_data_ext ); 
		}
		
		$this->parsed_file_name .= '.' . $this->file_extension;
		
		//-------------------------------------------------
		// Copy the upload to the uploads directory
		// ^^ We need to do this before checking the img
		//    size for the openbasedir restriction peeps
		//    We'll just unlink if it doesn't checkout
		//-------------------------------------------------
		
		$this->saved_upload_name = $this->out_file_dir . '/' . $this->parsed_file_name;
		/*
		if ( ! @move_uploaded_file( $_FILES[ $this->upload_form_field ]['tmp_name'], $this->saved_upload_name ) )
		{
			$this->error_no = 4;
			return;
		}
		else
		{
			@chmod( $this->saved_upload_name, 0777 );
		}
		*/
		
		copy($_FILES[ $this->upload_form_field ]['tmp_name'], $this->saved_upload_name);
		@chmod( $this->saved_upload_name, 0777 );
		if( !$renamed AND $this->file_extension != 'txt' )
		{
			$this->_checkXSSInfile();
			
			if( $this->error_no )
			{
				return false;
			}
		}
		
		//-------------------------------------------------
		// Is it an image?
		//-------------------------------------------------
		
		if ( $this->is_image )
		{
			//-------------------------------------------------
			// Are we making sure its an image?
			//-------------------------------------------------
			
			if ( $this->image_check )
			{
				$img_attributes = @getimagesize( $this->saved_upload_name );
				
				if ( ! is_array( $img_attributes ) or !count( $img_attributes ) )
				{
					@unlink( $this->saved_upload_name );
					$this->error_no = 5;
					return false;
				}
				else if ( ! $img_attributes[2] )
				{
					@unlink( $this->saved_upload_name );
					$this->error_no = 5;
					return false;
				}
				else if ( $img_attributes[2] == 1 AND ( $this->file_extension == 'jpg' OR $this->file_extension == 'jpeg' ) )
				{
					// Potential XSS attack with a fake GIF header in a JPEG
					@unlink( $this->saved_upload_name );
					$this->error_no = 5;
					return false;
				}
			}
		}
		
		//-------------------------------------------------
		// If filesize and $_FILES['size'] don't match then
		// either file is corrupt, or there was funny
		// business between when it hit tmp and was moved
		//-------------------------------------------------
		
		if( filesize($this->saved_upload_name) != $_FILES[ $this->upload_form_field ]['size'] )
		{
			@unlink( $this->saved_upload_name );
			$this->error_no = 1;
			return false;
		}
	}
}

?>