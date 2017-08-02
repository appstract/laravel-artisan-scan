<?php

namespace Appstract\ArtisanScan\Commands;

use File;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\TableSeparator;

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
     * Store all results.
     *
     * @var array
     */
    protected $results = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Check for SSL certificate
        $this->checkSsl();
        $this->addTableSeperator();

        // Search sources for http://. Replace by https://
        $this->searchForHttp();
        $this->addTableSeperator();

        // Is yarn.lock present?
        $this->hasYarn();
        $this->addTableSeperator();

        // Remove all console.log lines in scripts
        $this->removeConsoleLogs();
        $this->addTableSeperator();

        // Check if assets are minified
        $this->hasMinifiedAssets();
        $this->addTableSeperator();

        // Are 404, 500 and 503 pages provided?
        $this->hasErrorPages();

        // Render
        return $this->table(['Category', 'Results'], $this->results);
    }

    /**
     * [checkSsl description].
     * @param  [type] $domain [description]
     * @return [type]         [description]
     */
    public function checkSsl()
    {
        if ((env('APP_ENV') == 'local')) {
            return;
        }

        $this->results = array_add($this->results, 'ssl', ['SSL certificate']);

        try {
            $domain = config('scanner.domain');
            $stream = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $read = fopen($domain, 'rb', false, $stream);
        } catch (\Exception $e) {
            return $this->results['ssl'][] = '<fg=red>Invalid</>';
        }

        // Check that SSL certificate is not null
        // peer_certificate should be for example "OpenSSL X.509 resource @342"
        $params = stream_context_get_params($read);
        $cert = $params['options']['ssl']['peer_certificate'];

        $this->results['ssl'] = $cert ? '<fg=green>Valid</>' : '<fg=red>Invalid</>';
    }

    /**
     * Search For Http.
     */
    public function searchForHttp()
    {
        $dir = config('scanner.views');
        $http = 0;
        $output = '';

        $di = new \RecursiveDirectoryIterator($dir);

        foreach (new \RecursiveIteratorIterator($di) as $filename => $file) {
            if (File::extension($file) == 'php') {
                if (preg_match_all('/http:\/\//', File::get($file), $matches)) {
                    $output .= $filename;
                    $http++;
                }
            }
        }

        $this->results = array_add($this->results, 'http', ["Mixed Content ($http found)", $output]);
    }

    /**
     * Remove console.logs from js file.
     */
    public function hasYarn()
    {
        $this->results['yarn'] = ['Yarn.lock', (File::exists(base_path('yarn.lock'))) ? '<fg=green>Present</>' : '<fg=red>File not found</>'];
    }

    /**
     * Remove console.logs from js file.
     */
    public function removeConsoleLogs()
    {
        $file  = File::get(public_path('js/app.js'));
        $regex = '/(?<console>(?:\/\/)?\s*console\.[^;]+;)/';
        $count = preg_match_all($regex, $file, $matches);

        File::put(public_path('js/app.js'), preg_replace($regex, '', $file));

        $this->results['console.log'] = ['console.log', "$count removed"];
    }

    /**
     * Check for minified asset files (css/js).
     */
    public function hasMinifiedAssets()
    {
        $output = collect(config('scanner.assets'))->map(function($value, $key){
            $fp = fopen($value, 'r');

            while (! feof($fp)) {
                fgets($fp);
            }

            fclose($fp);

            $bytes = round(File::size($value) / 1024).'KB';

            return ($bytes > 50) ? "<fg=yellow>$key: $bytes</>" : "<fg=green>$key: $bytes</>";
        })->implode("\n");

        $this->results['assets'] = ['Minified assets', $output];
    }

    /**
     * Remove console.logs from js file.
     */
    public function hasErrorPages()
    {
        $output = collect([404, 500, 503])->map(function($value, $key){
            return (! File::exists(base_path("resources/views/errors/$value.blade.php")))
                ? "<fg=red>$value not found</>"
                : "<fg=green>$value present</>";
        })->implode("\n");

        $this->results['errors'] = ['Error pages', $output];
    }

    /**
     * [addTableSeperator description].
     */
    protected function addTableSeperator()
    {
        $this->results[] = new TableSeparator();
    }
}
