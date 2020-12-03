<?php

namespace App\Providers;

use App\Validators\RestValidator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        \Illuminate\Support\Facades\Validator::resolver(function($translator, $data, $rules, $messages)
        {
            return new RestValidator($translator, $data, $rules, $messages);
        });
    }
}
