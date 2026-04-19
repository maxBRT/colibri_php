<?php

namespace App\Jobs;

use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDescriptionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    public function __construct(public ?int $limit = null)
    {
        $this->onConnection('database');
        $this->onQueue('enrichment');
    }

    public function handle(PostRepositoryInterface $posts, EnrichmentService $enrichment): void
    {
        $posts->listPending($this->limit)
            ->each(function ($post) use ($posts, $enrichment): void {
                $summary = $enrichment->generateSummary($post);

                if ($summary === null) {
                    return;
                }

                $posts->markDone((string) $post->id, $summary);
            });
    }
}
