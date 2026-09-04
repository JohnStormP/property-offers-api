<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SupplierSeeder::class);
    }

    public function test_import_requires_valid_payload_and_existing_supplier(): void
    {
        $this->postJson('/api/imports')
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'supplier',
                'external_import_id',
                'sent_at',
                'offers',
            ]);

        $this->postJson('/api/imports', $this->importPayload([
            'supplier' => 'unknown-supplier',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['supplier']);
    }

    public function test_import_is_accepted_and_processed_asynchronously(): void
    {
        $response = $this->postJson('/api/imports', $this->importPayload());

        $response->assertStatus(202)
            ->assertJsonPath('data.status', ImportStatus::Pending->value);

        $import = Import::query()->first();

        $this->assertNotNull($import);
        $import = $import->fresh();
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->total_offers);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);

        $this->assertDatabaseHas('properties', [
            'code' => 'BCN-0001',
            'city' => 'Barcelona',
        ]);

        $this->assertDatabaseHas('offers', [
            'external_id' => 'offer-a-10001',
            'price' => 72500,
            'available_units' => 2,
        ]);
    }

    public function test_duplicate_import_is_idempotent_and_does_not_requeue(): void
    {
        Queue::fake();

        $payload = $this->importPayload();

        $first = $this->postJson('/api/imports', $payload)
            ->assertStatus(202)
            ->json('data.id');

        Queue::assertPushed(ProcessImportJob::class, 1);

        $second = $this->postJson('/api/imports', $payload)
            ->assertStatus(202)
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Import::query()->count());
        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_existing_offer_is_updated_on_later_import(): void
    {
        $this->postJson('/api/imports', $this->importPayload())->assertStatus(202);

        $this->postJson('/api/imports', $this->importPayload([
            'external_import_id' => 'import-2026-09-01-002',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia Updated',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 69900,
                    'currency' => 'EUR',
                    'available_units' => 1,
                    'expires_at' => '2026-09-12T23:59:59Z',
                ],
            ],
        ]))->assertStatus(202);

        $this->assertSame(1, Offer::query()->count());
        $this->assertSame(1, Property::query()->count());
        $this->assertDatabaseHas('offers', [
            'external_id' => 'offer-a-10001',
            'price' => 69900,
            'available_units' => 1,
        ]);
        $this->assertDatabaseHas('properties', [
            'code' => 'BCN-0001',
            'name' => 'Apartment near Sagrada Familia Updated',
        ]);
    }

    public function test_import_status_endpoint_returns_current_state(): void
    {
        $this->postJson('/api/imports', $this->importPayload())->assertStatus(202);

        $import = Import::query()->firstOrFail();

        $this->getJson("/api/imports/$import->id")
            ->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.supplier', 'supplier-a')
            ->assertJsonPath('data.external_import_id', 'import-2026-09-01-001')
            ->assertJsonPath('data.status', ImportStatus::Completed->value)
            ->assertJsonPath('data.total_offers', 1)
            ->assertJsonPath('data.processed_offers', 1)
            ->assertJsonPath('data.error', null)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'supplier',
                    'external_import_id',
                    'sent_at',
                    'status',
                    'total_offers',
                    'processed_offers',
                    'error',
                    'created_at',
                    'completed_at',
                ],
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function importPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 72500,
                    'currency' => 'EUR',
                    'available_units' => 2,
                    'expires_at' => '2026-09-10T23:59:59Z',
                ],
            ],
        ], $overrides);
    }
}
