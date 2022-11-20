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
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/remotisan.php' => config_path('remotisan.php'),
        ], 'remotisan-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/remotisan'),
        ], 'remotisan-views');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/remotisan.php',
            'remotisan'
        );

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->setViews();
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Remotisan::class, function ($app) {
            $remotisan = new Remotisan(app()->make(CommandsRepository::class));

            return $remotisan;
        });
    }

    /**
     * @return void
     */
    protected function setViews(): void
    {
        if(view()->exists('vendor.remotisan.index')) {
            $pathToViews = resource_path('views/vendor');
        } else {
            $pathToViews = __DIR__ . '/../resources/views';
        }

        $this->loadViewsFrom($pathToViews, 'remotisan');
    }
}
