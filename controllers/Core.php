<?php

namespace OnePilot\Client\Controllers;

use Illuminate\Routing\Controller;
use OnePilot\Client\Classes\Response;
use OnePilot\Client\Classes\UpdateManager;
use OnePilot\Client\Exceptions\OnePilotException;

class Core extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \ApplicationException
     */
    public function update()
    {
        if (config('onepilot.client::disable-core-update')) {
            throw new OnePilotException('Core update from 1Pilot has been blocked in the site configuration', 400);
        }

        if (class_exists('System')) {
            throw new OnePilotException("Core update is currently not supported for October CMS v2", 500);
        }

        $manager = UpdateManager::instance();
        $updateList = $manager->requestUpdateList(true);

        $coreHash = array_get($updateList, 'core.hash');
        $coreBuild = array_get($updateList, 'core.build');

        $manager->downloadCore($coreHash);
        $manager->extractCore();
        $manager->setBuild($coreBuild, $coreHash);
        $manager->update();

        return Response::make([
            'build' => $coreBuild,
            'hash' => $coreHash,
        ]);
    }
}
