<?php

namespace App\Jobs;

use App\Repositories\Contracts\LogoRepositoryInterface;
use App\Services\LogoDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyncLogosJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    public function __construct()
    {
        $this->onConnection('database');
        $this->onQueue('logos');
    }

    public function handle(LogoRepositoryInterface $logos, LogoDiscoveryService $discovery): void
    {
        $logos->listSourcesWithoutLogos()
            ->each(function ($source) use ($logos, $discovery): void {
                try {
                    $discovered = $discovery->discoverFromFeedUrl((string) $source->url);

                    if ($discovered === null) {
                        return;
                    }

                    $objectKey = sprintf(
                        'logos/%s.%s',
                        $source->id,
                        $discovered['extension']
                    );

                    Storage::disk('s3')->put($objectKey, $discovered['bytes']);

                    $logos->upsert([
                        'source_id' => $source->id,
                        'object_key' => $objectKey,
                        'url' => $this->buildPublicUrl($objectKey, $discovered['logo_url']),
                        'mime_type' => $discovered['mime_type'],
                        'size_bytes' => $discovered['size_bytes'],
                    ]);
                } catch (Throwable $exception) {
                    report($exception);

                    return;
                }
            });
    }

    private function buildPublicUrl(string $objectKey, string $fallbackUrl): string
    {
        $cdnBaseUrl = rtrim((string) config('filesystems.disks.s3.url', ''), '/');

        if ($cdnBaseUrl === '') {
            return $fallbackUrl;
        }

        return $cdnBaseUrl.'/'.ltrim($objectKey, '/');
    }
}
