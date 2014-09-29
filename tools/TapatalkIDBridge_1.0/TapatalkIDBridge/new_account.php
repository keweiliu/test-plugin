<?php
//if($_SERVER['HTTP_X_FORWARDED_FOR'] != '50.17.254.140')
//{
//    print json_encode(array('result'=> true, 'result_text'=>'hacking attempt!');
//}
error_reporting(0);
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
if(empty($_GET['username']) || empty($_GET['email']) || empty($_GET['password']))
{
   print $bridge->responseError("Parameters missing!\n");
   exit;
}
$username = mysql_escape_string($_GET['username']);
$email = mysql_escape_string($_GET['email']);
$password = mysql_escape_string($_GET['password']);

$params = array($username, $password, $email);
$options = XenForo_Application::get('options');
$data = $bridge->_input->filterExternal(array(
        'username' => XenForo_Input::STRING,
        'password' => XenForo_Input::STRING,
        'email'  => XenForo_Input::STRING,
), $params);

//Xenforo Validate fields
$v_datas = array();
foreach($data as $key => $value)
{
    if($key == 'password')
        continue;
    $v_datas[$key] = array(
        'name' => $key,
        'value' => $value,
    );
}
$options = array(XenForo_DataWriter_User::OPTION_ADMIN_EDIT => true);
foreach($v_datas as $field_name => $v_data)
{
    $v_data = array_merge(array('existingDataKey' => 0), $v_data);
    $vwriter = XenForo_DataWriter::create('XenForo_DataWriter_User');
    if (!empty($v_data['existingDataKey']) || $v_data['existingDataKey'] === '0')
    {
        $vwriter->setExistingData($v_data['existingDataKey']);
    }

    foreach ($options AS $key => $value)
    {
        $vwriter->setOption($key, $value);
    }
    $vwriter->set($v_data['name'], $v_data['value']);

    if ($errors = $vwriter->getErrors())
    {
       print $bridge->responseError($errors[$field_name]);
       exit;
    }
}

$extra_data = array(
    'user_group_id' => '2',
    'user_state' => 'valid',
    'is_discouraged' => '0',
    'gender' => '',
    'dob_day' => '0',
    'dob_month' => '0',
    'dob_year' => '0',
    'location' => '',
    'occupation' => '',
    'custom_title' => '',
    'homepage' => '',
    'about' => '',
    'signature' => '',
    'message_count' => '0',
    'like_count' => '0',
    'trophy_points' => '0',
    'style_id' => '0',
    'language_id' => '1',
    'timezone' => empty(XenForo_Application::get('options')->guestTimeZone)? 'Europe/London' : XenForo_Application::get('options')->guestTimeZone,
    'content_show_signature' => '1',
    'enable_rte' => '1',
    'visible' => '1',
    'receive_admin_email' => '1',
    'show_dob_date' => '1',
    'show_dob_year' => '1',
    'allow_view_profile' => 'everyone',
    'allow_post_profile' => 'members',
    'allow_send_personal_conversation' => 'members',
    'allow_view_identities' => 'everyone',
    'allow_receive_news_feed' => 'everyone'
);
$data = array_merge($extra_data,$data);

$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
$writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
$password = $data['password'];
unset($data['password']);
unset($data['token']);
unset($data['code']);
$writer->bulkSet($data);
if ($password !== '')
{
    $writer->setPassword($password);
}
$writer->save();

$errors = $writer->getErrors();
if($errors)
{
    $error_message = '';
    foreach($errors as $error)
    {
        $error_message .= (string) $error;
    }
    if(empty($error_message))
        $error_message = 'Register failed for unkown reason!';
    print $bridge->responseError($error_message);
}

$user = $writer->getMergedData();

$userConfirmModel = $bridge->getUserConfirmationModel();
XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');
$response = array();
if($user['user_id'] == 0)
{
    $response['result'] = false;
    $response['result_text'] = isset($email_response['result_text']) && !empty($email_response['result_text']) ? $email_response['result_text'] : '';
}
else
{
    $response['result'] = true;
}

$response['result_text'] = '';
header('Content-type: application/json');
print json_encode($response);