<?php

namespace App\Repositories\Eloquent;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PostRepository implements PostRepositoryInterface
{
    public function paginate(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $sourceIds = $filters['sources'] ?? [];

        return Post::query()
            ->with('source.logo')
            ->when($sourceIds !== [], function ($query) use ($sourceIds) {
                $query->whereIn('source_id', $sourceIds);
            })
            ->orderByDesc('pub_date')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findByGuid(string $guid): ?Post
    {
        return Post::query()->where('guid', $guid)->first();
    }

    public function insert(array $payload): Post
    {
        return Post::query()->create($payload);
    }

    public function listPending(?int $limit = null): Collection
    {
        return Post::query()
            ->where('status', 'processing')
            ->orderBy('pub_date')
            ->when($limit !== null, function ($query) use ($limit) {
                $query->limit($limit);
            })
            ->get();
    }

    public function markDone(string $id, ?string $description): void
    {
        Post::query()
            ->whereKey($id)
            ->update([
                'status' => 'done',
                'description' => $description,
            ]);
    }

    public function deleteOutdatedPosts(): void
    {
        Post::query()
            ->where('pub_date', '<', now()->subDays(30))
            ->delete();
    }
}
