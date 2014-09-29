<?php

error_reporting(0);
//if($_SERVER['HTTP_X_FORWARDED_FOR'] != '50.17.254.140')
//{
//    print json_encode(array('result'=> true, 'result_text'=>'hacking attempt!');
//}
$startTime = microtime(true);
define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');

if (DIRECTORY_SEPARATOR == '/')
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');
else
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))).'/');

require_once SCRIPT_ROOT.'library/XenForo/Autoloader.php';
XenForo_Autoloader::getInstance()->setupAutoloader(SCRIPT_ROOT.'library');

XenForo_Application::initialize(SCRIPT_ROOT.'library', SCRIPT_ROOT);
XenForo_Application::set('page_start_time', $startTime);
restore_error_handler();
try
{
    $bridge = TapatalkIDBridge_Application::getInstance();
//    $bridge->setAction($request_method_name);
    $bridge->init();
}
catch (XenForo_ControllerResponse_Exception $e)
{
    $controllerResponse = $e->getControllerResponse();

    if ($controllerResponse instanceof XenForo_ControllerResponse_Reroute)
    {
        $errorPhrase = $bridge->responseErrorMessage($controllerResponse);
        $errorText = $errorPhrase->errorText->render();
    }
    else
    {
        $errorText = 'Unknow error';
    }
    print json_encode(array('result'=> false, 'result_text'=> $errorText));
    exit;
}

$userModel = $bridge->getUserModel();
if(empty($_GET['email']) || empty($_GET['new_password']))
{
   print $bridge->responseError("Parameters missing!\n");
   exit;
}
$email = mysql_escape_string($_GET['email']);
$password = mysql_escape_string($_GET['new_password']);

$params = array($email, $password);
$options = XenForo_Application::get('options');
$data = $bridge->_input->filterExternal(array(
        'email'  => XenForo_Input::STRING,
        'new_password' => XenForo_Input::STRING,
), $params);

$userFromEmail = $userModel->getUserByEmail($data['email']);
if(empty($userFromEmail))
{
    print $bridge->responseError('Sorry, no such email user found.');
    exit;
}
$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
if(isset($userFromEmail['user_id']) && $userFromEmail['user_id'])
{
    $writer->setExistingData($userFromEmail['user_id']);
}
$writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
if ($data['new_password'] !== '')
{
    $writer->setPassword($data['new_password']);
}
else
{
    print $bridge->responseError('password cannot be empty');
    exit;
}
$writer->save();
if ($errors = $writer->getErrors())
{
    print $bridge->responseError('Update failed.');
    exit;
}
$response['result'] = true;
$response['result_text'] = '';
header('Content-type: application/json');
print json_encode($response);