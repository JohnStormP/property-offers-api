<?php

namespace App\Services;

use App\Exceptions\OfferNotAvailableException;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReservationService
{
    /**
     * @param  array{client_reference: string, customer_name: string, customer_email: string}  $data
     *
     * @throws OfferNotAvailableException
     * @throws Throwable
     */
    public function reserve(Offer $offer, array $data): Reservation
    {
        return DB::transaction(function () use ($offer, $data) {
            $updated = Offer::query()
                ->whereKey($offer->id)
                ->where('available_units', '>', 0)
                ->where('expires_at', '>', now())
                ->decrement('available_units');

            if ($updated === 0) {
                throw new OfferNotAvailableException;
            }

            return Reservation::query()->create([
                'offer_id' => $offer->id,
                'client_reference' => $data['client_reference'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
            ]);
        });
    }
}
