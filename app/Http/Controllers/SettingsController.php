<?php

namespace App\Http\Controllers;

use App\Cacheables\SpaceInfo;
use App\Exceptions\ApiException;
use App\Http\Requests\SettingsEditRequest;
use App\Models\AgeRating;
use App\Models\Reaction;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    // Получение публичных/общих предустановок
    public function public()
    {
        $settings = Cache::remember('public_settings', (7 * 24 * 60 * 60), fn () => [
            'allowed_upload_mimes'  => config('setups.allowed_upload_mimes'),
            'allowed_preview_sizes' => config('setups.allowed_preview_sizes'),
            'reactions'   => Reaction ::all(),
            'age_ratings' => AgeRating::all(),
        ]);

        $spaceInfo = SpaceInfo::getCached();
        return response()->json([
            'setups' => [
                ...$settings,
                'is_upload_disabled' => $spaceInfo->isUploadDisabled,
            ],
        ]);
    }

    public function index()
    {
        $settings = config('settings');
        $space = SpaceInfo::getCached();

        return response()->json([
            'setups' => $settings,
            'space' => $space
        ]);
    }

    public function update(SettingsEditRequest $request)
    {
        Cache::forget('public_settings');

        $key   = $request->key;
        $value = $request->value;

        try {
            envWrite($key, $value);
        }
        catch (\Exception) {
            throw new ApiException(500, 'Unable to update .env file');
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
