<?php

namespace Dfumagalli\Getresponse;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class GetResponseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerGetResponseService();
    }

    /**
     * Register the service provider in the container
     *
     * @return void
     */
    public function registerGetResponseService(): void
    {
        $this->app->singleton('getresponse', function ($app) {
            return GetResponse::fromConfig();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        AboutCommand::add('GetResponse', fn () => ['Version' => '0.2.0']);
        $this->publishResources();
    }

    /**
     * Register currency resources.
     *
     * @return void
     */
    public function publishResources(): void
    {
        if ($this->isLumen() === false) {
            $this->publishes([
                __DIR__ . '/../config/getresponse.php' => config_path('getresponse.php'),
            ], 'config');
        }
    }

    /**
     * Check if package is running under Lumen app
     *
     * @return bool
     */
    protected function isLumen(): bool
    {
        return str_contains($this->app->version(), 'Lumen') === true;
    }
}
