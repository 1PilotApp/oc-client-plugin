<?php

namespace OnePilot\Client\Classes;

use Illuminate\Support\Collection;
use October\Rain\Support\Traits\Singleton;
use Symfony\Component\Finder\SplFileInfo;

class Files
{
    use Singleton;

    /**
     * Get data for some important system files
     *
     * @return Collection
     */
    public function getFilesProperties()
    {
        $files = [
            '.env',
            '.htaccess',
            'index.php',
        ];

        $configFiles = $this->getConfigFiles();

        return collect($files + $configFiles)->transform(function ($relativePath, $absolutePath) {
            if (is_int($absolutePath)) {
                $absolutePath = base_path($relativePath);
            }

            if (!file_exists($absolutePath) || is_dir($absolutePath)) {
                return false;
            }

            $fp = fopen($absolutePath, 'r');
            $fstat = fstat($fp);
            fclose($fp);

            return [
                'path' => $relativePath,
                'size' => $fstat['size'],
                'mtime' => $fstat['mtime'],
                'checksum' => md5_file($absolutePath),
            ];
        })->filter()->values();
    }

    /**
     * @return array
     */
    private function getConfigFiles()
    {
        if (!file_exists($configPath = base_path('config'))) {
            return [];
        }

        return collect(File::allFiles($configPath))
            ->mapWithKeys(function (SplFileInfo $file) {
                return [
                    $file->getRealPath() => 'config' . DIRECTORY_SEPARATOR . $file->getRelativePathname(),
                ];
            })
            ->sort()
            ->toArray();
    }
}