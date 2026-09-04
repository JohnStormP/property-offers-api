<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'best_offer' => [
                'id' => (int) $this->offer_id,
                'supplier' => $this->supplier_code,
                'price' => (int) $this->price,
                'currency' => $this->currency,
                'available_units' => (int) $this->available_units,
                'expires_at' => $this->toUtcZulu($this->expires_at),
            ],
        ];
    }

    private function toUtcZulu(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
