<?php

define('IN_MOBIQUO', true);

require('lib/xmlrpc.inc');
require('lib/xmlrpcs.inc');
require('server_define.php');
require('mobiquo_common.php');
require('mobiquo_action.php');
require('env_setting.php');
require('smf_entry.php');
require('xmlrpcresp.php');

$rpcServer = new xmlrpc_server($server_param, false);
$rpcServer->setDebug(1);
$rpcServer->compress_response = true;
$rpcServer->response_charset_encoding = 'UTF-8';
$rpcServer->service($server_data);

?>