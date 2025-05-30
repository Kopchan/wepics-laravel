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
        $bytesUnits = [' B', ' KB', ' MB', ' GB', ' TB', ' PB'];
        $suffixIndex = 0;
        $count = floatval($bytes);

        while ($count >= 1000 && $suffixIndex < count($bytesUnits) - 1) {
            $count /= 1024;
            $suffixIndex++;
        }

        if ($count >= 100 || $suffixIndex === 0) {
            $formatted = number_format($count, 0, '.', '');
        } elseif ($count >= 10) {
            $formatted = number_format($count, 1, '.', '');
        } else {
            $formatted = number_format($count, 2, '.', '');
        }

        return $formatted . $bytesUnits[$suffixIndex];
    }
}

if (!function_exists('durationToHuman')) {
    function durationToHuman(float $seconds): string{
        $totalSeconds = floatval($seconds);
        $ms = round(fmod($totalSeconds, 1) * 1000);
        $absSeconds = floor(abs($totalSeconds));

        $days = floor($absSeconds / 86400);
        $hours = floor(($absSeconds % 86400) / 3600);
        $minutes = floor(($absSeconds % 3600) / 60);
        $secs = $absSeconds % 60;

        // Форматируем части с ведущими нулями (кроме дней)
        $formatPart = function ($value, $suffix) {
            if ($value <= 0 && $suffix !== 'ms') return '';
            $paddedValue = $suffix === 'd' ? $value : str_pad($value, 2, '0', STR_PAD_LEFT);
            return $paddedValue . $suffix;
        };

        // Собираем все значимые части (без нулевых)
        $parts = array_filter([
            $formatPart($days, 'd'),
            $formatPart($hours, 'h'),
            $formatPart($minutes, 'm'),
            $formatPart($secs, 's'),
        ]);

        // Добавляем миллисекунды, если время < 1 минуты и они есть
        if ($ms > 0 && $minutes < 1) {
            $parts[] = str_pad($ms, 3, '0', STR_PAD_LEFT) . 'ms';
        }

        // Берём только две самые значимые части
        $significantParts = array_slice($parts, 0, 2);

        // Определяем разделитель
        $separator = (isset($significantParts[1]) && str_ends_with($significantParts[1], 'ms')) ? '.' : ':';

        return !empty($significantParts) ? implode($separator, $significantParts) : '0s';
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
