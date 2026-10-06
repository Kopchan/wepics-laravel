<?php

namespace App\Http\Controllers;

use App\Cacheables\SpaceInfo;
use App\Exceptions\ApiException;
use App\Http\Requests\SettingsEditRequest;
use App\Models\AgeRating;
use App\Models\Reaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /** Объект общей информации */
    static private function getServiceInfo() { return [
        'service' => 'Wepics',
        'appname' => config('app.name'),
    ]; }

    /** Объект общих предустановок с сохранением в кеш */
    static private function getCacheSettings() {
        return Cache::remember('public_settings', (7 * 24 * 60 * 60), fn () => [
            'default_quota_bytes'       => config('setups.default_quota_bytes'),
            'allowed_preview_sizes'     => config('setups.allowed_preview_sizes'),
            'allowed_image_extensions'  => config('setups.allowed_image_extensions'),
            'allowed_video_extensions'  => config('setups.allowed_video_extensions'),
            'allowed_audio_extensions'  => config('setups.allowed_audio_extensions'),
            'age_ratings' => AgeRating::all()->makeHidden(['created_at', 'updated_at']),
            'reactions'   => Reaction ::all()->makeHidden(['created_at', 'updated_at']),
        ]);
    }

    /** Получение публичных/общих предустановок, информации о сервисе */
    public function index(Request $request)
    {
        $spaceInfo = SpaceInfo::getCached();
        $response = [
            'about' => static::getServiceInfo(),
            'setups' => [
                'is_upload_disabled' => $spaceInfo->isUploadDisabled,
                ...static::getCacheSettings(),
            ]
        ];
        //if ($request->user()?->is_admin)
            $response['space'] = $spaceInfo;

        return response()->json($response);
    }

    /** Получение информации о сервисе */
    public function who()
    {
        return response(static::getServiceInfo());
    }

    /** Получение публичных/общих предустановок */
    // Получение публичных/общих предустановок
    public function setups()
    {
        $spaceInfo = SpaceInfo::getCached();
        return response([
            'setups' => [
                'is_upload_disabled' => $spaceInfo->isUploadDisabled,
                ...static::getCacheSettings(),
            ]
        ]);
    }

    /** Получение информации о хранилище сервера */
    public function space()
    {
        return response(['space' => SpaceInfo::function(null)]);
    }


    /** Обновление env ключ-значения */
    public function update(SettingsEditRequest $request)
    {
        Cache::forget('public_settings');

        $key   = $request->key;
        $value = $request->value;

        try {
            throw new ApiException(500, 'comment this');
            //envWrite($key, $value);
        }
        catch (\Exception) {
            throw new ApiException(500, 'Unable to update .env file');
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    /** Отобразить диски и их место */
    public function printSpaceDevices()
    {
        // 1. Указываем корневые папки для сканирования
        $rootFolders = [
            storage_path('app/images'),
            storage_path('app/users'),
        ];

        $detectedDevices = [];

        foreach ($rootFolders as $rootPath) {
            if (!is_dir($rootPath)) continue;

            $checkPaths = [$rootPath];

            // Используем встроенный итератор
            $flags = \FilesystemIterator::SKIP_DOTS;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath, $flags),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                // Добавляем в массив для проверки только папки и ссылки
                if ($item->isLink() || $item->isDir()) {
                    $checkPaths[] = $item->getPathname();
                }
            }

            // 2. Опрашиваем собранные пути
            foreach ($checkPaths as $path) {
                // Важно: Нормализуем слэши для Windows/Linux систем
                $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

                // Используем lstat(), чтобы скрипт НЕ падал, если ссылка "битая"
                $lstat = @lstat($path);
                if (!$lstat) {
                    continue; // Если не удалось прочитать даже саму ссылку, пропускаем
                }

                // Берем ID устройства. Если это симлинк, PHP берет ID диска, где лежит сама ссылка.
                // Чтобы узнать ID диска назначения, нам нужно проверить реальный путь, если он существует.
                $devId = $lstat['dev'];
                $finalPath = $path;

                if (is_link($path)) {
                    $realTarget = @readlink($path);
                    // Если ссылка жива и цель существует
                    if ($realTarget && file_exists($path)) {
                        $finalPath = realpath($path);
                        $statTarget = @stat($finalPath);
                        if ($statTarget) {
                            $devId = $statTarget['dev']; // Переключаемся на ID целевого диска
                        }
                    } else {
                        // Ссылка битая (целевой файл удален) — пропускаем её,
                        // так как замерить место на несуществующем диске невозможно
                        continue;
                    }
                }

                // Если этот физический диск мы уже проверяли — пропускаем
                if (isset($detectedDevices[$devId])) {
                    continue;
                }

                // Замеряем место на диске (подавляем ошибки через @ на случай проблем с правами)
                $totalBytes = @disk_total_space($finalPath);
                $freeBytes = @disk_free_space($finalPath);

                if ($totalBytes !== false && $freeBytes !== false) {
                    $systemUsedBytes = $totalBytes - $freeBytes;

                    $detectedDevices[$devId] = [
                        'found_at_path'  => $path,
                        'real_mount'     => $finalPath,
                        'total_gb'       => round($totalBytes / (1024 ** 3), 2),
                        'system_used_gb' => round($systemUsedBytes / (1024 ** 3), 2),
                        'free_gb'        => round($freeBytes / (1024 ** 3), 2),
                    ];
                }
            }
        }

        // 3. Вывод результатов
        foreach ($detectedDevices as $devId => $info) {
            echo "<h3>Диск ID: {$devId}</h3>";
            echo "<p>Путь в проекте: {$info['found_at_path']}</p>";
            echo "<p>Реальный диск: {$info['real_mount']}</p>";
            echo "<p>Общий размер: {$info['total_gb']} GB | Занято: {$info['system_used_gb']} GB | Свободно: {$info['free_gb']} GB</p>";
            echo "<p>---------------------------------------</p>";
        }
    }
}
