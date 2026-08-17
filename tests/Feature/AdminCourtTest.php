<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCourtTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_court_with_price_and_image(): void
    {
        Storage::fake('public');
        [$admin, $type, $slot] = $this->baseData();

        $this->actingAs($admin)->post(route('admin.courts.store'), $this->payload($type, $slot) + [
            'images' => [UploadedFile::fake()->image('court.jpg', 800, 600)],
        ])->assertRedirect();

        $court = Court::where('name', 'Sân Admin UC42')->firstOrFail();
        $this->assertDatabaseHas('court_prices', ['court_id' => $court->id, 'price' => 180000]);
        Storage::disk('public')->assertExists($court->images()->first()->image);
    }

    public function test_duplicate_court_name_is_rejected(): void
    {
        [$admin, $type, $slot] = $this->baseData();
        Court::create(['code' => 'EXISTING', 'name' => 'Sân Admin UC42', 'court_type_id' => $type->id]);

        $this->actingAs($admin)->post(route('admin.courts.store'), $this->payload($type, $slot))
            ->assertSessionHasErrors('name');
    }

    public function test_invalid_image_is_rejected(): void
    {
        [$admin, $type, $slot] = $this->baseData();

        $this->actingAs($admin)->post(route('admin.courts.store'), $this->payload($type, $slot) + [
            'images' => [UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('images.0');
    }

    public function test_court_with_booking_is_deactivated_instead_of_deleted(): void
    {
        [$admin, $type, $slot] = $this->baseData();
        $customer = User::factory()->create();
        $court = Court::create(['code' => 'HAS-BOOKING', 'name' => 'Sân có booking', 'court_type_id' => $type->id]);
        $booking = Booking::create(['booking_code' => 'UC42-BK', 'user_id' => $customer->id]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'booking_date' => today(), 'time_slot_id' => $slot->id, 'price' => 100000, 'subtotal' => 100000]);

        $this->actingAs($admin)->delete(route('admin.courts.destroy', $court))->assertRedirect();

        $this->assertDatabaseHas('courts', ['id' => $court->id, 'status' => 'INACTIVE']);
    }

    private function baseData(): array
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Loại sân UC42', 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => '09:00 - 10:00', 'start_time' => '09:00', 'end_time' => '10:00', 'duration' => 60, 'status' => 'ACTIVE']);

        return [$admin, $type, $slot];
    }

    private function payload(CourtType $type, TimeSlot $slot): array
    {
        return ['name' => 'Sân Admin UC42', 'court_type_id' => $type->id, 'description' => 'Mô tả sân', 'opening_time' => '06:00', 'closing_time' => '22:00', 'status' => 'ACTIVE', 'is_featured' => 1, 'prices' => [$slot->id => 180000]];
    }
}
