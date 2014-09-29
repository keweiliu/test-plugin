<?php
/***************************************************************
* Database-1.1.php                                             *
* Copyright ｩ2009 Quoord Systems Ltd. All Rights Reserved.     *
* Created by Dragooon (http://smf-media.com)                   *
****************************************************************
* This file or any content of the file should not be           *
* redistributed in any form of matter. This file is a part of  *
* Tapatalk package and should not be used and distributed      *
* in any form not approved by Quoord Systems Ltd.              *
* http://tapatalk.com | http://taptatalk.com/license.html      *
****************************************************************
* Provides DB abstraction for SMF 1.1                          *
***************************************************************/

if (!defined('SMF'))
	die('Hacking Attempt...');

$mobdb = new MobDatabase();

// The main DB class for SMF 1.1
class MobDatabase
{
	// Some variables that we use for storing temporary data
	var $sql;
	var $result;
	var $params = array();

	// Constructor function, does almost nothing. Have it here just for future
	function MobDatabase()
	{
	}

	// Performs a query
	function query($sql, $params = array(), $security_override = false)
	{
		global $db_prefix, $user_info, $user_info;

		if (empty($sql))
			return false;

		// Set this in global space
		$this->params = $params;

		// Figure out the file and line
		if (function_exists('debug_backtrace'))
		{
			$trace = debug_backtrace();
			$file = $trace[0]['file'];
			$line = $trace[0]['line'];
		}
		else
		{
			$file = __FILE__;
			$line = __LINE__;
		}

		// Perform the replace
		if (!$security_override)
			$this->sql = preg_replace_callback('~{([a-z_]+)(?::([a-zA-Z0-9_-]+))?}~', array(&$this, '_replace_callback'), $sql);
		else
			$this->sql = $sql;

		// Perform the query
		$this->result = db_query($this->sql, $file, $line);
	}

	// Performs the replace callback, access is private
	function _replace_callback($matches)
	{
		global $db_prefix, $context, $user_info;

		if ($matches[1] === 'db_prefix')
			return $db_prefix;

		if ($matches[1] === 'query_see_board')
			return $user_info['query_see_board'];

		if (!isset($matches[2]))
			fatal_error('Invalid value inserted or no type specified.' . htmlspecialchars($matches[2]).' <br />'.$file.'<br />'.$line);

		if (!isset($this->params[$matches[2]]))
			fatal_error('The database value you\'re trying to insert does not exist: ' . htmlspecialchars($matches[2]).' <br />'.$file.'<br />'.$line);

		$replacement = $this->params[$matches[2]];

		switch ($matches[1])
		{
			case 'int':
				if (!is_numeric($replacement) || (string) $replacement !== (string) (int) $replacement)
					fatal_error('There was a error in your query<br />'.htmlspecialchars($file).'<br />'.$line.'<br />'.$matches[2]);
				return (string) (int) $replacement;
			break;

			case 'string':
			case 'text':
				return sprintf('\'%1$s\'', mysql_real_escape_string($replacement));
			break;

			case 'raw':
				return $replacement;
			break;

			case 'array_int':
				if (is_array($replacement))
				{
					if (empty($replacement))
						fatal_error('Database error, given array of integer values is empty. (' . $matches[2] . ')');

					foreach ($replacement as $key => $value)
					{
						if (!is_numeric($value) || (string) $value !== (string) (int) $value)
							fatal_error('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')');

						$replacement[$key] = (string) (int) $value;
					}

					return implode(', ', $replacement);
				}
				else
					fatal_error('Wrong value type sent to the database. Array of integers expected. (' . $matches[2] . ')');
			break;

			case 'array_string':
				if (is_array($replacement))
				{
					if (empty($replacement))
						fatal_error('Database error, given array of string values is empty. (' . $matches[2] . ')');

					foreach ($replacement as $key => $value)
						$replacement[$key] = sprintf('\'%1$s\'', mysql_real_escape_string($value, $connection));

					return implode(', ', $replacement);
				}
				else
					fatal_error('Wrong value type sent to the database. Array of strings expected. (' . $matches[2] . ')');
			break;

			case 'float':
				if (!is_numeric($replacement))
					fatal_error('Wrong value type sent to the database. Floating point number expected.');
				return (string) (float) $replacement;
			break;
		}
		fatal_error('The type you specified is not found');
	}

	// Returns the data from the result
	function fetch_assoc()
	{
		return mysql_fetch_assoc($this->result);
	}

	// Returns the number of rows from the result
	function num_rows()
	{
		return mysql_num_rows($this->result);
	}

	// Returns the result in another wau
	function fetch_row()
	{
		return mysql_fetch_row($this->result);
	}

	// Return the INSERT ID of the last INSERT
	function insert_id($table = '', $field = '')
	{
		return mysql_insert_id();
	}

	// Performs a database insert
	function insert($table, $columns, $data, $ignore = false)
	{
		global $context, $db_prefix;

		if (empty($table) || empty($columns) || empty($data))
			return false;
		$insert = $ignore ? 'INSERT IGNORE' : 'INSERT';

		$table = str_replace('{db_prefix}', $db_prefix, $table);
        
        if (function_exists('array_combine'))
		    $query_data = array_combine($columns, $data);
		else {
            $query_data = array(); 
            foreach($columns as $key => $value)    { 
                $query_data[$value] = $data[$key]; 
            } 
		}
		$insert_columns = array();
		$insert_data = array();

		// Just so that we get the things at the right place
		foreach ($query_data as $c => $d)
		{
			$insert_columns[] = '`' . $c . '`';
			$insert_data[] = is_int($d) ? (int) $d : sprintf('\'%1$s\'', mysql_real_escape_string($d));
		}

		// Perform the insert query
		$this->query(
			$insert . ' INTO ' . $table . ' (' . implode(',', $insert_columns) . ')
			VALUES
			(' . implode(',', $insert_data) . ')', array(), true);
	}

	// Frees the DB result
	function free_result()
	{
		return mysql_free_result($this->result);
	}
}