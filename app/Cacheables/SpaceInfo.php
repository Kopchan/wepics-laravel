<?php

namespace App\Cacheables;

use DateTime;
use Illuminate\Support\Facades\Storage;

class SpaceInfo extends CacheableBase
{
    public const TTL = (60 * 10); // На 10 минут в кеш

    public readonly int $total;
    public readonly int $free;
    public readonly int $used;
    public readonly int $usedPercent;
    public readonly DateTime $gotAt;

    public function __construct($total, $free)
    {
        $this->total = $total;
        $this->free = $free;
        $this->used = $used = $total - $free;
        $this->usedPercent = (int) round($used / $total * 100);
        $this->isUploadDisabled = $this->usedPercent >= config('setups.upload_disable_percentage');
        $this->gotAt = now();
    }

    public static function function(?array $args): SpaceInfo
    {
        $total = disk_total_space(Storage::path(''));
        $free  = disk_free_space (Storage::path(''));
        return new SpaceInfo($total, $free);
    }
}
