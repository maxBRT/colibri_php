<?php

namespace App\Jobs;

use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanUpOutdatedPostJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('cleanup');
    }

    /**
     * Execute the job.
     */
    public function handle(PostRepositoryInterface $postRepository): void
    {

        $postRepository->deleteOutdatedPosts();

    }
}
