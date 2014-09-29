<?php

class TapatalkIDBridge_EventListeners_LoadClassModel
{
    public static function loadClassListener($class, &$extend)
    {
        if ($class == 'XenForo_Model_UserConfirmation')
        {
            $extend[] = 'TapatalkIDBridge_Model_UserConfirmation';
        }
    }
}