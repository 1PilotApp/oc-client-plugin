<?php

namespace OnePilot\Client\Classes;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OnePilot\Client\Contracts\PackageDetector;

class Composer
{
    /** @var array */
    protected static $installedPackages;

    /** @var \Illuminate\Support\Collection */
    protected static $packagesConstraints;

    protected $packagist = [];

    public function __construct($includeContraints = true)
    {
        /** @var PackageDetector $detector */
        $detector = app(PackageDetector::class);

        self::$installedPackages = $detector->getPackages();

        if ($includeContraints) {
            self::$packagesConstraints = $detector->getPackagesConstraints();
        }
    }

    /**
     * @param $name
     * @return object|null
     */
    public function getRawPackage($name)
    {
        return self::$installedPackages->where('name', $name)->first();
    }

    /**
     * @param $name
     * @return string|null
     */
    public function getPackageVersion($name)
    {
        return $this->getRawPackage($name)->version ?? null;
    }

    /**
     * @param string $name
     *
     * @return array|null
     */
    public function getPackage($name)
    {
        $package = self::$installedPackages->where('name', $name)->first();

        if (empty($package)) {
            return null;
        }

        return $this->generatePackageData($package);
    }

    /**
     * @param object $package
     *
     * @return array
     */
    private function generatePackageData($package)
    {
        $currentVersion = $this->removePrefix($package->version);
        $latestVersion = $this->getLatestPackageVersion($package->name, $currentVersion);

        return [
            'name' => Str::after($package->name, '/'),
            'code' => $package->name,
            'type' => 'package',
            'active' => 1,
            'version' => $currentVersion,
            'new_version' => $latestVersion['compatible'] ?? null,
            'last_available_version' => $latestVersion['available'] ?? null,
        ];
    }

    /**
     * Get latest (stable) version number of composer package
     *
     * @param string $packageName    The name of the package as registered on packagist, e.g. 'laravel/framework'
     * @param string $currentVersion If provided will ignore this version (if last one is $currentVersion will return null)
     *
     * @return \Illuminate\Support\Collection ['compatible' => $version, 'available' => $version]
     */
    public function getLatestPackageVersion($packageName, $currentVersion = null)
    {
        $packages = $this->getLatestPackage($packageName);

        return collect($packages)->map(function ($package) use ($currentVersion) {
            $version = $this->removePrefix(optional($package)->version);

            return $version == $currentVersion ? null : $version;
        });
    }

    /**
     * Get latest (stable) package from packagist
     *
     * @param string $packageName , the name of the package as registered on packagist, e.g. 'laravel/framework'
     *
     * @return array ['compatible' => (object) $version, 'available' => (object) $version]
     */
    private function getLatestPackage($packageName)
    {
        if (!class_exists(VersionParser::class)) {
            return null;
        }

        if (empty($versions = $this->getVersionsFromPackagist($packageName))) {
            return null;
        }

        $lastCompatibleVersion = null;
        $lastAvailableVersion = null;

        $packageConstraints = self::$packagesConstraints->get($packageName);

        foreach ($versions as $versionData) {
            $versionNumber = $versionData->version;
            $normalizeVersionNumber = $versionData->version_normalized;
            $stability = VersionParser::normalizeStability(VersionParser::parseStability($versionNumber));

            // only use stable version numbers
            if ($stability !== 'stable') {
                continue;
            }

            if (version_compare($normalizeVersionNumber, $lastAvailableVersion->version_normalized ?? '', '>=')) {
                $lastAvailableVersion = $versionData;
            }

            if (empty($packageConstraints)) {
                $lastCompatibleVersion = $lastAvailableVersion;
                continue;
            }

            // only use version that follow constraint
            if (
                version_compare($normalizeVersionNumber, $lastCompatibleVersion->version_normalized ?? '', '>=')
                && $this->checkConstraints($normalizeVersionNumber, $packageConstraints)
            ) {
                $lastCompatibleVersion = $versionData;
            }
        }

        if ($lastCompatibleVersion === $lastAvailableVersion) {
            $lastAvailableVersion = null;
        }

        return [
            'compatible' => $lastCompatibleVersion,
            'available' => $lastAvailableVersion,
        ];
    }

    /**
     * @param string $version
     * @param string $prefix
     *
     * @return string
     */
    private function removePrefix($version, $prefix = 'v')
    {
        if (empty($version) || !Str::startsWith($version, $prefix)) {
            return $version;
        }

        return substr($version, strlen($prefix));
    }

    private function checkConstraints($version, $constraints)
    {
        foreach ($constraints as $constraint) {
            if (Semver::satisfies($version, $constraint) !== true) {
                return false;
            }
        }

        return true;
    }

    private function getPackagistDetailUrl(string $packageName): string
    {
        return 'https://packagist.org/packages/' . $packageName . '.json';
    }

    private function storePackagistVersions(string $package, string $response)
    {
        $packagistInfo = json_decode($response);

        $this->packagist[$package] = $packagistInfo->package->versions;
    }

    private function getVersionsFromPackagist(string $package)
    {
        if (empty($versions = Arr::get($this->packagist, $package))) {
            try {
                $packagistInfo = json_decode(file_get_contents($this->getPackagistDetailUrl($package)));
                $versions = $packagistInfo->package->versions;
            } catch (\Exception $e) {
                return null;
            }
        }

        unset($this->packagist[$package]);

        if (!is_object($versions)) {
            return null;
        }

        return $versions;
    }
}
