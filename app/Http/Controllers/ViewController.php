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
            ->withSum('images as size', 'size')
            ->withSum('images as duration', 'duration_ms')
            ->withCount([
                'images as medias_count',
                'images as images_count' => fn($q) => $q->where  ('type',  'image'),
                'images as videos_count' => fn($q) => $q->whereIn('type', ['video', 'imageAnimated']),
                'images as audios_count' => fn($q) => $q->where  ('type',  'audio'),
                'childAlbums as albums_count',
            ])
            ->first();
        if (!$album || $album->getAccessLevelCached() === AccessLevel::None)
            return view('index');

        return view('index', compact('album'));
    }

    public function image($albumHashOrAlias, $type, $imageHash)
    {
        $album = Album
            ::where ('alias', $albumHashOrAlias)
            ->orWhere('hash', $albumHashOrAlias)
            ->withCount([
                'images as images_count' => fn($q) => $q->where  ('type',  'image'),
                'images as videos_count' => fn($q) => $q->whereIn('type', ['video', 'imageAnimated']),
            ])
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
        $minDirection = min($image->width, $image->height);
        if ($minDirection <= 1080) {
            $image->widthThumb  = $image->width;
            $image->heightThumb = $image->height;
        }
        else {
            $scale = 1080 / $minDirection;
            $image->widthThumb  = (int) round($image->width  * $scale);
            $image->heightThumb = (int) round($image->height * $scale);
        }

        $image->urlOrigRoute  = route('get.image.orig' , [$album->hash, $image->hash]);
        $image->urlThumbRoute = route('get.image.thumb', [$album->hash, $image->hash, $image->orient, 1080]);

        return view('index', compact('album', 'image'));
    }

    public function imageNested($albumHashOrAlias, $trueAlbumHashOrAlias, $type, $imageHash)
    {
        $album = Album
            ::where ('alias', $albumHashOrAlias)
            ->orWhere('hash', $albumHashOrAlias)
            ->withCount([
                'childAlbums as albums_count',
            ])
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
        }
        else {
            $scale = 1080 / $minDirection;
            $image->widthThumb  = (int) round($image->width  * $scale);
            $image->heightThumb = (int) round($image->height * $scale);
        }

        $image->urlOrigRoute  = route('get.image.orig' , [$trueAlbum->hash, $image->hash]);
        $image->urlThumbRoute = route('get.image.thumb', [$trueAlbum->hash, $image->hash, $image->orient, 1080]);

        return view('index', compact('album', 'image'));
    }
}
