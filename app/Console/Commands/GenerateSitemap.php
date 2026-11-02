<?php

namespace App\Console\Commands;

use App\Support\Seo\PublicSitemap;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:sitemap';

    protected $description = 'Print curated sitemap XML; /sitemap.xml is generated dynamically without a static file';

    public function handle(PublicSitemap $sitemap): int
    {
        // A public file would bypass the route's current publication/privacy gates.
        $this->output->write($sitemap->xml(), false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
