<?php

namespace PayMe\Remotisan;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use PayMe\Remotisan\Console\Commands\CacheCommandsCommand;
use PayMe\Remotisan\Console\Commands\ProcessBrokerCommand;
use Throwable;

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

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'remotisan');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->ensureSignalsConstants();

        $this->commands([
            ProcessBrokerCommand::class,
            CacheCommandsCommand::class,
        ]);

        $this->logLastException();
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/remotisan.php',
            'remotisan'
        );

        $this->app->singleton(Remotisan::class, function ($app) {
            return new Remotisan(new CommandsRepository(), new ProcessExecutor());
        });
    }

    protected function ensureSignalsConstants(): void
    {
        $consts = [
            'SIG_IGN' => 1,
            'SIG_DFL' => 0,
            'SIG_ERR' => -1,
            'SIGHUP' => 1,
            'SIGINT' => 2,
            'SIGQUIT' => 3,
            'SIGILL' => 4,
            'SIGTRAP' => 5,
            'SIGABRT' => 6,
            'SIGIOT' => 6,
            'SIGBUS' => 7,
            'SIGFPE' => 8,
            'SIGKILL' => 9,
            'SIGUSR1' => 10,
            'SIGSEGV' => 11,
            'SIGUSR2' => 12,
            'SIGPIPE' => 13,
            'SIGALRM' => 14,
            'SIGTERM' => 15,
            'SIGSTKFLT' => 16,
            'SIGCLD' => 17,
            'SIGCHLD' => 17,
            'SIGCONT' => 18,
            'SIGSTOP' => 19,
            'SIGTSTP' => 20,
            'SIGTTIN' => 21,
            'SIGTTOU' => 22,
            'SIGURG' => 23,
            'SIGXCPU' => 24,
            'SIGXFSZ' => 25,
            'SIGVTALRM' => 26,
            'SIGPROF' => 27,
            'SIGWINCH' => 28,
            'SIGPOLL' => 29,
            'SIGIO' => 29,
            'SIGPWR' => 30,
            'SIGSYS' => 31,
            'SIGBABY' => 31,
        ];

        foreach ($consts as $const => $value) {
            if (!defined($const)) {
                define($const, $value);
            }
        }
    }

    /**
     * @return void
     * @throws BindingResolutionException
     */
    protected function logLastException(): void
    {
        $callback = fn(ExceptionHandler $handler) => $handler->reportable(fn(Throwable $e) => app()->instance('lastException', $e));
        $this->app->afterResolving(ExceptionHandler::class, $callback);

        if ($this->app->resolved(ExceptionHandler::class)) {
            $callback($this->app->make(ExceptionHandler::class));
        }
    }
}
