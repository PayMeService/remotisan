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
        $this->registerPublishers();
        $this->registerConfigs();
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->registerViews();
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
     * Setting the publishers available for tags in artisan vendor:publish.
     * @return void
     */
    protected function registerPublishers(): void
    {
        $this->publishes([
            __DIR__.'/../config/remotisan.php' => config_path('remotisan.php'),
        ], 'remotisan-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/remotisan'),
        ], 'remotisan-views');
    }

    /**
     * Setting package dedicated configs
     * @return void
     */
    protected function registerConfigs(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/remotisan.php',
            'remotisan'
        );
    }

    /**
     * Sets the package related views.
     * @return void
     */
    protected function registerViews(): void
    {
        if(view()->exists('vendor.remotisan.index')) {
            $pathToViews = resource_path('views/vendor');
        } else {
            $pathToViews = __DIR__ . '/../resources/views';
        }

        $this->loadViewsFrom($pathToViews, 'remotisan');
    }
}
