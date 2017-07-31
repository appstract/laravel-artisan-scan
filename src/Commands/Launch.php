<?php

namespace Appstract\ArtisanScan\Commands;

use File;
use Illuminate\Console\Command;
use Appstract\LushHttp\LushFacade as Lush;
use Appstract\LushHttp\Exception\LushRequestException;

class Launch extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'scan:launch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan app launch';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Check for SSL certificate
        $this->checkSsl();

        // Search sources for http://. Replace by https://
        $this->searchForHttp();

        // Is yarn.lock present?
        $this->hasYarn();

        // Remove all console.log lines in scripts
        $this->removeConsoleLogs();

        // Check if assets are minified
        $this->hasMinifiedAssets();
       
        // Are 404, 500 and 503 pages provided?
        $this->hasErrorPages();
        
        return $this->table($this->headers, [$this->info]);
    }


    /**
     * [checkSsl description]
     * @param  [type] $domain [description]
     * @return [type]         [description]
     */
    public function checkSsl()
    {
        if ((env('APP_ENV') == 'local')) {
            return;
        }

        $domain = config('scanner.domain');

        $this->headers[] = 'SSL certificate';

        $stream = stream_context_create(['ssl' => ['capture_peer_cert' => true ]]);

        try {
            $read = fopen($domain, 'rb', false, $stream);
        }
        catch (\Exception $e) {
            $this->info[] = '<fg=red>Invalid</>';
            return;
        }

        $params  = stream_context_get_params($read);

        // Check that SSL certificate is not null
        // peer_certificate should be for example "OpenSSL X.509 resource @342" 
        $cert   = $params["options"]["ssl"]["peer_certificate"];

        $this->info[] = ($cert) ? '<fg=green>Valid</>' : '<fg=red>Invalid</>';
    }

    /**
     * Search For Http
     * 
     */
    public function searchForHttp()
    {
        $dir =  config('scanner.views');
        $http = 0;
        $output = '';

        $di = new \RecursiveDirectoryIterator($dir);
        foreach (new \RecursiveIteratorIterator($di) as $filename => $file) {
            if (File::extension($file) == 'php') {
                $content = File::get($file);
                $regex = '/http:\/\//'; 
                if (preg_match_all($regex, $content, $matches)) {
                    $output .= $filename . PHP_EOL;
                    $http++;
                }
            }
        }
        $this->headers[] = $http . ' Mixed Content found';
        $this->info[] = $output;
    }

    /**
     * Remove console.logs from js file
     * 
     */
    public function hasYarn()
    {
        $this->headers[] = 'Yarn.lock';
        $this->info[] = (! File::exists(base_path('yarn.lock'))) ? '<fg=red>File not found</>' : '<fg=green>Present</>';
    }

    /**
     * Remove console.logs from js file
     * 
     */
    public function removeConsoleLogs()
    {
        $js = File::get(public_path('js/app.js'));
        $regex = '/(?<console>(?:\/\/)?\s*console\.[^;]+;)/';
        $count = preg_match_all($regex, $js, $matches);
        $str = preg_replace($regex, '', $js);
        File::put(public_path('js/app.js'), $str);

        $this->headers[] = 'console logs';
        $this->info[] = $count . ' removed';
    }

    /**
     * Check for minified asset files (css/js)
     * 
     */
    public function hasMinifiedAssets()
    {
        $this->headers[] = 'Minified assets';

        $output = '';
        foreach (config('scanner.assets') as $key => $value) {
            $fp = fopen($value, 'r');

            while(!feof($fp)) {
                fgets($fp);
            }
            fclose($fp);

            $bytes = File::size($value);
            $bytes = round($bytes / 1024) . 'KB';

            $output .= ($bytes > 50) 
                ?  '<fg=yellow>'.$key. ': '.$bytes.'</>' 
                : PHP_EOL . '<fg=green>'.$key .': '.$bytes.'</>'; 
        } 

        $this->info[] = $output;
    }

    /**
     * Remove console.logs from js file
     * 
     */
    public function hasErrorPages()
    {
        $page404 = (! File::exists(base_path('resources/views/errors/404.blade.php'))) ? '<fg=red>404 not found</>' : '<fg=green>404 present</>';
        $page500 = (! File::exists(base_path('resources/views/errors/500.blade.php'))) ? '<fg=red>500 not found</>' : '<fg=green>500 present</>';
        $page503 = (! File::exists(base_path('resources/views/errors/503.blade.php'))) ? '<fg=red>503 not found</>' : '<fg=green>503 present</>';
        $output = $page404 . PHP_EOL . $page500 . PHP_EOL . $page503;
        
        $this->headers[] = 'Error pages';
        $this->info[] = $output;
    }
}