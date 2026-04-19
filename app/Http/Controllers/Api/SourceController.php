<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSourcesRequest;
use App\Http\Resources\SourceResource;
use App\Repositories\Contracts\SourceRepositoryInterface;
use Illuminate\Http\JsonResponse;

class SourceController extends Controller
{
    public function __construct(private readonly SourceRepositoryInterface $sourceRepository) {}

    public function index(ListSourcesRequest $request): JsonResponse
    {
        $sources = $this->sourceRepository->list($request->categories());

        return response()->json([
            'sources' => SourceResource::collection($sources)->resolve(),
        ]);
    }
}
