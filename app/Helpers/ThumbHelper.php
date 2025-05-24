<?php

namespace App\Helpers;

class ThumbHelper
{
    public static function calcDimensions($originalWidth, $originalHeight, $dimension, $targetSize) {
        switch ($dimension) {
            case 'h': // Масштабирование по высоте
                $width = (int) round(($targetSize / $originalHeight) * $originalWidth);
                $width = $width % 2 === 0 ? $width : $width - 1; // Обеспечиваем чётность
                $height = $targetSize;
                break;

            case 'w': // Масштабирование по ширине
                $height = (int) round(($targetSize / $originalWidth) * $originalHeight);
                $height = $height % 2 === 0 ? $height : $height - 1; // Обеспечиваем чётность
                $width = $targetSize;
                break;

            default: // Вписать в квадрат (масштабирование с обрезкой)
                $width = $height = $targetSize % 2 === 0 ? $targetSize : $targetSize - 1;
                break;
        }
        return [$width, $height];
    }
}
