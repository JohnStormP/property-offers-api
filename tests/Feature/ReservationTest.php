<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_requires_valid_payload(): void
    {
        $offer = Offer::factory()->create([
            'available_units' => 2,
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson("/api/offers/$offer->id/reservations")
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'client_reference',
                'customer_name',
                'customer_email',
            ]);
    }

    public function test_reservation_is_created_and_decrements_available_units(): void
    {
        $offer = Offer::factory()->create([
            'available_units' => 2,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson("/api/offers/$offer->id/reservations", [
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.offer_id', $offer->id)
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c')
            ->assertJsonPath('data.customer_name', 'John Smith')
            ->assertJsonPath('data.customer_email', 'john@example.com');

        $this->assertSame(1, $offer->fresh()->available_units);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_last_unit_cannot_be_reserved_twice(): void
    {
        $offer = Offer::factory()->create([
            'available_units' => 1,
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson("/api/offers/$offer->id/reservations", [
            'client_reference' => 'web-order-first',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])->assertCreated();

        $this->postJson("/api/offers/$offer->id/reservations", [
            'client_reference' => 'web-order-second',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Offer is no longer available for reservation.');

        $this->assertSame(0, $offer->fresh()->available_units);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_expired_offer_cannot_be_reserved(): void
    {
        $offer = Offer::factory()->create([
            'available_units' => 3,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/offers/$offer->id/reservations", [
            'client_reference' => 'web-order-expired',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ])->assertStatus(409);

        $this->assertSame(3, $offer->fresh()->available_units);
        $this->assertSame(0, Reservation::query()->count());
    }

    public function test_client_reference_must_be_unique(): void
    {
        $offer = Offer::factory()->create([
            'available_units' => 2,
            'expires_at' => now()->addDay(),
        ]);

        $payload = [
            'client_reference' => 'web-order-same',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ];

        $this->postJson("/api/offers/$offer->id/reservations", $payload)->assertCreated();

        $this->postJson("/api/offers/$offer->id/reservations", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_reference']);
    }
}
