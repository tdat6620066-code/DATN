<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtType, Review, TimeSlot, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $court = Court::create(['code' => 'REVIEW', 'name' => 'Review court', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => 'Morning', 'start_time' => '08:00', 'end_time' => '09:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'REVIEW-001', 'user_id' => $user->id, 'status' => 'COMPLETED', 'payment_status' => 'PAID', 'total_amount' => 120000]);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today()->subDay(), 'price' => 120000, 'subtotal' => 120000, 'status' => 'COMPLETED']);
        $this->actingAs($user);
        return [$user, $booking, $court, ['court_id' => $court->id, 'rating' => 5, 'content' => 'Great court experience']];
    }

    public function test_customer_submits_once_and_only_approved_content_is_public(): void
    {
        [$user, $booking, $court, $data] = $this->fixture();
        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('Gửi đánh giá');
        $this->post(route('bookings.reviews.store', $booking), $data + ['status' => 'APPROVED', 'user_id' => 999])->assertRedirect(route('bookings.show', $booking));
        $this->assertDatabaseHas('reviews', $data + ['user_id' => $user->id, 'booking_id' => $booking->id, 'status' => 'PENDING']);
        $this->postJson(route('bookings.reviews.store', $booking), $data)->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->assertDatabaseCount('reviews', 1);
        $this->get(route('courts.show', $court))->assertOk()->assertDontSee($data['content']);
        Review::first()->update(['status' => 'APPROVED']);
        $this->get(route('courts.show', $court))->assertOk()->assertSee($data['content']);
    }

    public function test_ownership_status_and_court_are_enforced(): void
    {
        [$user, $booking, , $data] = $this->fixture();
        $this->actingAs(User::factory()->create(['role' => 'CUSTOMER']))->postJson(route('bookings.reviews.store', $booking), $data)->assertForbidden();
        $this->actingAs($user)->postJson(route('bookings.reviews.store', $booking), array_replace($data, ['court_id' => 999]))->assertForbidden();
        foreach (['CONFIRMED', 'CHECKED_IN', 'CANCELLED', 'PENDING_PAYMENT'] as $status) {
            $booking->update(['status' => $status]);
            $this->postJson(route('bookings.reviews.store', $booking), $data)->assertForbidden();
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_rating_and_comment_validation(): void
    {
        [, $booking, , $data] = $this->fixture();
        $this->postJson(route('bookings.reviews.store', $booking), array_replace($data, ['rating' => 6, 'content' => str_repeat('a', 2001)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['rating', 'content']);
        $this->postJson(route('bookings.reviews.store', $booking), array_replace($data, ['content' => '   ']))->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->assertDatabaseCount('reviews', 0);
    }
}
