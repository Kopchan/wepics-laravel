<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PerfController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ReactionController;

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
    $settings->get('',       'index' )->middleware('cache.headers:private;max_age=2628000;etag')->name('infoapi'); // Автоматическая информация об сервере
    $settings->get('who',    'who'   )->middleware('cache.headers:public;max_age=2628000;etag' )->name('who');     // Публичная информация об сервисе
    $settings->get('setups', 'setups')->middleware('cache.headers:public;max_age=2628000;etag' )->name('setups');  // Публичные предустановки
    $settings->middleware('token.auth:admin')->group(function ($administration) {
        $administration->get('space', 'space')->name('space');                                                     // Информация об серверном хранилище
        $administration->get('spacePrint', 'printSpaceDevices')->name('spacePrint');
        $administration->get('spacetest', function () { dd(\App\Cacheables\SpaceInfo::function(null)); })->name('spacetest');
    });
});
Route
::controller(PerfController::class)
->middleware('token.auth:admin')
->group(function ($perf) {
    $perf->get   ('perf', 'summary')->name('perf.summary');
    $perf->delete('perf',   'clear')->name('perf.clear');
});
Route
::controller(UserController::class)
->prefix('users')
->group(function ($users) {
    $users->post('login' , 'login')->name('login');
    $users->post('reg'   , 'reg'  )->name('signup');
    $users->middleware('token.auth')->group(function ($authorized) {
        $authorized->get  ('me',     'showSelf')->name('me');
        $authorized->patch('',       'editSelf')->name('editSelf');
        $authorized->post ('logout', 'logout'  )->name('logout');
    });
    $users->middleware('token.auth:admin')->group(function ($usersManage) {
        $usersManage->post('', 'create' )->name('user.create');
        $usersManage->get ('', 'showAll')->name('users');
        $usersManage->prefix('{id}')->group(function ($userManage) {
            $userManage->get   ('', 'show'  )->where('id', '[0-9]+')->name('user');
            $userManage->patch ('', 'edit'  )->where('id', '[0-9]+')->name('user.update');
            $userManage->delete('', 'delete')->where('id', '[0-9]+')->name('user.delete');
        });
    });
});
Route::get('albums', [AlbumController::class, 'ownAndAccessible']) // TODO
    ->middleware('token.auth:user')
    ->name('albums.ownAndAccessible');
Route
::middleware('token.auth:guest')
->controller(AlbumController::class)
->prefix('albums/{album_hash}')
->group(function ($album) {
    $album->get('',       'getLegacy')->name('album.legacy');
    $album->get('info',   'get'      )->name('album');
    $album->get('og.png', 'ogImage'  )->name('album.ogLegacy');
    $album->get('og',     'ogImage'  )->name('album.og');
    $album->get('ogView', 'ogView'   )->name('album.ogView');
    $album->post('invite',
        [InvitationController::class, 'store']) // Генерировать код приглашения на СВОЙ альбом
        ->middleware('token.auth:owner')
        ->name('album.invite');
    $album->middleware('token.auth:owner')->group(function ($albumManage) {
        $albumManage->get   ('reindex', 'reindex')->name('album.reindex');
        $albumManage->post  ('',         'create')->name('album.create');
        $albumManage->patch ('',         'update')->name('album.update');
        $albumManage->delete('',         'delete')->name('album.delete');
    });
    $album
    ->controller(AccessController::class)
    ->middleware('token.auth:owner')
    ->prefix('access')
    ->group(function ($albumRights) {
        $albumRights->get   ('', 'showAll')->name('album.accesses');
        $albumRights->post  ('', 'create' )->name('album.accesses.create');
    });
    $album->delete('access/{?user_id}', [AccessController::class, 'delete'])->name('album.accesses.delete');
    $album
    ->controller(ImageController::class)
    ->prefix('images')
    ->group(function ($albumMedias) {
        $albumMedias->get('', 'showAll')->withoutMiddleware('throttle:api')->name('album.images');
        $albumMedias->middleware('token.auth:owner')->post('', 'upload')   ->name('album.images.upload');
        $albumMedias->prefix('{image_hash}')->group(function ($media) {
            $media->middleware('token.auth:owner')->delete('', 'delete')->name('image.delete');
            $media->middleware('token.auth:owner')->patch ('', 'rename')->name('image.rename');
            $media->get('',         'info')->name('image.info');
            $media->get('orig',     'orig')
                ->withoutMiddleware('throttle:api')
                ->name('image.orig');
            $media->any('download', 'download')->name('image.download');
            $media->get('thumb/{orient}{px}{ani?}', 'thumb')
                ->where('orient', '[whqWHQ]')
                ->where('px'    , '[0-9]+')
                ->where('ani'   , '[a]')
                ->withoutMiddleware('throttle:api')
                ->name('image.thumb');
            $media
            ->controller(TagController::class)
            ->middleware('token.auth:owner')
            ->prefix('tags')
            ->group(function ($mediaTags) {
                $mediaTags->post  ('',   'set')->name('image.tags.set');
                $mediaTags->delete('', 'unset')->name('image.tags.unset');
            });
            $media
            ->controller(ReactionController::class)
            ->middleware('token.auth:user')
            ->prefix('reactions')
            ->group(function ($mediaReactions) {
                $mediaReactions->post  ('',   'set')->name('image.reactions.set');
                $mediaReactions->delete('', 'unset')->name('image.reactions.unset');
            });
        });
    });
});
Route
::controller(InvitationController::class)
->prefix('invitation/{invite_code}')
->group(function ($invite) {                                       // [ПРИГЛАШЕНИЕ]
    $invite->get('album',   'album')->name('invitation.preview');  // Просмотр содержимого альбома по приглашению
    $invite->post('join', '   join')->name('invitation.join');     // Присоединиться к альбому (добавление доступа)
    $invite->delete(  '', 'destroy')->name('invitation.destroy');  // Удалить СВОЙ код приглашения
});
Route
::middleware('token.auth:guest')
->controller(TagController::class)
->prefix('tags')
->group(function ($tags) {
    $tags->get('', 'showAllOrSearch')->name('tags');
    $tags->middleware('token.auth:admin')->group(function ($tagsManage) {
        $tagsManage->post  ('', 'create')->name('tag.create');
        $tagsManage->patch ('', 'rename')->name('tag.rename');
        $tagsManage->delete('', 'delete')->name('tag.delete');
    });
});
Route
::middleware('token.auth:guest')
->controller(ReactionController::class)
->prefix('reactions')
->group(function ($reactions) {
    $reactions->get('', 'showAll')->name('reactions');
    $reactions->middleware('token.auth:admin')->group(function ($reactionsManage) {
        $reactionsManage->post  ('',    'add')->name('reaction.add');
        $reactionsManage->delete('', 'remove')->name('reaction.remove');
    });
});
