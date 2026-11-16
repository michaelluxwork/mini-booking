<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Slot;
use Carbon\CarbonImmutable;
use Database\Seeders\SlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function slot(int $hours = 2): Slot
    {
        $start = now()->addHours($hours)->timestamp;
        return Slot::create(['starts_at' => $start, 'ends_at' => $start + 3600]);
    }

    private function payload(Slot $slot): array
    {
        return ['slot_id' => $slot->id, 'customer_name' => 'Demo User', 'customer_email' => 'demo@example.test'];
    }

    public function test_free_future_slot_can_be_booked(): void
    {
        $slot = $this->slot();
        $response = $this->postJson('/api/bookings', $this->payload($slot))->assertCreated()->assertJsonStructure(['id']);
        $this->assertDatabaseHas('bookings', ['id' => $response->json('id'), 'slot_id' => $slot->id]);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_second_request_for_booked_slot_conflicts_without_second_booking(): void
    {
        $slot = $this->slot();
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        $this->postJson('/api/bookings', $this->payload($slot))->assertConflict()->assertJsonStructure(['message']);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_invalid_fields_and_unknown_slot_return_field_errors(): void
    {
        $this->postJson('/api/bookings', ['slot_id' => 9999, 'customer_name' => '', 'customer_email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['slot_id', 'customer_name', 'customer_email']);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_slot_starting_exactly_now_is_past(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', 'Europe/Moscow'));
        $slot = $this->slot(0);
        $this->postJson('/api/bookings', $this->payload($slot))->assertConflict();
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'past');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cancellation_is_idempotent_and_future_slot_can_be_booked_again(): void
    {
        $slot = $this->slot();
        $id = $this->postJson('/api/bookings', $this->payload($slot))->json('id');
        $this->deleteJson('/api/bookings/'.$id)->assertNoContent();
        $this->deleteJson('/api/bookings/'.$id)->assertNoContent();
        $this->assertDatabaseCount('bookings', 0);
        $this->getJson('/api/slots')->assertJsonPath('data.0.status', 'available');
        $this->postJson('/api/bookings', $this->payload($slot))->assertCreated();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_list_is_sorted_has_explicit_timezone_and_no_personal_fields(): void
    {
        $later = $this->slot(3);
        $earlier = $this->slot(1);
        $this->postJson('/api/bookings', $this->payload($earlier))->assertCreated();
        $response = $this->getJson('/api/slots')->assertOk()->assertJsonPath('timezone', 'Europe/Moscow')
            ->assertJsonPath('data.0.id', $earlier->id)->assertJsonPath('data.1.id', $later->id)
            ->assertJsonPath('data.0.status', 'booked');
        $this->assertStringEndsWith('+03:00', $response->json('data.0.starts_at'));
        $this->assertNotNull($response->json('data.0.booking_id'));
        $this->assertStringNotContainsString('customer_', $response->getContent());
        $this->assertStringNotContainsString('demo@example.test', $response->getContent());
    }

    public function test_seeder_is_repeatable_and_next_day_adds_only_nine_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 12:00:00', 'Europe/Moscow'));
        $this->seed(SlotSeeder::class);
        $this->assertDatabaseCount('slots', 63);
        $first = Slot::orderBy('starts_at')->first();
        $this->assertSame('2026-11-17 09:00', CarbonImmutable::createFromTimestampUTC($first->starts_at)->setTimezone('Europe/Moscow')->format('Y-m-d H:i'));
        $future = Slot::where('starts_at', '>', now()->timestamp)->first();
        $id = $this->postJson('/api/bookings', $this->payload($future))->json('id');
        $this->seed(SlotSeeder::class);
        $this->assertDatabaseCount('slots', 63);
        $this->assertDatabaseHas('bookings', ['id' => $id]);
        $this->travel(1)->days();
        $this->seed(SlotSeeder::class);
        $this->assertDatabaseCount('slots', 72);
        $this->assertDatabaseHas('bookings', ['id' => $id]);
    }

    public function test_form_encoded_creation_is_rejected(): void
    {
        $this->post('/api/bookings', $this->payload($this->slot()), ['Accept' => 'application/json'])->assertStatus(415);
    }
    public function test_untrusted_origin_cannot_mutate_bookings(): void
    {
        $this->postJson('/api/bookings', $this->payload($this->slot()), ['Origin' => 'https://attacker.example'])
            ->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_large_json_body_is_rejected(): void
    {
        $this->postJson('/api/bookings', ['customer_name' => str_repeat('x', 9000)])
            ->assertStatus(413);
    }
    public function test_untrusted_host_is_rejected(): void
    {
        $this->getJson('http://attacker.example/api/slots')->assertForbidden();
    }
}
