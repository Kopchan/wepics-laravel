<?php

namespace App\Helpers;

use App\Models\Image;
use FFMpeg\Filters\Video\VideoFilters;
use FFMpeg\Format\Video\X264;
use FFMpeg\Media\AbstractVideo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class StreamHelper
{
    /**
     * Главный метод: сохраняет обложку или кадр в Storage::path("thumbs/frames/{hash}")
     */
    public static function extractPreview($videoPath, $video): ?string
    {
        $outputDir = Storage::path("thumbs/frames");
        try {
            @mkdir($outputDir, 0775, true);
        }
        catch (\Exception $e) {
            Log::error("extractPreview {$e->getMessage()}");
        }

        $ffprobe = FFProbe::create();
        $streams = $ffprobe->streams($videoPath);

        $coverStream = null;

        foreach ($streams as $stream) {
            if ($stream->get('codec_type') === 'video' &&
                ($stream->get('disposition')['attached_pic'] ?? false)) {
                $coverStream = $stream;
                break;
            }
        }
        if ($coverStream)
            return self::extractAttachedCover($videoPath, $outputDir, $coverStream, $video->hash);
        else
            return self::extractVideoFrame($videoPath, $outputDir, $video->duration_ms, $video->hash);
    }

    /**
     * Извлекает встроенную обложку с оригинальным расширением
     */
    public static function extractAttachedCover(string $videoPath, string $outputDir, $stream, string $hash): string
    {
        $codec = $stream->get('codec_name');
        $extension = match ($codec) {
            'mjpeg' => 'jpg',
            'png'   => 'png',
            default => 'bin',
        };


        //$outputFile = "{$outputDir}/{$hash}.{$extension}";
        $outputFile = "{$outputDir}/{$hash}.png";

//          ->addFilter('-c copy')

        FFMpeg::fromDisk('local')
            ->open(self::relativePath($videoPath))
            ->addFilter([
                '-map', '0:v',
                '-map', '-0:V',
            ])
            ->export()
            ->save(self::relativePath($outputFile));
/*
        FFMpeg::fromDisk('local')
            ->open(self::relativePath($videoPath))
            ->addFilter(['-map', 'disp:attached_pic'])
            ->export()
            ->save(self::relativePath($outputFile));

        $streamIndex = $stream->get('index');
        FFMpeg::fromDisk('local')
            ->open(self::relativePath($videoPath))
            ->addFilter(['-map', "0:$streamIndex"])
            ->export()
            ->save(self::relativePath($outputFile));
*/

        return $outputFile;
    }

    /**
     * Извлекает кадр с 10-й секунды (если длительность ≥ 20 сек), иначе — первый кадр. PNG.
     */
    public static function extractVideoFrame(string $videoPath, string $outputDir, int $durationMs, string $hash): string
    {
        $seconds = ($durationMs / 1000) >= 20 ? 10 : 0;
        $outputFile = "{$outputDir}/{$hash}.png";

        FFMpeg::fromDisk('local')
            ->open(self::relativePath($videoPath))
            ->getFrameFromSeconds($seconds)
            ->export()
            ->save(self::relativePath($outputFile));

        return $outputFile;
    }

    /**
     * Формула оптимального битрейта
     */
    public static function calcKiloBitrate($width, $height, $framerate, $coef = 0.1): int {
        return $width * $height * $framerate * $coef / 1000;
    }

    /**
     * Создание из чего-то в видео для превью (без аудио, уменьшение до конкретного размера, битрейта и фреймрейта)
     */
    public static function genPreviewVideo(
        Image  $media,
        string $mediaPath,
        string $outputPath,
        string $dimension    = 'q',
        int    $size         = 240,
        float  $duration     = 60,
        float  $maxFramerate = 60,
        float  $ecoFramerate = 30,
        float  $bitrateCoef  = 0.1,
        float  $maxBitrate   = 8000,
        float  $minBitrate   = 300,
    ): string {
        // Путь до временного файла
        $tempFile = "temp/thumb_{$media->hash}_{$dimension}{$size}a.mp4";

        // Размеры превью
        [$width, $height] = ThumbHelper::calcDimensions($media->width, $media->height, $dimension, $size);

        // Фильтры масштабирования
        $filter = match ($dimension) {
            'q' => implode(',', [
                "scale='if(gt(a,1),{$size},-2)':'if(gt(a,1),-2,{$size})'",
                "crop='trunc(in_w/2)*2':'trunc(in_h/2)*2'"
            ]),
            default => "scale=$width:$height",
        };

        // Кадров в секунду оригинала и требуемое
        $origFramerate = $media?->avg_frame_rate_den
            ? $media->avg_frame_rate_num / $media->avg_frame_rate_den
            : $maxFramerate;

        $targetFramerate = min($origFramerate, $maxFramerate);

        // Параметры кодировщика
        $params = [
            '-an',                   // Убрать аудио
            '-t', $duration,        // Длительность
            '-pix_fmt', 'yuv420p', // Кодировка цвета и пикселей
        ];
        if ($origFramerate > $maxFramerate) {
            $params[] = '-r';   // Урезание кадров в секунду
            $params[] = $maxFramerate;
        }

        // Скорость потока данных
        $targetBitrate = self::calcKiloBitrate($width, $height, $targetFramerate, $bitrateCoef);
        if ($targetBitrate > $maxBitrate)
            $targetBitrate = self::calcKiloBitrate($width, $height, $ecoFramerate, $bitrateCoef);

        if ($targetBitrate < $minBitrate)
            $targetBitrate = $minBitrate;
        else
        if ($targetBitrate > $maxBitrate)
            $targetBitrate = $maxBitrate;

        if ($media?->frame_count && $media->frame_count < 30)
            $targetBitrate = $maxBitrate;

        //dd($targetBitrate, $minBitrate, $maxBitrate);

        // Полный быстрый NVIDIA кодировщик
        $format = new NVENCFormat();
        $format
            ->setKiloBitrate($targetBitrate)
            ->setInitialParameters([
                '-hwaccel', 'cuda',
            ])
            ->setAdditionalParameters($params);

        // Преобразование
        try {
            FFMpeg::fromDisk('local')
                ->open(self::relativePath($mediaPath))
                ->addFilter(fn($filters) => $filters->custom($filter))
                ->export()
                ->inFormat($format)
                ->save($tempFile);
        }
        catch (\Exception $e) {
            Log::error('NVENC: '. $e->getMessage());

            // Резервный кодировщик
            $formatSecond = new X264();
            $formatSecond
                ->setKiloBitrate($targetBitrate)
                ->setAdditionalParameters($params);

            // Повторное преобразование
            FFMpeg::fromDisk('local')
                ->open(self::relativePath($mediaPath))
                ->addFilter(fn($filters) => $filters->custom($filter))
                ->export()
                ->inFormat($formatSecond)
                ->save($tempFile);
        }

        // Больше не временный файл, перенос в папку с превью
        Storage::move($tempFile, $outputPath);

        return $outputPath;
    }

    /**
     * Преобразует абсолютный путь в относительный для диска `local`
     */
    private static function relativePath(string $absolute): string
    {
        return str_replace(Storage::path(''), '', $absolute);
    }
}
