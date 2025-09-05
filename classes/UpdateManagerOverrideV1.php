<?php

namespace OnePilot\Client\Classes;

use ApplicationException;
use Config;
use Lang;
use October\Rain\Filesystem\Zip;
use October\Rain\Network\Http;
use Request;
use System\Models\Parameter;
use System\Models\PluginVersion;
use Url;

/**
 * Override \System\Classes\UpdateManager
 * to properly report extension update for OCMS version <1.0.475
 *  Gateway use protocol_version 1.3
 *
 * to allow plugin updates ( requestServerFile, extractPlugin )
 */
class UpdateManagerOverrideV1 extends \System\Classes\UpdateManager
{
    /**
     * Modifies the Network HTTP object with common attributes.
     *
     * @param Http  $http     Network object
     * @param array $postData Post data
     *
     * @return void
     */
    protected function applyHttpAttributes($http, $postData)
    {
        // Running October CMS 2.0
        if (class_exists('System')) {
            parent::applyHttpAttributes($http, $postData);

            return;
        }

        // Running Winter CMS
        if (class_exists('\Winter\Storm\Foundation\Application')) {
            parent::applyHttpAttributes($http, $postData);

            return;
        }

        $postData['protocol_version'] = '1.3';
        $postData['client'] = 'October CMS';

        $postData['server'] = base64_encode(json_encode([
            'php' => PHP_VERSION,
            'url' => Url::to('/'),
            'ip' => Request::ip(),
            'since' => PluginVersion::orderBy('created_at')->value('created_at'),
        ]));

        if ($projectId = Parameter::get('system::project.id')) {
            $postData['project'] = $projectId;
        }

        if (Config::get('cms.edgeUpdates', false)) {
            $postData['edge'] = 1;
        }

        if ($this->key && $this->secret) {
            $postData['nonce'] = $this->createNonce();
            $http->header('Rest-Key', $this->key);
            $http->header('Rest-Sign', $this->createSignature($postData, $this->secret));
        }

        if ($credentials = Config::get('cms.updateAuth')) {
            $http->auth($credentials);
        }

        $http->noRedirect();
        $http->data($postData);
    }

    /**
     * Downloads a file from the update server.
     *
     * @param string $uri          Gateway API URI
     * @param string $fileCode     A unique code for saving the file.
     * @param string $expectedHash The expected file hash of the file.
     * @param array  $postData     Extra post data
     *
     * @return void
     */
    public function requestServerFile($uri, $fileCode, $expectedHash, $postData = [])
    {
        // October CMS 2.0
        if (class_exists('System')) {
            parent::requestServerFile($uri, $fileCode, $expectedHash, $postData);

            return;
        }

        $filePath = $this->getFilePath($fileCode);

        $result = Http::post($this->createServerUrl($uri), function ($http) use ($postData, $filePath) {
            $this->applyHttpAttributes($http, $postData);
            $http->toFile($filePath);
        });

        if (in_array($result->code, [301, 302])) {
            if ($redirectUrl = array_get($result->info, 'redirect_url')) {
                $result = Http::get($redirectUrl, function ($http) use ($postData, $filePath) {
                    $http->toFile($filePath);
                });
            }
        }

        if ($result->code != 200) {
            throw new ApplicationException(File::get($filePath));
        }
    }

    /**
     * Extracts a plugin after it has been downloaded.
     */
    public function extractPlugin($name, $hash)
    {
        $fileCode = $name . $hash;
        $filePath = $this->getFilePath($fileCode);
        $innerPath = str_replace('.', '/', strtolower($name));

        if (!Zip::extract($filePath, plugins_path($innerPath))) {
            throw new ApplicationException(Lang::get('system::lang.zip.extract_failed', ['file' => $filePath]));
        }

        @unlink($filePath);
    }
}
