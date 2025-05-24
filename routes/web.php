<?php

use App\Http\Controllers\ViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::controller(ViewController::class)->group(function ($view) {
    $view->view('',        'index')->name('home');
    $view->get ('{album}', 'album')->name('album');
    $view->get ('{album}/{type}/{image}',             'image')      ->where('type', 'i|a|v');
    $view->get ('{album}/{trueAlbum}/{type}/{image}', 'imageNested')->where('type', 'i|a|v');
    $view->view('{any?}', 'index')->where('any', '.*')->name('any');
});

