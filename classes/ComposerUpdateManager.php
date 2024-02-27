<?php

namespace OnePilot\Client\Classes;

use ApplicationException;
use Artisan;
use Exception;
use Illuminate\Support\Arr;
use System\Classes\UpdateManager;

class ComposerUpdateManager
{
    /** @var ComposerProcess */
    private $process;

    public function __construct()
    {
        $this->process = new ComposerProcess;
    }

    /**
     * @param string[] $plugins Vendor.Plugin
     *
     * @return string|null
     * @throws ApplicationException
     */
    public function updatePlugin(array $plugins)
    {
        if (empty($packages = $this->composerPackagesFromPluginsCode($plugins))) {
            throw new ApplicationException("Plugin not found", 400);
        }

        return $this->updatePackages($packages);
    }

    /**
     * @return string|null
     * @throws ApplicationException
     */
    public function updatePackages(array $packages)
    {
        $this->checkComposerVersion();

        $installedPackages = Arr::pluck($this->process->listPackages(), 'name');

        if (!empty($diff = array_diff($packages, $installedPackages))) {
            throw new ApplicationException('Packages "' . implode(", ", $diff) . '" is not installed');
        }

        return $this->process->updatePackages($packages);
    }

    /**
     * @return string|null
     * @throws ApplicationException
     */
    public function updateCore()
    {
        $this->checkComposerVersion();

        return $this->process->updatePackages(['october/*', 'laravel/framework']);
    }

    /**
     * Run composer install to ensure all works properly in case of issue during update
     */
    public function install()
    {
        return $this->process->install();
    }

    /**
     * @return string
     */
    public function runDatabaseMigrations()
    {
        try {
            retry(2, function () {
                Artisan::call('october:migrate');
            });
        } catch (\Throwable $e) {
            //
        }

        return Artisan::output();
    }

    /**
     * @param string[] $plugins Vendor.Plugin
     *
     * @return string[]
     */
    public function composerPackagesFromPluginsCode(array $plugins)
    {
        $updateManager = UpdateManager::instance();
        $packages = [];

        foreach ($plugins as $plugin) {
            try {
                $pluginDetails = $updateManager->requestPluginDetails($plugin);

                $packages[] = array_get($pluginDetails ?? [], 'composer_code');
            } catch (Exception $e) {
                //
            }
        }

        return array_filter($packages);
    }

    /**
     * @throws ApplicationException
     */
    public function checkComposerVersion()
    {
        if (version_compare($this->process->version(), '2.0.0', '<')) {
            throw new ApplicationException('Require composer 2');
        }
    }

    public function setOctoberCMSVersion()
    {
        if (empty($octoberSystemVersion = $this->process->getComposerOctoberSystemVersion())) {
            return null;
        }

        if (!empty($build = $this->getBuildFromVersion($octoberSystemVersion))) {
            UpdateManager::instance()->setBuild($build);
        }
    }

    /**
     * Return the patch version of a semver string
     * eg: 1.2.3 -> 3, 1.2.3-dev -> 3
     */
    protected function getBuildFromVersion(string $version)
    {
        $parts = explode('.', $version);
        if (count($parts) !== 3) {
            return null;
        }

        $lastPart = $parts[2];
        if (!is_numeric($lastPart)) {
            $lastPart = explode('-', $lastPart)[0];
        }

        if (!is_numeric($lastPart)) {
            return null;
        }

        return $lastPart;
    }
}
