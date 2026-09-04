<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
    }

    public function test_search_requires_dates_and_guests(): void
    {
        $this->getJson('/api/properties')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['check_in', 'check_out', 'guests']);
    }

    public function test_search_returns_cheapest_actual_offer_per_property(): void
    {
        $supplierA = Supplier::query()->where('code', 'supplier-a')->firstOrFail();
        $supplierB = Supplier::query()->where('code', 'supplier-b')->firstOrFail();
        $property = Property::factory()->create([
            'code' => 'BCN-0001',
            'name' => 'Apartment near Sagrada Familia',
            'city' => 'Barcelona',
        ]);
        $otherCity = Property::factory()->create([
            'code' => 'MAD-0001',
            'city' => 'Madrid',
        ]);

        $importA = Import::factory()->create([
            'supplier_id' => $supplierA->id,
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
        $importB = Import::factory()->create([
            'supplier_id' => $supplierB->id,
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'property_id' => $property->id,
            'import_id' => $importA->id,
            'external_id' => 'offer-a-expensive',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 90000,
            'available_units' => 2,
            'expires_at' => now()->addDays(3),
        ]);

        $cheapest = Offer::factory()->create([
            'supplier_id' => $supplierB->id,
            'property_id' => $property->id,
            'import_id' => $importB->id,
            'external_id' => 'offer-b-cheap',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'available_units' => 1,
            'expires_at' => now()->addDays(3),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'property_id' => $property->id,
            'import_id' => $importA->id,
            'external_id' => 'offer-a-zero-units',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 1000,
            'available_units' => 0,
            'expires_at' => now()->addDays(3),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'property_id' => $property->id,
            'import_id' => $importA->id,
            'external_id' => 'offer-a-expired',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 500,
            'available_units' => 5,
            'expires_at' => now()->subHour(),
        ]);

        Offer::factory()->create([
            'supplier_id' => $supplierA->id,
            'property_id' => $otherCity->id,
            'import_id' => $importA->id,
            'external_id' => 'offer-madrid',
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 50000,
            'available_units' => 2,
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->getJson('/api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2&page=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('data.0.best_offer.id', $cheapest->id)
            ->assertJsonPath('data.0.best_offer.supplier', 'supplier-b')
            ->assertJsonPath('data.0.best_offer.price', 72500)
            ->assertJsonStructure([
                'data',
                'next',
                'prev',
                'per_page',
            ]);
    }

    public function test_search_excludes_offers_with_insufficient_guests_capacity(): void
    {
        $supplier = Supplier::query()->where('code', 'supplier-a')->firstOrFail();
        $property = Property::factory()->create(['city' => 'Barcelona']);
        $import = Import::factory()->create(['supplier_id' => $supplier->id]);

        Offer::factory()->create([
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'import_id' => $import->id,
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 2,
            'price' => 40000,
            'available_units' => 2,
            'expires_at' => now()->addDays(3),
        ]);

        $this->getJson('/api/properties?check_in=2026-10-10&check_out=2026-10-15&guests=3')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
