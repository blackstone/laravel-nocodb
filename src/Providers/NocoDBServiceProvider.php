<?php

namespace BlackstonePro\NocoDB\Providers;

use BlackstonePro\NocoDB\Connections\NocoConnection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class NocoDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/nocodb.php',
            'nocodb'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/nocodb.php' => config_path('nocodb.php'),
        ], 'nocodb-config');

        DB::extend('nocodb', function ($config, $name) {
            $config['name'] = $name;
            return new NocoConnection($config);
        });
    }
}
