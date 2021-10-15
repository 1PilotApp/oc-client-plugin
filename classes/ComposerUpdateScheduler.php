<?php

namespace OnePilot\Client\Classes;

class ComposerUpdateScheduler
{
    const PATH = 'framework/1pilot_update_scheduled';

    public static function registerTask(array $packages, string $uuid, string $callbackUrl)
    {
        $tasks = self::hasTask() ? self::getTasks() : [];

        $tasks[] = ['packages' => $packages, 'uuid' => $uuid, 'callbackUrl' => $callbackUrl];

        file_put_contents(storage_path(self::PATH), json_encode($tasks));
    }

    public static function path()
    {
        return storage_path(self::PATH);
    }

    public static function hasTask()
    {
        return file_exists(storage_path(self::PATH));
    }

    /**
     * @return array[]
     * @psalm-return array<array{package:string, uuid:string, callbackUrl:string}>
     */
    public static function getTasks()
    {
        $content = file_get_contents(storage_path(self::PATH));

        if ($content === false) {
            return [];
        }

        $tasks = json_decode($content, true);

        return (is_array($tasks)) ? $tasks : [];
    }

    public static function clear()
    {
        @unlink(storage_path(self::PATH));
    }

    public static function schedulerIsWritable()
    {
        return is_writable(storage_path(self::PATH));
    }
}
