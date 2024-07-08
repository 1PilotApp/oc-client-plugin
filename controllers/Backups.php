<?php

namespace OnePilot\Client\Controllers;

use DB;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OnePilot\Client\Classes\BackupsBrowser;
use OnePilot\Client\Classes\SpatieBackup;
use OnePilot\Client\Exceptions\OnePilotException;
use Spatie\Backup\Helpers\Format;
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

        if (empty($monitorConfig = config('backup.monitor_backups', config('backup.monitorBackups'))) || !is_array($monitorConfig)) {
            throw new OnePilotException("No Spatie Backup `backup.monitor_backups` config detected", 500);
        }

        $statuses = BackupDestinationStatusFactory::createForMonitorConfig($monitorConfig);

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
                $path = method_exists($newest, 'disk')
                    ? $newest->disk()->path($newest->path())
                    : $newest->path();

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

            if (method_exists($status, 'getHealthCheckFailure')) {
                if (!empty($failure = $status->getHealthCheckFailure())) {
                    $row['healthCheckFailure'] = [
                        'name' => $failure->healthCheck()->name(),
                        'message' => $failure->exception()->getMessage(),
                    ];
                }

                return $row;
            }

            /*
             * For legacy Spatie Backup versions
             */

            if (method_exists($status, 'dateOfNewestBackup') && !$status->dateOfNewestBackup()) {
                $row['healthCheckFailure'] = [
                    'name' => 'MaximumAgeInDays',
                    'message' => 'There are no backups of this application at all.',
                ];

                return $row;
            }

            if (method_exists($status, 'newestBackupIsTooOld') && $status->newestBackupIsTooOld()) {
                $newest = $status->dateOfNewestBackup() ?? null;

                $row['healthCheckFailure'] = [
                    'name' => 'MaximumAgeInDays',
                    'message' => $newest
                        ? 'The latest backup made on ' . $newest->format('Y-m-d h:i') . ' is considered too old.'
                        : 'The latest backup is too old.',
                ];

                return $row;
            }

            if (method_exists($status, 'usesTooMuchStorage') && $status->usesTooMuchStorage()) {
                if (!method_exists($status, 'usedStorage') || !method_exists($status, 'maximumAllowedUsageInBytes')) {
                    $row['healthCheckFailure'] = [
                        'name' => 'MaximumStorageInMegabytes',
                        'message' => 'The backups are using too much storage.',
                    ];

                    return $row;
                }

                $diskUsage = Format::humanReadableSize($status->usedStorage());
                $diskLimit = Format::humanReadableSize($status->maximumAllowedUsageInBytes());;

                $row['healthCheckFailure'] = [
                    'name' => 'MaximumStorageInMegabytes',
                    'message' => 'The backups are using too much storage. Current usage is ' . $diskUsage . ' which is higher than the allowed limit of ' . $diskLimit . '.',
                ];

                return $row;
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
