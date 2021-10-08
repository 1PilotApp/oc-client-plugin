<?php namespace OnePilot\Client\Console;

use ApplicationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use October\Rain\Network\Http;
use OnePilot\Client\Classes\ComposerUpdateManager;
use OnePilot\Client\Classes\ComposerUpdateScheduler;

class RunComposerUpdate extends Command
{
    protected $name = '1pilot:composer-update';

    protected $description = 'Run composer update triggered from 1Pilot.io';

    protected $hidden = true;

    public function handle()
    {
        if (!ComposerUpdateScheduler::hasTask()) {
            $this->info('Nothing to do');

            return;
        }

        if (empty($tasks = ComposerUpdateScheduler::getTasks())) {
            $this->info('No tasks to run (invalid schedule file format)');

            return;
        }

        if (!ComposerUpdateScheduler::schedulerIsWritable()) {
            $this->error($message = '"' . ComposerUpdateScheduler::path() . '" should be writable from the user that run October CMS crons');

            Log::error($message);

            return;
        }

        $updateManager = new ComposerUpdateManager();

        foreach ($tasks as $task) {
            $output = '';
            $start = time();

            try {
                $updateManager->checkComposerVersion();
            } catch (ApplicationException $e) {
                $this->callCallbackUrl($task, $start, $e->getMessage(), false, null, 'composer-2');

                continue;
            }

            try {
                $output .= ($task['packages'] == ['october'])
                    ? $updateManager->updateCore()
                    : $updateManager->updatePackages($task['packages']);
            } catch (ApplicationException $e) {
                $fallbackSuccess = $this->runInstallToEnsureAllWorks();
                $this->callCallbackUrl($task, $start, $e->getMessage(), false, $fallbackSuccess);

                continue;
            }

            $output .= PHP_EOL . PHP_EOL;

            $output .= $updateManager->runDatabaseMigrations();

            $updateManager->setOctoberCMSVersion(); // ensure OctoberCMS version is set from composer

            $this->callCallbackUrl($task, $start, $output, true);
        }

        ComposerUpdateScheduler::clear();
    }

    /**
     * @param array  $task
     * @param int    $start
     * @param string $content
     * @param bool   $success
     * @param bool   $fallbackSuccess
     * @param string $errorCode error code
     */
    private function callCallbackUrl(
        $task,
        $start,
        $content,
        $success = false,
        $fallbackSuccess = null,
        $errorCode = null
    ) {
        Http::post($task['callbackUrl'], function ($http) use (
            $errorCode,
            $fallbackSuccess,
            $start,
            $task,
            $success,
            $content
        ) {
            /** @var Http $http */
            $http->header('Accept', 'application/json');

            $http->data('status', $success ? 'success' : 'failed');
            $http->data('content', $content);
            $http->data('uuid', $task['uuid']);
            $http->data('packages', $task['packages']);
            $http->data('start_at', $start);
            $http->data('end_at', time());

            if (!empty($errorCode)) {
                $http->data('error_code', $errorCode);
            }

            if (!$success) {
                $http->data('fallback_status', $fallbackSuccess ? 'success' : 'failed');
            }
        });
    }

    /**
     * @return bool
     */
    private function runInstallToEnsureAllWorks()
    {
        $updateManager = new ComposerUpdateManager();

        try {
            $updateManager->install();

            return true;
        } catch (\Exception $e) {

            Log::info($e->getMessage(), [$e]);

            return false;
        }
    }
}
