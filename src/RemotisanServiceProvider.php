<?php

namespace PayMe\Remotisan;

use Illuminate\Support\ServiceProvider;

class RemotisanServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/remotisan.php' => config_path('remotisan.php'),
        ], 'remotisan-config');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/remotisan.php',
            'remotisan'
        );

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'remotisan');
    }

    public function register()
    {
        $this->app->singleton(Remotisan::class, function ($app) {
            $remotisan = new Remotisan(app()->make(CommandsRepository::class));

            return $remotisan;
        });
    }
}
