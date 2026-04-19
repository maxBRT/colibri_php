<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDescriptionsJob;
use Illuminate\Console\Command;

class PostsDescribeCommand extends Command
{
    protected $signature = 'posts:describe {--limit=}';

    protected $description = 'Dispatch AI description generation job for pending posts';

    public function handle(): int
    {
        $rawLimit = $this->option('limit');

        $limit = $rawLimit === null ? null : max(1, (int) $rawLimit);

        GenerateDescriptionsJob::dispatch(limit: $limit);

        $this->info('Descriptions generation job dispatched.');

        return self::SUCCESS;
    }
}
