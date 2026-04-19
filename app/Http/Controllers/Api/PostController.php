<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListPostsRequest;
use App\Http\Resources\PostResource;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function __construct(private readonly PostRepositoryInterface $postRepository) {}

    public function index(ListPostsRequest $request): JsonResponse
    {
        $posts = $this->postRepository->paginate(
            filters: ['sources' => $request->sources()],
            page: $request->pageNumber(),
            perPage: $request->perPage(),
        );

        return response()->json([
            'posts' => PostResource::collection($posts->items())->resolve(),
            'pagination' => [
                'page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'total_pages' => $posts->lastPage(),
            ],
        ]);
    }
}
