<?php

use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ReactionController;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route
::controller(SettingsController::class)
->group(function ($settings) {
    $settings->get('',       ['public'])->middleware('cache.headers:public;max_age=2628000;etag'); // Публичные предустановки
    $settings->get('setups', 'public')->middleware('cache.headers:public;max_age=2628000;etag'); // Публичные предустановки 2
});
Route::any('who', fn () => [
    'service' => 'Wepics',
    'appname' => config('app.name'),
]);
Route
::controller(UserController::class)
->prefix('users')
->group(function ($users) {
    $users->post ('login' , 'login');
    $users->post ('reg'   , 'reg'  );
    $users->middleware('token.auth')->group(function ($authorized) {
        $authorized->get  ('me',     'showSelf');
        $authorized->patch('',       'editSelf');
        $authorized->post ('logout', 'logout'  );
    });
    $users->middleware('token.auth:admin')->group(function ($usersManage) {
        $usersManage->post('', 'create' );
        $usersManage->get ('', 'showAll');
        $usersManage->prefix('{id}')->group(function ($userManage) {
            $userManage->get   ('', 'show'  )->where('id', '[0-9]+');
            $userManage->patch ('', 'edit'  )->where('id', '[0-9]+');
            $userManage->delete('', 'delete')->where('id', '[0-9]+');
        });
    });
});
Route
::middleware('token.auth:guest')
->controller(AlbumController::class)
->prefix('albums/{album_hash}')
->group(function ($album) {
    $album->get('', 'getLegacy');
    $album->get('info', 'get');
    $album->get('og.png', 'ogImage')->name('get.album.ogLegacy');
    $album->get('og', 'ogImage')->name('get.album.og');
    $album->get('ogView', 'ogView');
    $album->middleware('token.auth:admin')->group(function ($albumManage) {
        $albumManage->get   ('reindex', 'reindex');
        $albumManage->post  ('', 'create');
        $albumManage->patch ('', 'update');
        $albumManage->delete('', 'delete');
    });
    $album
    ->controller(AccessController::class)
    ->middleware('token.auth:admin')
    ->prefix('access')
    ->group(function ($albumRights) {
        $albumRights->get   ('', 'showAll');
        $albumRights->post  ('', 'create' );
        $albumRights->delete('', 'delete');
    });
    $album
    ->controller(ImageController::class)
    ->prefix('images')
    ->group(function ($albumMedias) {
        $albumMedias->get('', 'showAll')->withoutMiddleware('throttle:api');
        $albumMedias->middleware('token.auth:admin')->post('', 'upload');
        $albumMedias->prefix('{image_hash}')->group(function ($media) {
            $media->middleware('token.auth:admin')->delete('', 'delete');
            $media->middleware('token.auth:admin')->patch ('', 'rename');
            $media->get('',         'info');
            $media->get('orig',     'orig')
                ->withoutMiddleware('throttle:api')
                ->name('get.image.orig');
            $media->any('download', 'download');
            $media->get('thumb/{orient}{px}{ani?}', 'thumb')
                ->where('orient', '[whWH]')
                ->where('px'    , '[0-9]+')
                ->where('ani'   , '[a]')
                ->withoutMiddleware('throttle:api')
                ->name('get.image.thumb');
            $media
            ->controller(TagController::class)
            ->middleware('token.auth:admin')
            ->prefix('tags')
            ->group(function ($mediaTags) {
                $mediaTags->post  ('', 'set');
                $mediaTags->delete('', 'unset');
            });
            $media
            ->controller(ReactionController::class)
            ->middleware('token.auth:user')
            ->prefix('reactions')
            ->group(function ($mediaReactions) {
                $mediaReactions->post  ('', 'set');
                $mediaReactions->delete('', 'unset');
            });
        });
    });
});
Route
::middleware('token.auth:guest')
->controller(TagController::class)
->prefix('tags')
->group(function ($tags) {
    $tags->get('', 'showAllOrSearch');
    $tags->middleware('token.auth:admin')->group(function ($tagsManage) {
        $tagsManage->post  ('', 'create');
        $tagsManage->patch ('', 'rename');
        $tagsManage->delete('', 'delete');
    });
});
Route
::middleware('token.auth:guest')
->controller(ReactionController::class)
->prefix('reactions')
->group(function ($reactions) {
    $reactions->get('', 'showAll');
    $reactions->middleware('token.auth:admin')->group(function ($reactionsManage) {
        $reactionsManage->post  ('', 'add');
        $reactionsManage->delete('', 'remove');
    });
});
