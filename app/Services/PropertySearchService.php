<?php

namespace App\Services;

use App\Repositories\PropertyRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Throwable;

readonly class PropertySearchService
{
    public function __construct(
        private PropertyRepository $propertyRepository,
    ) {}

    /**
     * @param  array{city?: string|null, check_in: string, check_out: string, guests: int, per_page?: int}  $filters
     *
     * @throws Throwable
     */
    public function search(array $filters): LengthAwarePaginator
    {
        return $this->propertyRepository->searchWithCheapestOffer($filters);
    }
}
