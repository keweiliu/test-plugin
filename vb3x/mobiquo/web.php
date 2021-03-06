<?php
if (function_exists('set_magic_quotes_runtime'))
@set_magic_quotes_runtime(0);
ini_set('max_execution_time', '120');
error_reporting(0);

define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));

$current_plugin_version = get_plugin_version();

chdir(get_root_dir());
require_once('./global.php');

print_screen($current_plugin_version);
exit;



function print_screen($current_plugin_version)
{
	global $vbulletin;

	$latest_tp_plugin_version = get_latest_plugin_version();
	$sysversion = @$vbulletin->versionnumber;
	$mobiquo_path = get_path();
	
	$check_upload_status = @file_get_contents("https://".$mobiquo_path."/upload.php?checkAccess");
	$check_push_status = @file_get_contents("https://".$mobiquo_path."/push.php?checkAccess");
	


	echo "Forum XMLRPC Interface for Tapatalk Application<br><br>";
	echo "Current Tapatalk plugin version: ".$current_plugin_version."<br>";

	echo "Latest Tapatalk plugin version: $latest_tp_plugin_version<br>";

	echo "Attachment upload interface status: <a href=\"http://".$mobiquo_path."/upload.php\" target=\"_blank\">".($check_upload_status ? 'OK' : 'Inaccessible')."</a><br>";

	echo "Push notification interface status: <a href=\"http://".$mobiquo_path."/push.php\" target=\"_blank\">".($check_push_status ? 'OK' : 'Inaccessible')."</a><br>";

	echo "<br>
<a href=\"https://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a><br>
For more details, please visit <a href=\"https://tapatalk.com\" target=\"_blank\">https://tapatalk.com</a>";
}

function get_plugin_version()
{
	$config_file = CWD1 . '/config/config.txt';
	file_exists($config_file) or exit('config.txt does not exists');

	if(function_exists('file_get_contents')){
		$tmp = @file_get_contents($config_file);
	}else{
		$handle = @fopen($config_file, 'rb');
		$tmp = @fread($handle, filesize($config_file));
		@fclose($handle);
	}

	// remove comments by /*xxxx*/ or //xxxx
	$tmp = preg_replace('/\/\*.*?\*\/|\/\/.*?(\n)/si','$1',$tmp);
	$tmpData = preg_split("/\s*\n/", $tmp, -1, PREG_SPLIT_NO_EMPTY);

	$mobiquo_config = array();
	foreach ($tmpData as $d){
		list($key, $value) = preg_split("/=/", $d, 2); // value string may also have '='
		$key = trim($key);
		$value = trim($value);
		if ($key == 'hide_forum_id')
		{
			$value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
			$mobiquo_config[$key] = $value;
		}
		else
		{
			strlen($value) and $mobiquo_config[$key] = $value;
		}
	}
	$returnStr = preg_split("/_/", $mobiquo_config['version'], -1, PREG_SPLIT_NO_EMPTY);
	return $returnStr[1];
}

function get_latest_plugin_version()
{
	$tp_lst_pgurl = 'http://api.tapatalk.com/v.php?sys=vb3x&link';

	$response = 'CURL is disabled and PHP option "allow_url_fopen" is OFF. You can enable CURL or turn on "allow_url_fopen" in php.ini to fix this problem.';
	if (function_exists('curl_init'))
	{
		$ch = curl_init($tp_lst_pgurl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch,CURLOPT_TIMEOUT,10);

		$response = curl_exec($ch);
		curl_close($ch);
	}
	elseif (ini_get('allow_url_fopen'))
	{
		$params = array('http' => array(
                'method' => 'POST',
                'content' => @http_build_query($data, '', '&'),
		));

		$ctx = stream_context_create($params);
		$timeout = 10;
		$old = ini_set('default_socket_timeout', $timeout);
		$fp = @fopen($tp_lst_pgurl, 'rb', false, $ctx);

		if (!$fp) return false;

		ini_set('default_socket_timeout', $old);
		stream_set_timeout($fp, $timeout);
		stream_set_blocking($fp, 0);


		$response = @stream_get_contents($fp);
	}
	return $response;
}

function get_root_dir()
{
	$dir = '../';

	if (!empty($_SERVER['SCRIPT_FILENAME']))
	{
		$dir = dirname($_SERVER['SCRIPT_FILENAME']);
		if (!file_exists($dir.'/global.php'))
		$dir = dirname($dir);

		$dir = $dir.'/';
	}

	return $dir;
}

function get_path()
{
	$path =  '../';

	if (!empty($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['HTTP_HOST']))
	{
		$path = $_SERVER['HTTP_HOST'];
		$path .= dirname($_SERVER['SCRIPT_NAME']);
	}
	return $path;
}