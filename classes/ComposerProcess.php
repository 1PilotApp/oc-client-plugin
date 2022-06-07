<?php

namespace OnePilot\Client\Classes;

use ApplicationException;
use Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Inspired from the OctoberCMS v2 Composer process class
 * @see \October\Rain\Process\Composer
 */
class ComposerProcess
{
    /**
     * update runs the "composer update" command
     */
    public function update()
    {
        $this->runComposerCommand('update');
    }

    /**
     * @param array $packages
     *
     * @return string
     *
     * @throws ApplicationException
     */
    public function updatePackages(array $packages)
    {
        if (empty($packages)) {
            throw new ApplicationException('No package provided', 500);
        }

        $process = new Process($this->prepareComposerArguments([
            'update',
            $packages,
            $this->generateDevFlag(),
            '--with-dependencies',
        ]));

        $process->setTimeout(600);
        $process->run();

        $command = $process->getCommandLine();

        if (!$process->isSuccessful()) {
            Log::error($command . PHP_EOL . $process->getErrorOutput());

            throw new ApplicationException($process->getErrorOutput(), 500);
        }

        return $process->getErrorOutput() . PHP_EOL . $process->getOutput();
    }

    public function install()
    {
        try {
            $devFlag = $this->generateDevFlag();
        } catch (ApplicationException $e) {
        }

        $process = new Process($this->prepareComposerArguments([
            'install',
            $devFlag ?? null,
        ]));

        $process->run();

        $command = $process->getCommandLine();

        if ($process->isSuccessful()) {
            Log::info($command . PHP_EOL . $process->getErrorOutput() . $process->getOutput());

            return $process->getErrorOutput() . PHP_EOL . $process->getOutput();
        }

        Log::error($command . PHP_EOL . $process->getErrorOutput());

        throw new ApplicationException($process->getErrorOutput(), 500);
    }

    /**
     * isInstalled returns true if composer is installed
     */
    public function isInstalled()
    {
        $process = new Process($this->prepareComposerArguments(['--version']));
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * get composer version
     * @throws ApplicationException
     */
    public function version()
    {
        $process = new Process($this->prepareComposerArguments(['--version']));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ApplicationException('Composer is not properly installed `composer --version` returned error');
        }

        return Str::before(trim(str_replace([
            'Composer version',
            'Composer',
        ], '', $process->getOutput())), ' ');
    }

    /**
     * listPackages returns a list of installed packages
     */
    public function listPackages()
    {
        $process = new Process($this->prepareComposerArguments([
            'show',
            '--direct',
            '--format=json',
        ]));
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $installed = json_decode($process->getOutput(), true);

        $packages = [];

        foreach (array_get($installed, 'installed', []) as $package) {
            $package['version'] = ltrim(array_get($package, 'version'), 'v');
            $packages[] = $package;
        }

        return $packages;
    }

    /**
     * runComposerCommand is a helper for running a git command
     */
    public function runComposerCommand(...$parts)
    {
        $process = new Process($this->prepareComposerArguments($parts));
        $process->run();

        return $process->getOutput();
    }

    /**
     * @return string
     * @throws ApplicationException
     */
    public function generateDevFlag()
    {
        return $this->isDevPackagesInstalled() ? '' : '--no-dev';
    }

    /**
     * Return true if the last install/update was done with `--dev` flag
     * @return bool
     * @throws ApplicationException
     */
    public function isDevPackagesInstalled()
    {
        if (!file_exists($path = base_path('vendor/composer/installed.json'))) {
            throw new ApplicationException("`vendor/composer/installed.json` not existing, can't properly detect dev/no-dev mode");
        }

        $content = json_decode(file_get_contents($path), true);

        if (is_null($dev = $content['dev'] ?? null)) {
            throw new ApplicationException("`vendor/composer/installed.json` is invalid can't properly detect dev/no-dev mode");
        }

        return (bool)$dev;
    }

    public function getComposerOctoberSystemVersion()
    {
        try {
            $packages = $this->listPackages();

            foreach ($packages as $package) {
                $packageName = $package['name'] ?? null;
                if (mb_strtolower($packageName) === 'october/system') {
                    return $package['version'] ?? null;
                }
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * prefix arguments with the right binary path for comoser
     */
    protected function prepareComposerArguments($parts)
    {
        $parts = Arr::flatten(array_filter($parts));

        if ($composerBin = Config::get('system.composer_binary')) {
            return array_merge([$composerBin], $parts, ['--no-ansi']);
        }

        $phpBin = (new PhpExecutableFinder)->find();

        $composerBin = (new ExecutableFinder)->find('composer', 'composer', [
            '/usr/local/bin',
            '/usr/bin',
            '/usr/sbin',
            '/bin',
            '/sbin',
        ]);

        return array_merge([
            $phpBin,
            $composerBin,
        ], $parts, ['--no-ansi']);
    }
}
