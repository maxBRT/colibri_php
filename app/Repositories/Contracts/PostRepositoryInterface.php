<?php

namespace App\Repositories\Contracts;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PostRepositoryInterface
{
    public function paginate(array $filters, int $page, int $perPage): LengthAwarePaginator;

    public function findByGuid(string $guid): ?Post;

    public function insert(array $payload): Post;

    /**
     * @return Collection<int, Post>
     */
    public function listPending(int $limit): Collection;

    public function markDone(string $id, ?string $description): void;
}
