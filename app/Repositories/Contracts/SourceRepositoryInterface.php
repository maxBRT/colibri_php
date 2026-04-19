<?php

namespace App\Repositories\Contracts;

use App\Models\Source;
use Illuminate\Support\Collection;

interface SourceRepositoryInterface
{
    /**
     * @return Collection<int, Source>
     */
    public function list(?array $categories): Collection;

    public function upsert(array $payload): Source;

    /**
     * @return Collection<int, string>
     */
    public function listCategories(): Collection;
}
