<?php

namespace OnePilot\Client\Controllers;

use Cms\Classes\ThemeManager;
use DirectoryIterator;
use Illuminate\Routing\Controller;
use October\Rain\Filesystem\Zip;
use October\Rain\Network\Http;
use OnePilot\Client\Classes\File;
use OnePilot\Client\Classes\Response;
use OnePilot\Client\Classes\UpdateManagerFactory;
use OnePilot\Client\Exceptions\OnePilotException;
use Request;
use SplFileInfo;
use System\Classes\PluginManager;
use System\Models\PluginVersion;
use Yaml;

class Extensions extends Controller
{
    protected $tempFolder = null;

    /**
     * install a plugin or a theme
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws OnePilotException
     */
    public function install()
    {
        if (config('onepilot.client::disable-plugin-install')) {
            throw new OnePilotException('Plugin install from 1Pilot has been blocked in the site configuration', 400);
        }

        if (empty($url = Request::get('url'))) {
            throw new OnePilotException('URL parameter is missing', 400);
        }

        $filePath = $this->download($url);
        $this->tempFolder = temp_path() . '/' . uniqid('1Pilot-exts-');
        $this->extract($filePath, $this->tempFolder);

        $extDescribeFile = $this->findExtensionRecursively($this->tempFolder);

        if (empty($extDescribeFile)) {
            throw new OnePilotException('Any valid Plugin or Theme vas found in the provided archive', 400);
        }

        if ($this->isPlugin($extDescribeFile)) {
            return $this->installPlugin($extDescribeFile);
        }

        return $this->installTheme($extDescribeFile);
    }

    /**
     * @param SplFileInfo $file Instance of SplFileInfo for the plugin.php file
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws OnePilotException
     */
    private function installPlugin($file)
    {
        $namespaceArray = $this->getNamespaceFromPhpFile($file);
        $namespace = implode('\\', $namespaceArray);

        if (count($namespaceArray) != 2) {
            throw new OnePilotException('invalid Plugin.php file', 500, null, [
                'plugin_file' => $file->getPath() . '/' . $file->getFilename(),
                'namespace' => $namespace,
            ]);
        }

        $pluginCode = implode('.', $namespaceArray);
        $targetPath = plugins_path(mb_strtolower(implode('/', $namespaceArray)));

        if (File::exists($targetPath)) {
            File::deleteDirectory($targetPath);
        }

        // Move files in the rights directory
        File::copyDirectory($file->getPath(), $targetPath);
        File::recursiveChmod($targetPath, null, null);
        File::deleteDirectory($this->tempFolder);

        // Instantiate the plugin Class
        $pluginClass = PluginManager::instance()->loadPlugin($namespace, $targetPath);

        // Apply DB updates
        $updateManager = UpdateManagerFactory::instance();
        $updateManager->updatePlugin($pluginCode);

        // Get plugin version from the DB
        $pluginVersion = PluginVersion::where('code', $pluginCode)->first()->version ?? null;

        if (is_null($pluginVersion) && is_null($pluginClass)) {
            throw new OnePilotException('An error occur on the plugin install process, please retry', 500, null, [
                'plugin_file' => $file->getPath() . '/' . $file->getFilename(),
                'namespace' => $namespace,
            ]);
        }

        // Get details from the plugin Class
        $pluginDetails = $pluginClass->pluginDetails();

        return Response::make([
            'type' => 'plugin',
            'name' => $pluginDetails['name'],
            'author' => $pluginDetails['author'],
            'code' => $pluginCode,
            'version' => $pluginVersion,
        ]);
    }

    /**
     * @param SplFileInfo $file
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws OnePilotException
     */
    private function installTheme($file)
    {
        if (!class_exists(ThemeManager::class)) {
            throw new OnePilotException('"october/cms" module is not installed', 500);
        }

        $themeManager = ThemeManager::instance();

        $theme = Yaml::parseFile($file->getPathname());

        if (empty($theme) || empty($theme['name'])) {
            throw new OnePilotException('invalid theme.yaml file', 500, null, [
                'theme_file' => $file->getPath() . '/' . $file->getFilename(),
            ]);
        }

        // Code is not required using name if not defined
        $themeCode = $theme['code'] ?: $theme['name'];

        $themeDir = strtolower(str_replace(['.', '/'], '-', $themeCode));

        // Move files in the rights directory
        File::copyDirectory($file->getPath(), themes_path($themeDir));
        File::recursiveChmod(themes_path($themeDir), null, null);
        File::deleteDirectory($this->tempFolder);

        $themeManager->setInstalled($themeCode);

        return Response::make([
            'type' => 'theme',
            'name' => $theme['name'],
            'author' => $theme['author'],
            'code' => $themeCode,
        ]);
    }


    /**
     * @param $pluginUrl
     *
     * @return string
     * @throws OnePilotException
     */
    private function download($pluginUrl)
    {
        $filePath = temp_path() . '/' . uniqid('1Pilot-exts-') . '.zip';
        $result = Http::get($pluginUrl, function ($http) use ($filePath) {
            $http->toFile($filePath);
        });

        if ($result->code != 200) {
            throw new OnePilotException('Error when downloading plugin package', 500, null, [
                'plugin_url' => $pluginUrl,
                'result_code' => $result->code,
            ]);
        }

        return $filePath;
    }

    /**
     * @param $filePath
     * @param $tmpFolder
     *
     * @return bool
     * @throws OnePilotException
     */
    private function extract($filePath, $tmpFolder)
    {
        if (Zip::extract($filePath, $tmpFolder)) {
            File::delete($filePath);

            return true;
        }

        File::delete($filePath);
        throw new OnePilotException('Can\'t extract file to ' . $tmpFolder, 500);
    }

    /**
     * Find the base file of a plugin or theme
     *
     * theme.yaml if it's a theme
     * Plugin.php if it's a plugin
     *
     * @param $basePath
     *
     * @return \SplFileInfo
     */
    private function findExtensionRecursively($basePath)
    {
        $iterator = new DirectoryIterator($basePath);
        $iterator->rewind();

        $dirs = [];

        foreach ($iterator as $file) {
            if ($file->isDot() || $file->isLink()) {
                continue;
            }

            // We will process folders later
            if ($file->isDir()) {
                $dirs[] = $file->getPathname();
                continue;
            }

            if ($this->isPlugin($file) || $this->isTheme($file)) {
                return $file;
            }
        }

        foreach ($dirs as $dir) {
            if ($path = $this->findExtensionRecursively($dir)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param string|SplFileInfo $fileName
     *
     * @return bool
     */
    private function isPlugin($fileName)
    {
        if ($fileName instanceof SplFileInfo) {
            $fileName = $fileName->getFilename();
        }

        return $fileName === 'Plugin.php';
    }

    /**
     * @param string|SplFileInfo $fileName
     *
     * @return bool
     */
    private function isTheme($fileName)
    {
        if ($fileName instanceof SplFileInfo) {
            $fileName = $fileName->getFilename();
        }

        return $fileName === 'theme.yaml';
    }

    /**
     * @param string|SplFileInfo $filePath
     *
     * @return array
     */
    private function getNamespaceFromPhpFile($filePath)
    {
        if ($filePath instanceof SplFileInfo) {
            $filePath = $filePath->getPathname();
        }

        $namespace = [];
        $buffer = '';
        $i = 0;

        $fp = fopen($filePath, 'r');

        while (!$namespace) {
            if (feof($fp)) {
                break;
            }

            $buffer .= fread($fp, 512);
            $tokens = token_get_all($buffer);

            if (strpos($buffer, '{') === false) {
                continue;
            }

            for (; $i < count($tokens); $i++) {
                if ($tokens[$i][0] !== T_NAMESPACE) {
                    continue;
                }

                for ($j = $i + 1; $j < count($tokens); $j++) {
                    if (defined('T_NAME_QUALIFIED') && $tokens[$j][0] === T_NAME_QUALIFIED) {
                        $namespace = explode('\\', $tokens[$j][1]);
                        break;
                    }

                    if ($tokens[$j][0] === T_STRING) {
                        $namespace[] = $tokens[$j][1];
                    } elseif ($tokens[$j] === '{' || $tokens[$j] === ';') {
                        break;
                    }
                }
            }
        }

        return $namespace;
    }

}