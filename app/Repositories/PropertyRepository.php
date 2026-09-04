<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class PropertyRepository
{
    /**
     * @param  array{city?: string|null, check_in: string, check_out: string, guests: int, per_page?: int}  $filters
     *
     * @throws Throwable
     */
    public function searchWithCheapestOffer(array $filters): LengthAwarePaginator
    {
        $rankedOffers = DB::table('offers')
            ->join('suppliers', 'suppliers.id', '=', 'offers.supplier_id')
            ->select([
                'offers.property_id',
                'offers.id as offer_id',
                'suppliers.code as supplier_code',
                'offers.price',
                'offers.currency',
                'offers.available_units',
                'offers.expires_at',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY offers.property_id ORDER BY offers.price ASC, offers.id ASC) as offer_rank'),
            ])
            ->whereDate('offers.check_in', $filters['check_in'])
            ->whereDate('offers.check_out', $filters['check_out'])
            ->where('offers.max_guests', '>=', $filters['guests'])
            ->where('offers.available_units', '>', 0)
            ->where('offers.expires_at', '>', now());

        $query = DB::table('properties')
            ->joinSub($rankedOffers, 'best_offers', function ($join): void {
                $join->on('best_offers.property_id', '=', 'properties.id')
                    ->where('best_offers.offer_rank', '=', 1);
            })
            ->select([
                'properties.code',
                'properties.name',
                'properties.city',
                'best_offers.offer_id',
                'best_offers.supplier_code',
                'best_offers.price',
                'best_offers.currency',
                'best_offers.available_units',
                'best_offers.expires_at',
            ])
            ->orderBy('best_offers.price')
            ->orderBy('properties.code');

        if (! empty($filters['city'])) {
            $query->where('properties.city', $filters['city']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
