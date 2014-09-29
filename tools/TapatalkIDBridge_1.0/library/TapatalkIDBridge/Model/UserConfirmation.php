<?php

class TapatalkIDBridge_Model_UserConfirmation extends XFCP_TapatalkIDBridge_Model_UserConfirmation
{
    public function resetPassword($userId, $sendEmail = true)
    {
        $password = parent::resetPassword($userId, $sendEmail);
        //send request;
        XenForo_Application::autoload('TapatalkIDBridge_Tools');
        $userModel = $this->getModelFromCache('XenForo_Model_User');
        $user = $userModel->getUserById($userId);
        $res = TapatalkIDBridge_Tools::getContentFromRemoteServer('http://directory.tapatalk.com/au_change_password.php?email='.$user['email'].'&new_password='.md5($password).'&app_id=15&app_key=sad23RdsGShdf67r', 0, $error_message);
        return $password;
    }
}