<?php namespace OnePilot\Client;

use Carbon\Carbon;
use Event;
use OnePilot\Client\Classes\ComposerUpdateScheduler;
use OnePilot\Client\Exceptions\Handler;
use OnePilot\Client\Models\Settings;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => '1Pilot Client',
            'description' => 'Central dashboard to manage your OctoberCMS websites',
            'author' => '1Pilot.io',
            'icon' => 'icon-plug',
            'homepage' => 'https://1pilot.io',
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => '1Pilot Settings',
                'description' => 'Central dashboard to manage your OctoberCMS websites',
                'category' => SettingsManager::CATEGORY_SYSTEM,
                'icon' => 'icon-plug',
                'class' => Settings::class,
                'order' => 500,
                'keywords' => '1Pilot 1Pilot.io OnePilot Dashboard',
                'permissions' => ['OnePilot.client.access_settings'],
            ],
        ];
    }

    public function registerSchedule($schedule)
    {
        $schedule->call(function () {
            Settings::set('last_cron_at', Carbon::now()->timestamp);
        })->everyTenMinutes();
    }

    public function registerPermissions()
    {
        return [
            'OnePilot.client.access_settings' => [
                'label' => 'Access to 1Pilot settings',
                'tab' => '1Pilot',
            ],
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('OnePilot.RunComposerUpdate', Console\RunComposerUpdate::class);
    }

    public function boot()
    {
        Handler::register();

        $this->registerRunComposerUpdateSchedule();
    }

    private function registerRunComposerUpdateSchedule()
    {
        if (!class_exists('System')) {
            return; // Only for OCv2
        }

        // Register schedule at the end to no impact website schedules
        Event::listen('console.schedule', function ($schedule) {
            $schedule->command(Console\RunComposerUpdate::class)
                ->when(ComposerUpdateScheduler::hasTask())
                ->withoutOverlapping();
        });
    }
}
