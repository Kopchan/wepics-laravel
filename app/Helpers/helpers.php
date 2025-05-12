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

if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}



if (!function_exists('bytesToHuman')) {
    function bytesToHuman(int $bytes): string
    {
        //$units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $units = ['B', 'K', 'M', 'G', 'T', 'P'];

        $bytes = max($bytes, 0);

        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        $formatted = number_format($bytes, 3 - strlen(floor($bytes)), '.', '');

        return $formatted . $units[$pow];
    }
}

if (!function_exists('countToHuman')) {
    function countToHuman(int $count): string
    {
        $units = ['', 'K', 'M', 'B', 'T', 'Q'];

        if ($count <= 0) {
            return '0';
        }

        $pow = min(
            floor(log($count, 1000)),
            count($units) - 1
        );

        $value = $count / pow(1000, $pow);

        if (fmod($value, 1.0) === 0.0) {
            $formatted = number_format($value, 0, '.', '');
        } else {
            $precision = 3 - floor(log10($value) + 1);
            $precision = max(0, $precision);
            $formatted = number_format($value, $precision, '.', '');
        }

        return $formatted . $units[$pow];
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
