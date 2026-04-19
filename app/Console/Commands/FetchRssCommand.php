<?php

namespace App\Console\Commands;

use App\Jobs\FetchRssJob;
use Illuminate\Console\Command;

class FetchRssCommand extends Command
{
    protected $signature = 'rss:fetch {--source=*}';

    protected $description = 'Dispatch RSS ingestion job';

    public function handle(): int
    {
        $sources = $this->option('source');

        FetchRssJob::dispatch($sources === [] ? null : $sources);

        $this->info('RSS fetch job dispatched.');

        return self::SUCCESS;
    }
}
