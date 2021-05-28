<?php

namespace OnePilot\Client\Classes;

use Cms\Classes\ThemeManager;
use System;
use System\Models\Parameter;
use System\Models\PluginVersion;

class OctoberUpdateServer
{
    /** @var UpdateManager */
    private $updateManager;

    /** @var ThemeManager|null */
    private $themeManager;

    public function __construct()
    {
        $this->updateManager = UpdateManager::instance();
        $this->themeManager = class_exists(ThemeManager::class) ? ThemeManager::instance() : null;
    }

    /**
     * @return array
     * @see UpdateManager::requestUpdateList
     */
    public function availableUpdates()
    {
        $installed = PluginVersion::all();
        $versions = $installed->lists('version', 'code');
        $names = $installed->lists('name', 'code');
        $build = Parameter::get('system::core.build');
        $themes = [];

        if ($this->themeManager) {
            $themes = array_keys($this->themeManager->getInstalled());
        }

        $params = [
            'plugins' => base64_encode(json_encode($versions)),
            'themes' => base64_encode(json_encode($themes)),
            'build' => $build,
        ];

        if (class_exists(System::class) && !empty(System::VERSION)) {
            // OCMS V2
            $params['version'] = System::VERSION;
            $apiRoute = 'project/check';
        } else {
            // OCMS V1
            $params['core'] = $this->getHash();
            $params['force'] = false;
            $apiRoute = 'core/update';
        }

        try {
            $result = $this->updateManager->requestServerData($apiRoute, $params);
        } catch (\ApplicationException $e) {
            return [];
        }

        /*
         * Inject known core build
         */
        if ($core = array_get($result, 'core')) {
            $result['core'] = $core;
        }

        /*
         * Inject the application's known plugin name and version
         */
        $plugins = [];
        foreach (array_get($result, 'plugins', []) as $code => $info) {
            $info['name'] = $names[$code] ?? $code;
            $info['old_version'] = $versions[$code] ?? false;

            $plugins[$code] = $info;
        }

        $result['plugins'] = $plugins;

        /*
         * Strip out themes that have been installed before
         */
        if ($this->themeManager) {
            $themes = [];
            foreach (array_get($result, 'themes', []) as $code => $info) {
                if (!$this->themeManager->isInstalled($code)) {
                    $themes[$code] = $info;
                }
            }
            $result['themes'] = $themes;
        }

        return $result;
    }

    /**
     * Returns the currently installed system hash.
     * @return string
     */
    public function getHash()
    {
        return Parameter::get('system::core.hash', md5('NULL'));
    }
}
