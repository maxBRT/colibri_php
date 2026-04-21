<?php

namespace App\Jobs;

use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDescriptionsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $limit = null)
    {
        $this->onConnection('database');
        $this->onQueue('enrichment');
    }

    public function handle(PostRepositoryInterface $posts): void
    {
        $posts->listPending($this->limit)
            ->each(fn ($post) => GenerateDescriptionForPostJob::dispatch($post));
    }
}
