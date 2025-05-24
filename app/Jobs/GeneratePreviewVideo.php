<?php

namespace App\Jobs;

use App\Helpers\StreamHelper;
use App\Models\Image;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Redis;

class GeneratePreviewVideo implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Image $media;
    public string $mediaPath;
    public string $outputPath;
    public string $dimension;
    public int $size;

    public function uniqueId(): string
    {
        return md5($this->media->hash . $this->size);
    }

    public function __construct(Image $media, string $mediaPath, string $outputPath, string $dimension = 'q', int $size = 240)
    {
        $this->media = $media;
        $this->mediaPath = $mediaPath;
        $this->outputPath = $outputPath;
        $this->dimension = $dimension;
        $this->size = $size;
    }

    public function handle(): void
    {
//        $publishKey = "job.GeneratePreviewVideo.{$this->media->hash}{$this->dimension}{$this->size}";
//        try {
            StreamHelper::genPreviewVideo($this->media, $this->mediaPath, $this->outputPath, $this->dimension, $this->size);
//        }
//        catch (\Exception $exception) {
//            Redis::publish($publishKey, json_encode([
//                'status' => 'errored',
//                'error' => $exception,
//            ]));
//            throw $exception;
//        }
//        finally {
//            Redis::publish($publishKey, json_encode([
//                'status' => 'completed',
//            ]));
//        }
    }
}
