<?php

namespace App\Repositories\Contracts;

use App\Models\Logo;
use App\Models\Source;
use Illuminate\Support\Collection;

interface LogoRepositoryInterface
{
    public function findBySourceId(string $sourceId): ?Logo;

    public function upsert(array $payload): Logo;

    /**
     * @return Collection<int, Source>
     */
    public function listSourcesWithoutLogos(): Collection;
}
