<?php

class TapatalkIDBridge_EventListeners_LoadClassController
{
    public static function controller($class, array &$extend)
    {
		switch ($class)
		{
			case 'XenForo_ControllerPublic_Account':
				$extend[] = 'TapatalkIDBridge_ControllerPublic_Account';
				break;
		}
    }
}