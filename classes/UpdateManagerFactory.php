<?php

namespace OnePilot\Client\Classes;

use System\Classes\UpdateManager;

class UpdateManagerFactory
{
    /**
     * @return UpdateManagerOverrideV1|UpdateManager
     */
    public static function instance()
    {
        return class_exists('System')
            ? UpdateManager::instance()
            : UpdateManagerOverrideV1::instance();
    }
}
