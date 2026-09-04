<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'external_import_id' => 'import-'.fake()->unique()->numerify('########'),
            'sent_at' => now(),
            'status' => ImportStatus::Pending,
            'payload' => [],
            'total_offers' => 0,
            'processed_offers' => 0,
            'error' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Failed,
            'error' => 'Import processing failed.',
            'completed_at' => now(),
        ]);
    }
}
