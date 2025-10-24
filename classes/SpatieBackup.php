<?php

namespace OnePilot\Client\Classes;

use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;

class SpatieBackup
{
    public static function isSupported()
    {
        return class_exists(BackupDestinationStatus::class);
    }

    public static function driverFormat($driver)
    {
        return preg_replace('#filesystemadapter$#', '', $driver);
    }
}
