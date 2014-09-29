<?php

class TapatalkIDBridge_ControllerPublic_Account extends XFCP_TapatalkIDBridge_ControllerPublic_Account
{
    public function actionSecuritySave()
    {
        $this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'old_password' => XenForo_Input::STRING,
			'password' => XenForo_Input::STRING,
			'password_confirm' => XenForo_Input::STRING
		));

		$userId = XenForo_Visitor::getUserId();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($userId);
		if (!$auth || !$auth->authenticate($userId, $input['old_password']))
		{
			return $this->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		$writer->setExistingData($userId);
		$writer->setPassword($input['password'], $input['password_confirm']);
		$writer->save();
        
        //Tapatalk Add
        //Send request.
        XenForo_Application::autoload('TapatalkIDBridge_Tools');
        $visitor = XenForo_Visitor::getInstance();
        $res = TapatalkIDBridge_Tools::getContentFromRemoteServer('http://directory.tapatalk.com/au_change_password.php?email='.$visitor['email'].'&new_password='.md5($input['password']).'&app_id=15&app_key=sad23RdsGShdf67r', 0, $error_message);
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('settings')
		);
    }
}