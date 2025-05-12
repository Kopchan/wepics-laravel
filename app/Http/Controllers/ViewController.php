<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Models\Album;
use App\Models\Image;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class ViewController extends Controller
{
    public function index($any = null)
    {
        return view('index');
    }

    public function album($albumHashOrAlias = null)
    {
        $album = Album
            ::where ('alias', $albumHashOrAlias)
            ->orWhere('hash', $albumHashOrAlias)
            ->first();
        if (!$album || $album->getAccessLevelCached() === AccessLevel::None)
            return view('index');

        return view('index', compact('album'));
    }

    public function image($albumHashOrAlias, $imageHash)
    {
        $album = Album
            ::where ('alias', $albumHashOrAlias)
            ->orWhere('hash', $albumHashOrAlias)
            ->first();
        if (!$album || $album->getAccessLevelCached() === AccessLevel::None)
            return view('index');

        $image = Image
            ::where('hash', $imageHash)
            ->where('album_id', $album->id)
            ->first();
        if (!$image)
            return view('index', compact('album'));

        $image->orient = $image->width > $image->height ? 'h' : 'w';
        $scale = 1080 / min($image->width, $image->height);
        $image->widthThumb  = (int) round($image->width  * $scale);
        $image->heightThumb = (int) round($image->height * $scale);

        return view('index', compact('album', 'image'));
    }

    public function imageNested($albumHashOrAlias, $trueAlbumHashOrAlias, $imageHash)
    {
        $album = Album
            ::where ('alias', $albumHashOrAlias)
            ->orWhere('hash', $albumHashOrAlias)
            ->first();
        if (!$album || $album->getAccessLevelCached() === AccessLevel::None)
            return view('index');

        $trueAlbum = Album
            ::where ('alias', $trueAlbumHashOrAlias)
            ->orWhere('hash', $trueAlbumHashOrAlias)
            ->first();
        if (!$trueAlbum || $trueAlbum->getAccessLevelCached() === AccessLevel::None)
            return view('index', compact('album'));

        $image = Image
            ::where('hash', $imageHash)
            ->where('album_id', $trueAlbum->id)
            ->first();
        if (!$image)
            return view('index', compact('album'));

        $image->album = $trueAlbum;
        $image->orient = $image->width > $image->height ? 'h' : 'w';
        $minDirection = min($image->width, $image->height);
        if ($minDirection <= 1080) {
            $image->widthThumb  = $image->width;
            $image->heightThumb = $image->height;
            $image->urlPath = route('get.image.thumb', [$album->hash, $image->hash, $image->orient, 1080]);
        }
        else {
            $scale = 1080 / $minDirection;
            $image->widthThumb  = (int) round($image->width  * $scale);
            $image->heightThumb = (int) round($image->height * $scale);
        }

        return view('index', compact('album', 'image'));
    }
}
