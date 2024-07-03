<?php

namespace OnePilot\Client\Controllers;

use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OnePilot\Client\Classes\BackupsBrowser;
use OnePilot\Client\Classes\SpatieBackup;
use OnePilot\Client\Exceptions\OnePilotException;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

class Backups extends Controller
{
    /**
     * @return \Illuminate\Support\Collection
     * @throws OnePilotException
     */
    public function check()
    {
        if (!SpatieBackup::isSupported()) {
            throw new OnePilotException("Only Spatie Backup based solutions are currently supported", 500);
        }

        $statuses = BackupDestinationStatusFactory::createForMonitorConfig(config('backup.monitor_backups'));

        return $statuses->map(function (BackupDestinationStatus $status) {
            $destination = $status->backupDestination();
            $newest = $destination->newestBackup();

            $row = [
                'name' => $destination->backupName(),
                'disk' => [
                    'name' => $destination->diskName(),
                    'driver' => SpatieBackup::driverFormat($destination->filesystemType()),
                ],
                'isReachable' => $destination->isReachable(),
                'isHealthy' => $status->isHealthy(),
                'amount' => $destination->backups()->count(),
                'usedStorage' => $destination->usedStorage(),
            ];

            if ($newest && $newest->exists()) {
                $path = $newest->disk()->path($newest->path());
                $basePath = base_path();

                if ($row['disk']['driver'] === 'local' && strpos($path, $basePath) === 0) {
                    $path = substr($path, strlen($basePath) + 1);
                }

                $row['lastBackup'] = [
                    'date' => (string)$newest->date(),
                    'size' => method_exists($newest, 'sizeInBytes') ? $newest->sizeInBytes() : $newest->size() ?? '',
                    'file' => $newest->path(),
                    'path' => $path,
                ];
            }

            if (!empty($failure = $status->getHealthCheckFailure())) {
                $row['healthCheckFailure'] = [
                    'name' => $failure->healthCheck()->name(),
                    'message' => $failure->exception()->getMessage(),
                ];
            }

            return $row;
        });
    }

    public function browse(Request $request)
    {
        $browser = new BackupsBrowser();
        $browser->setPagination($request->get('page', 1), $request->get('per_page', 50));

        return $browser->get();
    }
}
