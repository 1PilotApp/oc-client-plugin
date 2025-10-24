<?php

namespace OnePilot\Client\Classes;

use OnePilot\Client\Exceptions\OnePilotException;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

class BackupsBrowser
{
    /** @var int */
    private $currentPage;

    /** @var int */
    private $perPage;

    /** @var int */
    private $from;

    /** @var int */
    private $to;

    /** @var int */
    private $total = 0;

    public function setPagination(int $currentPage = 1, int $perPage = 20)
    {
        $this->currentPage = $currentPage;
        $this->perPage = $perPage;
        $this->from = (($currentPage - 1) * $perPage) + 1;
        $this->to = (($currentPage - 1) * $perPage) + $perPage;
    }

    /**
     * @return array
     * @throws OnePilotException
     */
    public function get()
    {
        if (!SpatieBackup::isSupported()) {
            throw new OnePilotException("Only Spatie Backup based solutions are currently supported", 500);
        }

        if (empty($monitorConfig = config('backup.monitor_backups', config('backup.monitorBackups'))) || !is_array($monitorConfig)) {
            throw new OnePilotException("No Spatie Backup `backup.monitor_backups` config detected", 500);
        }

        $statuses = BackupDestinationStatusFactory::createForMonitorConfig($monitorConfig);
        $status = $statuses->first();

        $destination = $status->backupDestination();

        return array_merge($this->paginationFields($destination->backups()->count()), [
            'name' => $destination->backupName(),
            'disk' => [
                'name' => $destination->diskName(),
                'driver' => SpatieBackup::driverFormat($destination->filesystemType()),
            ],
            'base_path' => base_path(),
            'data' => $destination->backups()
                ->forPage($this->currentPage, $this->perPage)
                ->map(function (\Spatie\Backup\BackupDestination\Backup $backup) {
                    return [
                        'date' => (string)$backup->date(),
                        'size' => method_exists($backup, 'sizeInBytes') ? $backup->sizeInBytes() : $backup->size() ?? '',
                        'file' => $backup->path(),
                        'path' => method_exists($backup, 'disk') ? $backup->disk()->path($backup->path()) : $backup->path(),
                    ];
                })
                ->values(),
        ]);
    }

    /** @return array */
    private function paginationFields($total)
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $total,
            'last_page' => (int)ceil($total / $this->perPage),
        ];
    }
}
