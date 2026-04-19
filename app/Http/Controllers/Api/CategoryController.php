<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\SourceRepositoryInterface;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private readonly SourceRepositoryInterface $sourceRepository) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => $this->sourceRepository->listCategories()->all(),
        ]);
    }
}
