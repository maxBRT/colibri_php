<?php

namespace App\Repositories\Eloquent;

use App\Models\Source;
use App\Repositories\Contracts\SourceRepositoryInterface;
use Illuminate\Support\Collection;

class SourceRepository implements SourceRepositoryInterface
{
    public function list(?array $categories): Collection
    {
        return Source::query()
            ->with('logo')
            ->when($categories !== null && $categories !== [], function ($query) use ($categories) {
                $query->whereIn('category', $categories);
            })
            ->orderBy('name')
            ->get();
    }

    public function upsert(array $payload): Source
    {
        return Source::query()->updateOrCreate(
            ['id' => $payload['id']],
            [
                'name' => $payload['name'],
                'url' => $payload['url'],
                'category' => $payload['category'],
            ]
        );
    }

    public function listCategories(): Collection
    {
        return Source::query()
            ->orderBy('category')
            ->distinct()
            ->pluck('category')
            ->values();
    }
}
