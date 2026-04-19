<?php

namespace App\Repositories\Eloquent;

use App\Models\Logo;
use App\Models\Source;
use App\Repositories\Contracts\LogoRepositoryInterface;
use Illuminate\Support\Collection;

class LogoRepository implements LogoRepositoryInterface
{
    public function findBySourceId(string $sourceId): ?Logo
    {
        return Logo::query()->where('source_id', $sourceId)->first();
    }

    public function upsert(array $payload): Logo
    {
        return Logo::query()->updateOrCreate(
            ['source_id' => $payload['source_id']],
            [
                'object_key' => $payload['object_key'],
                'url' => $payload['url'],
                'mime_type' => $payload['mime_type'] ?? null,
                'size_bytes' => $payload['size_bytes'] ?? null,
            ]
        );
    }

    public function listSourcesWithoutLogos(): Collection
    {
        return Source::query()
            ->whereDoesntHave('logo')
            ->orderBy('name')
            ->get();
    }
}
