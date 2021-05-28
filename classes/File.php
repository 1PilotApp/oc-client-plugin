<?php

namespace OnePilot\Client\Classes;

use Illuminate\Support\Facades\File as IlluminateFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @mixin \File
 * @see \Illuminate\Filesystem\Filesystem
 */
class File extends IlluminateFile
{
    /**
     * @param string $path
     * @param null   $fileRights if null use same right that /index.php
     * @param null   $dirRights  if null use the same right that the plugin dir
     */
    public static function recursiveChmod($path, $fileRights = null, $dirRights = null)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $fileRights = is_null($fileRights) ? self::getDefaultFilePerms() : $fileRights;
        $dirRights = is_null($dirRights) ? self::getDefaultFolderPerms() : $dirRights;

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @chmod($item->getPathname(), $dirRights);
            }

            if ($item->isFile()) {
                @chmod($item->getPathname(), $fileRights);
            }
        }
    }

    /**
     * @return int
     */
    private static function getDefaultFilePerms()
    {
        $chmod = @fileperms(base_path('index.php'));

        return empty($chmod) ? 0644 : $chmod;
    }

    /**
     * @return int
     */
    private static function getDefaultFolderPerms()
    {
        $chmod = @fileperms(plugins_path());

        return empty($chmod) ? 0755 : $chmod;
    }
}
