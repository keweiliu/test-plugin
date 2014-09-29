<?php
error_reporting(E_ALL);
$host = $_POST['host'];
if (strpos($host, 'http://') === 0)
    $host = substr($host, 7);
else if (strpos($host, 'https://') === 0)
{
    $host = substr($host, 8);
}
list($host,$path) = explode('/',$host,2);
$_POST['path'] = str_replace("mobiquo.php","upload.php",$_POST['path']);
$path .= $_POST['path'];

//mybb
//$input_file_name = 'attachment';
//phpbb
$input_file_name = 'fileupload';
$cookie = $_POST['clientcookies'];
$forum_id = $_POST['forum_id'];
$group_id = $_POST['group_id'];
$filename = $_FILES['fileupload']['tmp_name'];
$real_fielname = $_FILES['fileupload']['name'];
$cookie = str_replace(',', ';', $cookie);

$fp = fsockopen($host, 80);
if (!$fp) return "Failed to open socket to $host";


$request_body ='-----------------------------265001916915724
Content-Disposition: form-data; name="method_name"

upload_attach
-----------------------------265001916915724
Content-Disposition: form-data; name="forum_id"

'.$forum_id.(empty($group_id) ? '' : '
-----------------------------265001916915724
Content-Disposition: form-data; name="group_id"

'.$group_id).'

-----------------------------265001916915724
Content-Disposition: form-data; name="'.$input_file_name.'"; filename="'.$real_fielname.'"
Content-Type: image/png

'.join("", file($filename)).'

-----------------------------265001916915724--';


$request_head = "POST /$path/upload.php HTTP/1.1
User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:14.0) Gecko/20100101 Firefox/14.0.1
Host: $host:80
Accept-Charset: UTF-8,ISO-8859-1,US-ASCII
Cookie: $cookie
Content-Type: multipart/form-data; boundary=---------------------------265001916915724
Content-Length: ".strlen($request_body).'

';


fputs($fp, $request_head);
fputs($fp, $request_body);

echo str_replace("\n","<br/>",$request_head);



echo "==================================================<br/>";


$line = fgets($fp,1024);
//if (!eregi("^HTTP/1\.. 200", $line)) return;
print $line;
$results = "";
$inheader = 1;
while(!feof($fp))
{
    $line = fgets($fp,1024);
    $results .= $line;
}
if(!empty($results))
{
	$GLOBALS['_xh']['headers'] = array();
	if(!isset($GLOBALS['_xh']['cookies']))
		$GLOBALS['_xh']['cookies'] = array();

	// be tolerant to usage of \n instead of \r\n to separate headers and data
	// (even though it is not valid http)
	$pos = strpos($results,"\r\n\r\n");
	// be tolerant to line endings, and extra empty lines
	$ar = preg_split("/\r?\n/", trim(substr($results, 0, $pos)));
	while(list(,$line) = @each($ar))
	{
		// take care of multi-line headers and cookies
		$arr = explode(':',$line,2);
		if(count($arr) > 1)
		{
			$header_name = strtolower(trim($arr[0]));
			/// @todo some other headers (the ones that allow a CSV list of values)
			/// do allow many values to be passed using multiple header lines.
			/// We should add content to $GLOBALS['_xh']['headers'][$header_name]
			/// instead of replacing it for those...
			if ($header_name == 'set-cookie' || $header_name == 'set-cookie2')
			{
				if ($header_name == 'set-cookie2')
				{
					// version 2 cookies:
					// there could be many cookies on one line, comma separated
					$cookies = explode(',', $arr[1]);
				}
				else
				{
					$cookies = array($arr[1]);
				}
				foreach ($cookies as $cookie)
				{
					// glue together all received cookies, using a comma to separate them
					// (same as php does with getallheaders())
					if (isset($GLOBALS['_xh']['headers'][$header_name]))
						$GLOBALS['_xh']['headers'][$header_name] .= ', ' . trim($cookie);
					else
						$GLOBALS['_xh']['headers'][$header_name] = trim($cookie);
					// parse cookie attributes, in case user wants to correctly honour them
					// feature creep: only allow rfc-compliant cookie attributes?
					// @todo support for server sending multiple time cookie with same name, but using different PATHs
					$cookie = explode(';', $cookie);
					foreach ($cookie as $pos => $val)
					{
						$val = explode('=', $val, 2);
						$tag = trim($val[0]);
						$val = trim(@$val[1]);
						/// @todo with version 1 cookies, we should strip leading and trailing " chars
						if ($pos == 0)
						{
							$cookiename = $tag;
							$GLOBALS['_xh']['cookies'][$tag] = array();
							$GLOBALS['_xh']['cookies'][$cookiename]['value'] = urldecode($val);
						}
						else
						{
							if ($tag != 'value')
							{
							  $GLOBALS['_xh']['cookies'][$cookiename][$tag] = $val;
							}
						}
					}
				}
			}
			else
			{
				$GLOBALS['_xh']['headers'][$header_name] = trim($arr[1]);
			}
		}
		elseif(isset($header_name))
		{
			///	@todo version1 cookies might span multiple lines, thus breaking the parsing above
			$GLOBALS['_xh']['headers'][$header_name] .= ' ' . trim($line);
		}
	}
}
if(!empty($GLOBALS['_xh']['cookies']))
	$_SESSION['cookie_action'] = $GLOBALS['_xh']['cookies'];
//$_SESSION['cookie_action'] = $GLOBALS['_xh']['cookies'];
print str_replace("\n","<br/>", htmlspecialchars($results));
fclose($fp);