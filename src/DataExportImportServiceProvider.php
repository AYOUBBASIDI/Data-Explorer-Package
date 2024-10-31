<?php

namespace Basidi\DataExplorer;

use Illuminate\Support\ServiceProvider;

class DataExportImportServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/dataexportimport.php', 'dataexportimport'
        );
    }

    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/Views', 'dataexportimport');

        // Publish config and views
        $this->publishes([
            __DIR__ . '/config/dataexportimport.php' => config_path('dataexportimport.php'),
            __DIR__ . '/Views' => resource_path('views/vendor/dataexportimport'),
        ]);
    }
}
