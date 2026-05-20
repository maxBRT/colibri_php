<?php

namespace App\Jobs;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDescriptionForPostJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    public function __construct(public Post $post)
    {
        $this->onConnection('database');
        $this->onQueue('enrichment');
    }

    public function handle(PostRepositoryInterface $posts, EnrichmentService $enrichment): void
    {
        $summary = $enrichment->generateSummary($this->post);

        if ($summary === null) {
            return;
        }

        $posts->markDone((string) $this->post->id, $summary);
    }
}
