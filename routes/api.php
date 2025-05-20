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
    $settings->get('',       'public')->middleware('cache.headers:public;max_age=2628000;etag'); // Публичные предустановки
    $settings->get('setups', 'public')->middleware('cache.headers:public;max_age=2628000;etag'); // Публичные предустановки 2
});

Route::any('who', function () {
    return [
        'service' => 'Wepics',
        'appname' => config('app.name'),
    ];
});

Route
::controller(UserController::class)
->prefix('users')
->group(function ($users) {
    $users->post ('login' , 'login');
    $users->post ('reg'   , 'reg'  );
    $users->middleware('token.auth')->group(function ($authorized) {
        $authorized->get  ('logout', 'logout'  );
        $authorized->patch('',       'editSelf');
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
    $album->get('og.png', 'ogImage')->name('get.album.og');
    $album->get('ogView', 'ogView');
    $album->get('reindex', 'reindex');
    $album->middleware('token.auth:admin')->group(function ($albumManage) {
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
    ->group(function ($albumImages) {
        $albumImages->get('', 'showAll')->withoutMiddleware("throttle:api");
        $albumImages->middleware('token.auth:admin')->post('', 'upload');
        $albumImages->prefix('{image_hash}')->group(function ($image) {
            $image->middleware('token.auth:admin')->delete('', 'delete');
            $image->middleware('token.auth:admin')->patch ('', 'rename');
            $image->get('',         'info');
            $image->get('orig',     'orig');
            $image->any('download', 'download');
            $image->get('thumb/{orient}{px}', 'thumb')
                ->where('orient', '[whWH]')
                ->where('px'    , '[0-9]+')
                ->withoutMiddleware("throttle:api")
                ->name('get.image.thumb');
            $image
            ->controller(TagController::class)
            ->middleware('token.auth:admin')
            ->prefix('tags')
            ->group(function ($imageTags) {
                $imageTags->post  ('', 'set');
                $imageTags->delete('', 'unset');
            });
            $image
            ->controller(ReactionController::class)
            ->middleware('token.auth:user')
            ->prefix('reactions')
            ->group(function ($imageReactions) {
                $imageReactions->post  ('', 'set');
                $imageReactions->delete('', 'unset');
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
