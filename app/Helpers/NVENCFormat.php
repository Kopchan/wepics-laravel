<?php

namespace App\Helpers;

use FFMpeg\Format\Video\DefaultVideo;


class NVENCFormat extends DefaultVideo
{
    public function getAvailableAudioCodecs(): array
    {
        return ['aac'];
    }

    public function getAvailableVideoCodecs(): array
    {
        return ['h264_nvenc'];
    }

    public function getAudioCodec(): string
    {
        return 'aac';
    }

    public function getVideoCodec(): string
    {
        return 'h264_nvenc';
    }

    public function supportBFrames(): bool
    {
        return true;
    }

    public function getExtraParams(): array
    {
        return [];
    }
}
