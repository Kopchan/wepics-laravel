<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

if (!function_exists('dropColumnIfExists')) {
    function dropColumnIfExists($table, $column): void
    {
        if (Schema::hasColumn($table, $column))
            Schema::table($table, fn(Blueprint $table) => $table->dropColumn($column));
    }
}

if (!function_exists('bytesToHuman')) {
    function bytesToHuman(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

        $bytes = max($bytes, 0);

        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        $formatted = number_format($bytes, 3 - strlen(floor($bytes)), '.', '');

        return $formatted . ' ' . $units[$pow];
    }
}

if (!function_exists('envWrite')) {
    function envWrite(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath))
            throw new Exception('Unable to update .env file', 500);

        $envContent = File::get($envPath);

        $key = strtoupper($key);
        $pattern = "/^{$key}=(.*)$/m";

        if (preg_match($pattern, $envContent))
            $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);

        else
            $envContent .= "\n{$key}={$value}";

        File::put($envPath, $envContent);
        Artisan::call('config:clear');
        Artisan::call('config:cache');
    }
}
