<?php

namespace App\Providers;

use App\Support\QueryPerf;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.trustXSendfile'))
            BinaryFileResponse::trustXSendfileTypeHeader();

        QueryPerf::registerListener();
    }
}
