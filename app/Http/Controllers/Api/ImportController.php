<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Models\Import;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    /**
     * @throws Throwable
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $import = $this->importService->queue($request->validated());
        $import->loadMissing('supplier');

        return new ImportResource($import)
            ->response()
            ->setStatusCode(202);
    }

    public function show(Import $import): ImportResource
    {
        $import->loadMissing('supplier');

        return new ImportResource($import);
    }
}
