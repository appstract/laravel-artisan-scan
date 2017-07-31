<?php

namespace Appstract\ArtisanScan\Commands;

use File;
use Illuminate\Console\Command;
use Appstract\LushHttp\LushFacade as Lush;
use Appstract\LushHttp\Exception\LushRequestException;

class Performance extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'scan:performance';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan app performance';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // check for optimized autoloader
        // check for chache

        $opcacheSettings = opcache_get_configuration(); 
        if ($opcacheSettings['directives']['opcache.enable'] == false) {            
            $this->error('Opcache is not enabled');
        }

        if (! File::exists(base_path('bootstrap/cache/config.php'))) {
            if ($this->confirm('Create a cache file for faster configuration loading?')) {
                $this->call('config:cache');
            }
        }

        if (! File::exists(base_path('bootstrap/cache/routes.php'))) {
            if ($this->confirm('Do you wish to create a route cache file for faster route registration?')) {
                $this->call('route:cache');
            }
        }

        if ($this->confirm('Optimize the framework for better performance?')) {
            $this->call('optimize');
        }
    }
}