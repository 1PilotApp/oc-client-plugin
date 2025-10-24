<?php

namespace OnePilot\Client\Controllers;

use DB;
use Illuminate\Routing\Controller;
use OnePilot\Client\Classes\Composer;
use OnePilot\Client\Classes\Files;
use OnePilot\Client\Classes\LogsOverview;
use OnePilot\Client\Classes\OctoberUpdateServer;
use OnePilot\Client\Classes\Response;
use OnePilot\Client\Classes\SpatieBackup;
use OnePilot\Client\Classes\UpdateManagerFactory;
use OnePilot\Client\Models\Settings;
use System\Models\Parameter;

class Validate extends Controller
{
    const CONFIGS_TO_MONITOR = [
        'app.debug',
        'app.timezone',
        'cms.activeTheme',
        'cms.backendUri',
        'cms.disableCoreUpdates',
        'cms.edgeUpdate',
        'cms.enableCsrfProtection',
        'cms.enableSafeMode',
    ];

    const CONFIGS_TO_MONITOR_OCMSV2 = [
        'app.debug',
        'app.timezone',
        'cms.active_theme',
        'backend.uri',
        'system.enable_csrf_protection',
        'cms.safe_mode',
    ];

    public function validate()
    {
        $updates = (new OctoberUpdateServer)->availableUpdates();

        return Response::make([
            'core' => $this->core($updates),
            'servers' => $this->servers(),
            'plugins' => (new Plugins)->all(array_get($updates, 'plugins', [])),
            'themes' => (new Themes)->all(array_get($updates, 'themes', [])),
            'files' => Files::instance()->getFilesProperties(),
            'errors' => $this->errorsOverview(),
            'extra' => $this->extra(),
            'nextSteps' => $this->nextSteps(),
        ]);
    }

    private function core($updates)
    {
        $updateManager = UpdateManagerFactory::instance();

        $version = method_exists($updateManager, 'getCurrentVersion')
            ? $updateManager->getCurrentVersion() // OCMS V2
            : Parameter::get('system::core.build', 1); // OCMS V1

        if (empty($version)) {
            $package = (new Composer)->getPackage('october/system');

            if (!empty($package)) {
                return [
                    'version' => (string)$package['version'] ?? null,
                    'new_version' => (string)$package['new_version'] ?? null,
                    'update_enabled' => !config('cms.disableCoreUpdates', false),
                    'changelog' => null,
                ];
            }
        }

        return [
            'version' => (string)$version,
            'new_version' => (string)array_get($updates, 'core.version', array_get($updates, 'core.build')),
            'update_enabled' => !config('cms.disableCoreUpdates', false),
            'changelog' => array_get($updates, 'core.updates'),
        ];
    }

    protected function servers()
    {
        $serverWeb = $_SERVER['SERVER_SOFTWARE'] ?: getenv('SERVER_SOFTWARE') ?? null;

        return [
            'php' => phpversion(),
            'web' => $serverWeb,
            'mysql' => $this->dbVersion(),
        ];
    }

    private function errorsOverview()
    {
        try {
            return (new LogsOverview())->get();
        } catch (\Exception $e) {
        }
    }

    /**
     * @return array
     */
    private function extra()
    {
        $extra = [
            'storage_dir_writable' => is_writable(base_path('storage')),
            'last_cron_at' => null,
        ];

        $configs = class_exists('System') ? self::CONFIGS_TO_MONITOR_OCMSV2 : self::CONFIGS_TO_MONITOR;

        foreach ($configs as $config) {
            $extra[$config] = config($config);
        }

        $onepilotConfigs = [
            'onepilot.client::disable-plugin-install',
            'onepilot.client::disable-plugin-update',
            'onepilot.client::disable-core-update',
        ];

        foreach ($onepilotConfigs as $config) {
            if (config($config)) {
                $extra[$config] = config($config);
            }
        }

        if (Settings::get('last_cron_at')) {
            $extra['last_cron_at'] = (int)Settings::get('last_cron_at');
        }

        return $extra;
    }

    /**
     * @return string|void
     */
    private function dbVersion()
    {
        try {
            $connection = config('database.default');
            $driver = config("database.connections.{$connection}.driver");
            switch ($driver) {
                case 'mysql':
                    return $this->mysqlVersion();
                case 'sqlite':
                    return $this->sqliteVersion();
            }
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * @return string|null
     */
    private function mysqlVersion()
    {
        $result = DB::select('SELECT VERSION() as version;');

        return $result[0]->version ?? null;
    }

    /**
     * @return string|null
     */
    private function sqliteVersion()
    {
        $result = DB::select('select "SQLite " || sqlite_version() as version');

        return $result[0]->version ?? null;
    }

    /**
     * List next validation steps that could/should be run
     *
     * @return array
     */
    private function nextSteps()
    {
        $steps = [];

        if (SpatieBackup::isSupported()) {
            $steps[] = 'backup';
        }

        return $steps;
    }
}
