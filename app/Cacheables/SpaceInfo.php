<?php

namespace App\Cacheables;

use App\Models\Image;
use DateTime;
use Illuminate\Support\Facades\Storage;

class SpaceInfo extends CacheableBase
{
    public const TTL = (60 * 10); // На 10 минут в кеш

    public readonly int $total;
    public readonly int $free;
    public readonly int $used;
    public readonly int $appUsed;
    public readonly int $systemUsed;
    public readonly int $usedPercent;
    public readonly DateTime $gotAt;

    public function __construct($total, $free, $appUsed)
    {
        $this->total = $total;
        $this->free = $free;
        $this->appUsed = $appUsed;
        $this->used = $used = $total - $free;
        $this->systemUsed = $used - $appUsed;
        $this->usedPercent = (int) round($used / $total * 100);
        $this->isUploadDisabled = $this->usedPercent >= config('setups.upload_disable_percentage');
        $this->gotAt = now();
    }

    public static function function(?array $args): SpaceInfo
    {
        // Базовый путь к storage
        $baseStoragePath = Storage::path('');

        // Папки для глубокого сканирования на наличие симлинков/дисков
        $targetFolders = [
            Storage::path('images'),
            Storage::path('users'),
        ];

        // Собираем уникальные устройства. Начинаем с основного диска storage
        $detectedDevices = [];
        $mainDevId = null;

        $mainLstat = @lstat($baseStoragePath);
        if ($mainLstat) {
            $mainDevId = $mainLstat['dev'];
            $totalDisk = @disk_total_space($baseStoragePath);
            $freeDisk  = @disk_free_space($baseStoragePath);

            if ($totalDisk !== false && $freeDisk !== false) {
                $detectedDevices[$mainDevId] = [
                    'total' => $totalDisk,
                    'free'  => $freeDisk,
                ];
            }
        }

        // Сканируем вложенные папки на предмет других дисков/симлинков
        foreach ($targetFolders as $folderPath) {
            if (!is_dir($folderPath)) {
                continue;
            }

            $flags = \FilesystemIterator::SKIP_DOTS;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folderPath, $flags),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isLink() || $item->isDir()) {
                    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $item->getPathname());

                    $lstat = @lstat($path);
                    if (!$lstat) {
                        continue; // Пропускаем, если ссылку нельзя прочитать
                    }

                    $devId = $lstat['dev'];
                    $finalPath = $path;

                    if (is_link($path)) {
                        $realTarget = @readlink($path);
                        if ($realTarget && file_exists($path)) {
                            $finalPath = realpath($path);
                            $statTarget = @stat($finalPath);
                            if ($statTarget) {
                                $devId = $statTarget['dev'];
                            }
                        } else {
                            continue; // Пропускаем битую ссылку
                        }
                    }

                    // Если устройство новое, замеряем его объём
                    if (!isset($detectedDevices[$devId])) {
                        $totalDisk = @disk_total_space($finalPath);
                        $freeDisk  = @disk_free_space($finalPath);

                        if ($totalDisk !== false && $freeDisk !== false) {
                            $detectedDevices[$devId] = [
                                'total' => $totalDisk,
                                'free'  => $freeDisk,
                            ];
                        }
                    }
                }
            }
        }

        // Суммируем показатели со всех обнаруженных уникальных дисков
        $total = 0;
        $free = 0;

        foreach ($detectedDevices as $device) {
            $total += $device['total'];
            $free  += $device['free'];
        }

        // Если не удалось прочитать ни один диск (крайне редкий случай), ставим заглушку 1 байт во избежание division by zero
        $total = $total > 0 ? $total : 1;

        //$total = disk_total_space(Storage::path(''));
        //$free  = disk_free_space (Storage::path(''));
        $appUsed = Image::sum('size');
        return new SpaceInfo($total, $free, $appUsed);
    }
}
