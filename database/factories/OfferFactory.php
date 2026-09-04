<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    protected $model = Offer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = now()->addDays(30)->startOfDay();

        return [
            'supplier_id' => Supplier::factory(),
            'property_id' => Property::factory(),
            'import_id' => fn (array $attributes) => Import::factory()->create([
                'supplier_id' => $attributes['supplier_id'],
            ])->getKey(),
            'external_id' => 'offer-'.fake()->unique()->numerify('######'),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkIn->copy()->addDays(5)->toDateString(),
            'max_guests' => fake()->numberBetween(1, 8),
            'price' => fake()->numberBetween(10000, 200000),
            'currency' => 'EUR',
            'available_units' => fake()->numberBetween(1, 10),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_units' => 0,
        ]);
    }
}
