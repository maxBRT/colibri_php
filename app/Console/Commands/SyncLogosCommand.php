<?php

namespace App\Console\Commands;

use App\Jobs\SyncLogosJob;
use Illuminate\Console\Command;

class SyncLogosCommand extends Command
{
    protected $signature = 'sync:logos';

    protected $description = 'Dispatch logo sync job';

    public function handle(): int
    {
        SyncLogosJob::dispatch();

        $this->info('Logo sync job dispatched.');

        return self::SUCCESS;
    }
}
