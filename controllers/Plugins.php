<?php

namespace OnePilot\Client\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use OnePilot\Client\Classes\Response;
use OnePilot\Client\Classes\UpdateManager;
use OnePilot\Client\Exceptions\OnePilotException;
use Request;
use System\Models\PluginVersion;

class Plugins extends Controller
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

        if (empty($pluginCode = Request::get('slug'))) {
            throw new OnePilotException('Plugin slug parameter is missing', 400);
        }

        if (class_exists('System')) {
            throw new OnePilotException("Plugin update is currently not supported for October CMS v2", 500);
        }

        $manager = UpdateManager::instance();
        $pluginDetails = $manager->requestPluginDetails($pluginCode);

        $code = array_get($pluginDetails, 'code');
        $hash = array_get($pluginDetails, 'hash');

        try {
            $manager->downloadPlugin($code, $hash);
            $manager->extractPlugin($code, $hash);
            $manager->update();
        } catch (\Exception $e) {
            throw new OnePilotException('Error during plugin update', 500, $e);
        }

        return Response::make([
            'code' => $code,
            'hash' => $hash,
        ]);
    }

    public function all($updates)
    {
        $extensions = [];
        $plugins = PluginVersion::all();
        $pluginsDetails = $this->getPluginsDetails($plugins);

        foreach ($plugins as $key => $plugin) {
            $extensions[] = $this->formatPluginResponse($plugin, $updates, $pluginsDetails);
        }

        return $extensions;
    }

    /**
     * @param PluginVersion $plugin
     * @param array         $updates
     * @param Collection    $pluginsDetails
     *
     * @return array
     */
    private function formatPluginResponse($plugin, $updates, $pluginsDetails)
    {
        $extension = [
            'version' => $plugin->version,
            'new_version' => null,
            'name' => $plugin->name,
            'code' => $plugin->code,
            'type' => 'plugin',
            'active' => !$plugin->is_disabled,
            'update_enabled' => !$plugin->is_frozen,
            'changelog' => null,
        ];

        $details = $pluginsDetails->get($plugin->code);

        if ($details && array_key_exists('product_url', $details)) {
            $extension['authorurl'] = $details['product_url'];
        }

        if (array_key_exists($plugin->code, $updates)) {
            $extension['new_version'] = $updates[$plugin->code]['version'];
        }

        if (array_key_exists($plugin->code, $updates)) {
            $extension['changelog'] = $updates[$plugin->code]['updates'];
        }

        return $extension;
    }

    /**
     * @param Collection $plugins
     *
     * @return mixed
     * @internal param $plugin
     */
    private function getPluginsDetails($plugins)
    {
        $updateManager = UpdateManager::instance();

        $productsDetails = $updateManager->requestServerData('plugin/details', [
            'names' => $plugins->pluck('code')->toArray(),
        ]);

        return collect($productsDetails)
            ->filter(function ($item) {
                return !empty($item['code']);
            })
            ->mapWithKeys(function ($item) {
                return [$item['code'] => $item];
            });
    }
}
