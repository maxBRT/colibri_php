<?php

namespace App\Jobs;

use App\Models\Source;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\RssService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchRssJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * @param  array<int, string>|null  $sourceIds
     */
    public function __construct(public ?array $sourceIds = null)
    {
        $this->onQueue('rss');
    }

    public function handle(RssService $rssService, PostRepositoryInterface $postRepository): void
    {
        Source::query()
            ->when($this->sourceIds !== null && $this->sourceIds !== [], function ($query) {
                $query->whereIn('id', $this->sourceIds);
            })
            ->orderBy('id')
            ->get()
            ->each(function (Source $source) use ($rssService, $postRepository): void {
                $entries = $rssService->fetchAndParse($source->url);

                foreach ($entries as $entry) {
                    if ($postRepository->findByGuid($entry['guid']) !== null) {
                        continue;
                    }

                    $postRepository->insert([
                        'title' => $entry['title'],
                        'description' => null,
                        'link' => $entry['link'],
                        'guid' => $entry['guid'],
                        'pub_date' => $entry['pub_date'],
                        'source_id' => $source->id,
                        'status' => 'processing',
                    ]);
                }
            });
    }
}
