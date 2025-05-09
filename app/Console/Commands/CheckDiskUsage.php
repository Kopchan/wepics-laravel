<?php

namespace App\Console\Commands;

use App\Cacheables\SpaceInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckDiskUsage extends Command
{
    protected $signature = 'disk:check';

    protected $description = 'Check disk usage';

    public function handle()
    {
        $space = SpaceInfo::get();
        $total = bytesToHuman($space->total);
        $free  = bytesToHuman($space->free);
        $usedPercent = $space->usedPercent;
        $message = "Checking disk usage: remaining $free out of $total ($usedPercent%)";
        Log::info($message);
        $this->info($message);
    }
}
