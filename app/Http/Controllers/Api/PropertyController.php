<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertySearchRequest;
use App\Http\Resources\PropertyResource;
use App\Services\PropertySearchService;
use Illuminate\Http\JsonResponse;
use Throwable;

class PropertyController extends Controller
{
    public function __construct(
        private readonly PropertySearchService $propertySearchService,
    ) {}

    /**
     * @throws Throwable
     */
    public function index(PropertySearchRequest $request): JsonResponse
    {
        $paginator = $this->propertySearchService->search($request->validated());

        return response()->json([
            'data' => PropertyResource::collection($paginator->items())->resolve(),
            'next' => $paginator->nextPageUrl(),
            'prev' => $paginator->previousPageUrl(),
            'per_page' => $paginator->perPage(),
        ]);
    }
}
