<?php

namespace OnePilot\Client\Controllers;

use Illuminate\Routing\Controller;
use OnePilot\Client\Classes\ComposerUpdateManager;
use OnePilot\Client\Classes\ComposerUpdateScheduler;
use OnePilot\Client\Classes\Response;
use OnePilot\Client\Exceptions\OnePilotException;
use Request;

class Composer extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws OnePilotException
     */
    public function update()
    {
        if (config('onepilot.client::disable-plugin-update')) {
            throw new OnePilotException('Plugin update from 1Pilot has been blocked in the site configuration', 400);
        }

        if (!class_exists('System')) {
            throw new OnePilotException("Composer update is only supported for October CMS v2", 500);
        }

        $pluginsCode = Request::post('plugins');
        $packages = Request::post('packages');

        if (empty($pluginsCode) && empty($packages)) {
            throw new OnePilotException('plugins parameter is missing', 400);
        }

        if (empty($callbackUrl = Request::post('callback'))) {
            throw new OnePilotException('callback parameter is missing', 400);
        }

        if (empty($uuid = Request::post('uuid'))) {
            throw new OnePilotException('uuid parameter is missing', 400);
        }

        $updateManager = new ComposerUpdateManager();

        // Check that we find the package before registering the update job
        if (empty($packages) && empty($packages = $updateManager->composerPackagesFromPluginsCode($pluginsCode))) {
            throw new OnePilotException("Plugin not found", 400);
        }

        ComposerUpdateScheduler::registerTask($packages, $uuid, $callbackUrl);

        return Response::make([
            'type' => 'cron',
            'plugins' => $pluginsCode ?? null,
            'packages' => $packages,
        ]);
    }

    /**
     * @throws OnePilotException
     */
    public function coreUpdate()
    {
        if (config('onepilot.client::disable-core-update')) {
            throw new OnePilotException('Core update from 1Pilot has been blocked in the site configuration', 400);
        }

        if (!class_exists('System')) {
            throw new OnePilotException("Composer update is only supported for October CMS v2", 500);
        }

        if (empty($callbackUrl = Request::post('callback'))) {
            throw new OnePilotException('callback parameter is missing', 400);
        }

        if (empty($uuid = Request::post('uuid'))) {
            throw new OnePilotException('uuid parameter is missing', 400);
        }

        ComposerUpdateScheduler::registerTask(['october'], $uuid, $callbackUrl);

        return Response::make([
            'type' => 'cron',
            'plugins' => $pluginsCode ?? null,
            'packages' => ['october'],
        ]);
    }
}
