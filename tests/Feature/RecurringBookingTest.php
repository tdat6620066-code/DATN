<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\FixedBooking;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBookingTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReceiptImages;

    private User $customer;
    private Court $court;
    private Court $alternative;
    private TimeSlot $slot;
    private TimeSlot $otherSlot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-09-08 10:00:00'));
        $this->customer = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard']);
        $this->court = Court::create(['code' => 'C2', 'name' => 'Sân 2', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $this->alternative = Court::create(['code' => 'C1', 'name' => 'Sân 1', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $this->slot = TimeSlot::create(['name' => '19:00 - 20:00', 'start_time' => '19:00', 'end_time' => '20:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $this->otherSlot = TimeSlot::create(['name' => '20:00 - 21:00', 'start_time' => '20:00', 'end_time' => '21:00', 'duration' => 60, 'status' => 'ACTIVE']);
        foreach ([$this->court, $this->alternative] as $court) {
            foreach ([$this->slot, $this->otherSlot] as $slot) {
                $court->prices()->create(['time_slot_id' => $slot->id, 'price' => $court->id === $this->court->id ? 150000 : 140000,
                    'day_type' => 'WEEKDAY', 'effective_from' => '2026-01-01', 'status' => 'ACTIVE']);
            }
        }
        $this->actingAs($this->customer);
    }

    private function definition(array $overrides = []): array
    {
        return array_replace(['court_id' => $this->court->id, 'time_slot_id' => $this->slot->id,
            'booking_type' => 'weekly', 'start_date' => '2026-09-15', 'end_date' => '2026-12-15', 'days_of_week' => [2]], $overrides);
    }

    private function occupied(string $date): Booking
    {
        $booking = Booking::create(['booking_code' => 'EXIST-'.str()->random(10), 'user_id' => User::factory()->create()->id,
            'status' => 'CONFIRMED', 'payment_status' => 'PAID', 'subtotal' => 150000, 'total_amount' => 150000]);
        $booking->bookingDetails()->create(['court_id' => $this->court->id, 'time_slot_id' => $this->slot->id,
            'booking_date' => $date, 'status' => 'CONFIRMED', 'price' => 150000, 'subtotal' => 150000]);
        Payment::create(['booking_id' => $booking->id, 'amount' => 150000, 'status' => 'PAID', 'paid_at' => now()]);

        return $booking;
    }

    private function preview(array $definition = []): array
    {
        $this->post(route('bookings.recurring.preview'), $definition ?: $this->definition())->assertSessionHasNoErrors()->assertRedirect(route('bookings.create-recurring', ['resume' => 1]));

        return session('fixed_booking_draft');
    }

    private function review(array $draft, array $choices = []): array
    {
        $this->post(route('bookings.recurring.review'), ['preview_token' => $draft['token'], 'choices' => $choices])->assertSessionHasNoErrors();

        return session('fixed_booking_draft');
    }

    private function confirmation(array $draft): array
    {
        return ['preview_token' => $draft['token'], 'confirmation_key' => $draft['confirmation_key'], 'confirmed' => 1];
    }

    public function test_fourteen_dates_with_two_conflicts_create_twelve_independent_bookings_when_skipped(): void
    {
        $first = $this->occupied('2026-09-29');
        $second = $this->occupied('2026-10-13');
        $draft = $this->preview();
        $this->assertCount(12, $draft['preview']['schedules']);
        $this->assertCount(2, $draft['preview']['conflicts']);
        $this->assertDatabaseCount('bookings', 2);
        $this->assertDatabaseCount('fixed_bookings', 0);
        $this->get(route('bookings.create-recurring', ['resume' => 1]))->assertOk()->assertSee('Sân khác cùng giờ');
        $draft = $this->review($draft, ['2026-09-29-'.$this->slot->id => 'skip', '2026-10-13-'.$this->slot->id => 'skip']);
        $this->assertDatabaseCount('bookings', 2);
        $this->assertEquals(1800000, $draft['quote']['total']);
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->assertCount(12, $group->bookings);
        foreach ($group->bookings as $booking) {
            $this->assertCount(1, $booking->bookingDetails);
            $this->assertSame('PENDING', $booking->payment->status);
        }
        $this->assertSame('CONFIRMED', $first->fresh()->status);
        $this->assertSame('CONFIRMED', $second->fresh()->status);
        $this->get(route('bookings.fixed.show', $group))->assertOk()->assertSee('Đã bỏ qua');
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertRedirect(route('bookings.fixed.show', $group));
        $this->assertDatabaseCount('fixed_bookings', 1);
        $this->assertDatabaseCount('bookings', 14);
        $child = $group->bookings->first();
        $this->post(route('bookings.cancel', $child))->assertSessionHas('error');
        $this->assertSame('PENDING_PAYMENT', $child->fresh()->status);
        $this->assertSame(12, $group->bookings()->where('status', 'PENDING_PAYMENT')->count());
        $this->assertSame(1, Payment::where('fixed_booking_id', $group->id)->count());
        $this->assertSame(0, Payment::whereIn('booking_id', $group->bookings->modelKeys())->count());
        $this->actingAs(User::factory()->create(['role' => 'CUSTOMER']))->get(route('bookings.fixed.show', $group))->assertForbidden();
    }

    public function test_customer_explicitly_selects_another_court_and_another_time(): void
    {
        $this->occupied('2026-09-29');
        $this->occupied('2026-10-13');
        $draft = $this->preview();
        $this->post(route('bookings.recurring.review'), ['preview_token' => $draft['token']])->assertSessionHasErrors('choices');
        $draft = $this->review($draft, ['2026-09-29-'.$this->slot->id => $this->alternative->id.'-'.$this->slot->id,
            '2026-10-13-'.$this->slot->id => $this->court->id.'-'.$this->otherSlot->id]);
        $this->assertEquals(2090000, $draft['quote']['total']);
        $this->get(route('bookings.create-recurring', ['resume' => 1]))->assertOk()->assertSee('Xác nhận lịch đã chọn');
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->assertCount(14, $group->bookings);
        $this->assertSame($this->alternative->id, $group->bookings()->where('recurrence_key', '2026-09-29-'.$this->slot->id)->firstOrFail()->bookingDetails->first()->court_id);
        $this->assertSame($this->otherSlot->id, $group->bookings()->where('recurrence_key', '2026-10-13-'.$this->slot->id)->firstOrFail()->bookingDetails->first()->time_slot_id);
    }

    public function test_a_new_conflict_after_review_rejects_confirmation_without_creating_partial_group(): void
    {
        $draft = $this->review($this->preview());
        $existing = $this->occupied('2026-12-15');
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('fixed_bookings', 0);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame('CONFIRMED', $existing->fresh()->status);
    }

    public function test_price_change_requires_review_again(): void
    {
        $draft = $this->review($this->preview());
        $this->court->prices()->update(['price' => 160000]);
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_unoffered_alternative_and_all_skipped_are_rejected(): void
    {
        $this->occupied('2026-09-15');
        $draft = $this->preview($this->definition(['end_date' => '2026-09-15']));
        $key = '2026-09-15-'.$this->slot->id;
        $this->post(route('bookings.recurring.review'), ['preview_token' => $draft['token'], 'choices' => [$key => '999-999']])->assertSessionHasErrors('choices');
        $this->post(route('bookings.recurring.review'), ['preview_token' => $draft['token'], 'choices' => [$key => 'skip']])->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('fixed_bookings', 0);
    }

    public function test_forged_confirmation_and_expired_preview_cannot_create_bookings(): void
    {
        $this->post(route('bookings.store-recurring'), $this->definition() + ['confirmed' => 1])->assertSessionHasErrors('preview_token');
        $draft = $this->preview();
        $this->post(route('bookings.store-recurring'), ['preview_token' => $draft['token'], 'confirmation_key' => (string) str()->uuid(), 'confirmed' => 1])->assertSessionHasErrors('choices');
        $this->travel(21)->minutes();
        $this->post(route('bookings.recurring.review'), ['preview_token' => $draft['token']])->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_alternative_cannot_overlap_another_selected_occurrence(): void
    {
        $this->occupied('2026-09-15');
        $draft = $this->preview($this->definition(['end_date' => '2026-09-15', 'time_slot_ids' => [$this->slot->id, $this->otherSlot->id]]));
        $this->post(route('bookings.recurring.review'), ['preview_token' => $draft['token'],
            'choices' => ['2026-09-15-'.$this->slot->id => $this->court->id.'-'.$this->otherSlot->id]])->assertSessionHasErrors('choices');
        $this->assertDatabaseCount('fixed_bookings', 0);
    }

    public function test_preview_from_another_customer_cannot_be_confirmed(): void
    {
        $draft = $this->review($this->preview());
        $this->actingAs(User::factory()->create(['role' => 'CUSTOMER']));
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasErrors('preview_token');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_consecutive_slots_form_one_booking_per_date_with_all_details_and_correct_payment(): void
    {
        $draft = $this->preview($this->definition(['end_date' => '2026-09-22', 'time_slot_id' => null,
            'time_slot_ids' => [$this->otherSlot->id, $this->slot->id]]));
        $this->assertCount(4, $draft['preview']['schedules']);
        $this->get(route('bookings.create-recurring', ['resume' => 1]))->assertOk()->assertSee('time_slot_ids[]', false);
        $draft = $this->review($draft);
        $this->assertSame(2, $draft['quote']['booking_count']);
        $this->assertEquals(600000, $draft['quote']['total']);
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->assertCount(2, $group->bookings);
        foreach ($group->bookings as $booking) {
            $this->assertCount(2, $booking->bookingDetails);
            $this->assertEquals(600000, $booking->payment->amount);
            $this->assertEquals(300000, $booking->total_amount);
        }
        $page = $this->get(route('bookings.fixed.show', $group))->assertOk()->assertSee('19:00 - 20:00')->assertSee('20:00 - 21:00');
        $this->assertSame(1, substr_count($page->getContent(), $group->bookings->first()->booking_code));
    }

    public function test_nonconsecutive_and_overlapping_slots_are_rejected_before_preview(): void
    {
        foreach ([['22:00', '23:00'], ['19:30', '20:30']] as [$start, $end]) {
            $slot = TimeSlot::create(['name' => $start.' - '.$end, 'start_time' => $start, 'end_time' => $end, 'duration' => 60, 'status' => 'ACTIVE']);
            $this->post(route('bookings.recurring.preview'), $this->definition(['time_slot_ids' => [$this->slot->id, $slot->id]]))->assertSessionHasErrors('time_slot_ids');
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('fixed_bookings', 0);
    }

    public function test_daily_booking_also_requires_consecutive_hours_on_server(): void
    {
        $late = TimeSlot::create(['name' => '22:00 - 23:00', 'start_time' => '22:00', 'end_time' => '23:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $this->court->prices()->create(['time_slot_id' => $late->id, 'price' => 150000, 'day_type' => 'WEEKDAY', 'effective_from' => '2026-01-01', 'status' => 'ACTIVE']);
        $payload = ['court_id' => $this->court->id, 'booking_date' => '2026-09-15', 'time_slot_ids' => [$this->slot->id, $late->id]];
        $this->post(route('bookings.store'), $payload)->assertSessionHasErrors('time_slot_ids');
        $this->assertDatabaseCount('bookings', 0);
        try {
            app(\App\Services\BookingService::class)->createBooking($this->customer->id, [
                ['court_id' => $this->court->id, 'booking_date' => '2026-09-15', 'time_slot_id' => $this->slot->id],
                ['court_id' => $this->court->id, 'booking_date' => '2026-09-15', 'time_slot_id' => $late->id],
            ]);
            $this->fail('A service caller must not bypass the consecutive-hours rule.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('liền nhau', json_decode($e->getMessage(), true)[0]['message']);
        }
        $this->assertDatabaseCount('bookings', 0);
        $payload['time_slot_ids'] = [$this->otherSlot->id, $this->slot->id];
        $this->post(route('bookings.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_details', 2);
    }

    public function test_conflicting_part_of_consecutive_session_can_be_explicitly_skipped(): void
    {
        $existing = $this->occupied('2026-09-15');
        $draft = $this->preview($this->definition(['end_date' => '2026-09-22', 'time_slot_ids' => [$this->slot->id, $this->otherSlot->id]]));
        $this->assertCount(1, $draft['preview']['conflicts']);
        $draft = $this->review($draft, ['2026-09-15-'.$this->slot->id => 'skip']);
        $this->assertSame(2, $draft['quote']['booking_count']);
        $this->assertEquals(450000, $draft['quote']['total']);
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->assertCount(2, $group->bookings);
        $this->assertEquals([1, 2], $group->bookings->map(fn ($booking) => $booking->bookingDetails->count())->sort()->values()->all());
        $this->assertSame('CONFIRMED', $existing->fresh()->status);
    }

    public function test_monthly_dates_skip_nonexistent_days_and_discount_is_used_once_for_group(): void
    {
        $voucher = Voucher::create(['code' => 'FIXED', 'name' => 'Fixed discount', 'discount_type' => 'FIXED', 'discount_value' => 50000,
            'min_order_amount' => 0, 'usage_limit' => 1, 'used_count' => 0, 'start_at' => now()->subDay(), 'end_at' => now()->addYear(), 'status' => 'ACTIVE']);
        $draft = $this->review($this->preview($this->definition(['booking_type' => 'monthly', 'start_date' => '2026-09-15', 'end_date' => '2026-12-31', 'days_of_month' => [31], 'voucher_code' => 'FIXED'])));
        $this->assertCount(2, $draft['quote']['selected']);
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->assertEquals($draft['quote']['total'], $group->bookings()->sum('total_amount'));
        $this->assertEquals($draft['quote']['discount'], $group->bookings()->sum('discount'));
        $this->assertSame(1, $voucher->fresh()->used_count);
        foreach ($group->bookings as $booking) {
            $this->assertEquals($group->total_price, $booking->payment->amount);
        }
    }

    private function checkoutGroup(): FixedBooking
    {
        $draft = $this->review($this->preview($this->definition(['end_date' => '2026-09-22'])));
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        return FixedBooking::latest('id')->firstOrFail();
    }

    private function callbackData(FixedBooking $group, array $overrides = []): array
    {
        config(['vnpay.tmn_code' => 'TEST1234', 'vnpay.hash_secret' => 'test-secret']);
        $data = array_replace(['vnp_TmnCode' => 'TEST1234', 'vnp_TxnRef' => 'FIX'.$group->id,
            'vnp_Amount' => (string) (int) round((float) $group->total_price * 100),
            'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => '123456'], $overrides);
        ksort($data);
        $data['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($data, '', '&', PHP_QUERY_RFC1738), 'test-secret');
        return $data;
    }

    public function test_one_vnpay_receipt_confirms_every_session_and_duplicate_callbacks_are_idempotent(): void
    {
        $group = $this->checkoutGroup();
        $this->assertSame('AWAITING_PAYMENT', $group->status);
        $this->assertEquals(now()->addMinutes(15), $group->expires_at);
        $this->assertDatabaseCount('payments', 1);
        $this->get(route('bookings.fixed.show', $group))->assertOk()->assertSee('Thanh toán VNPay 300.000đ');
        $this->get(route('bookings.vnpay', $group->bookings->first()))->assertRedirect(route('bookings.fixed.show', $group));
        $data = $this->callbackData($group);
        $url = $this->post(route('bookings.fixed.pay', $group))->assertRedirect()->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('30000000', $query['vnp_Amount']);
        $this->assertSame($group->expires_at->format('YmdHis'), $query['vnp_ExpireDate']);
        $this->assertMatchesRegularExpression('/^FIX'.$group->id.'X[0-9]{14}[A-Fa-f0-9]{6}$/', $query['vnp_TxnRef']);
        $retryUrl = $this->post(route('bookings.fixed.pay', $group))->assertRedirect()->headers->get('Location');
        parse_str(parse_url($retryUrl, PHP_URL_QUERY), $retryQuery);
        $this->assertNotSame($query['vnp_TxnRef'], $retryQuery['vnp_TxnRef']);
        $this->get(route('bookings.vnpay.ipn', $data))->assertJson(['RspCode' => '00']);
        $this->get(route('bookings.vnpay.return', $data))->assertRedirect(route('bookings.fixed.show', $group));
        $this->assertSame('ACTIVE', $group->fresh()->status);
        $this->assertSame(2, $group->bookings()->where('status', 'CONFIRMED')->where('payment_status', 'PAID')->count());
        $this->assertSame('PAID', $group->payment->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_invalid_callbacks_and_child_references_cannot_confirm_the_group(): void
    {
        $group = $this->checkoutGroup();
        $data = $this->callbackData($group);
        $data['vnp_SecureHash'] = 'invalid';
        $this->get(route('bookings.vnpay.ipn', $data))->assertJson(['RspCode' => '97']);
        foreach ([['vnp_Amount' => '15000000'], ['vnp_TmnCode' => 'OTHER123']] as $overrides) {
            $this->get(route('bookings.vnpay.ipn', $this->callbackData($group, $overrides)))->assertJson(['RspCode' => isset($overrides['vnp_Amount']) ? '04' : '02']);
        }
        $child = $group->bookings->first();
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group, ['vnp_TxnRef' => $child->id.now()->format('YmdHis'), 'vnp_Amount' => '15000000'])))->assertJson(['RspCode' => '01']);
        $this->assertSame('PENDING', $group->payment->status);
        $this->assertSame(0, $group->bookings()->where('status', 'CONFIRMED')->count());
        $this->actingAs(User::factory()->create(['role' => 'CUSTOMER']))->post(route('bookings.fixed.pay', $group))->assertForbidden();
    }

    public function test_failed_payment_releases_every_slot_and_cannot_be_revived(): void
    {
        $group = $this->checkoutGroup();
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group, ['vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02'])))->assertJson(['RspCode' => '00']);
        $this->assertSame('PAYMENT_FAILED', $group->fresh()->status);
        $this->assertSame(2, $group->bookings()->where('status', 'EXPIRED')->count());
        foreach ($group->bookings as $booking) {
            foreach ($booking->bookingDetails as $detail) {
                $this->assertSame('AVAILABLE', app(\App\Services\CourtAvailabilityService::class)->checkAvailability($detail->court_id, $detail->booking_date, $detail->time_slot_id));
            }
        }
        $this->get(route('bookings.fixed.show', $group))->assertOk()->assertSee('Thanh toán thất bại')->assertDontSee('Thanh toán VNPay 300.000đ');
        $this->post(route('bookings.fixed.pay', $group))->assertSessionHas('error');
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group)))->assertJson(['RspCode' => '02']);
        $this->travel(16)->minutes();
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertSame(1, \App\Models\Notification::where('unique_key', 'fixed-booking:FAILED:'.$group->id)->count());
        $this->assertSame(0, \App\Models\Notification::where('unique_key', 'fixed-booking:EXPIRED:'.$group->id)->count());
        $this->assertSame('PAYMENT_FAILED', $group->fresh()->status);
    }

    public function test_ten_sessions_across_courts_are_all_held_then_released_on_failure(): void
    {
        $this->occupied('2026-09-29');
        $draft = $this->review($this->preview($this->definition(['end_date' => '2026-11-17'])), [
            '2026-09-29-'.$this->slot->id => $this->alternative->id.'-'.$this->slot->id,
        ]);
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $details = $group->bookings->flatMap->bookingDetails;
        $this->assertCount(10, $details);
        $this->assertCount(2, $details->pluck('court_id')->unique());
        $availability = app(\App\Services\CourtAvailabilityService::class);
        $other = User::factory()->create();
        foreach ($details as $detail) {
            $this->assertSame('HOLD', $availability->checkAvailability($detail->court_id, $detail->booking_date, $detail->time_slot_id));
            try {
                app(\App\Services\BookingService::class)->createBooking($other->id, [[
                    'court_id' => $detail->court_id, 'time_slot_id' => $detail->time_slot_id,
                    'booking_date' => $detail->booking_date->toDateString(),
                ]], null, ['booking_type' => 'weekly']);
                $this->fail('Every held session must reject another customer.');
            } catch (\Exception $exception) {
                $errors = json_decode($exception->getMessage(), true);
                $this->assertSame('Khung giờ này đang được giữ', $errors[0]['message'] ?? null);
            }
        }
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group, ['vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02'])))->assertJsonPath('RspCode', '00');
        foreach ($details as $detail) {
            $this->assertSame('AVAILABLE', $availability->checkAvailability($detail->court_id, $detail->booking_date, $detail->time_slot_id));
        }
        $this->assertSame(10, $group->bookings()->where('status', 'EXPIRED')->count());
    }

    public function test_expiration_releases_all_slots_and_late_payment_does_not_revive_them(): void
    {
        $group = $this->checkoutGroup();
        $availability = app(\App\Services\CourtAvailabilityService::class);
        $date = \Carbon\Carbon::parse('2026-09-15');
        $this->assertSame('HOLD', $availability->checkAvailability($this->court->id, $date, $this->slot->id));
        $this->travel(15)->minutes();
        $this->assertSame('AVAILABLE', $availability->checkAvailability($this->court->id, $date, $this->slot->id));
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertSame('EXPIRED', $group->fresh()->status);
        $this->assertSame(2, $group->bookings()->where('status', 'EXPIRED')->count());
        $replacement = $this->checkoutGroup();
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group)))->assertJson(['RspCode' => '02']);
        $this->assertSame('EXPIRED', $group->fresh()->status);
        $this->assertSame('AWAITING_PAYMENT', $replacement->fresh()->status);
    }

    public function test_refund_is_limited_to_one_session_and_reports_do_not_duplicate_the_receipt(): void
    {
        $group = $this->checkoutGroup();
        $data = $this->callbackData($group);
        $this->get(route('bookings.vnpay.ipn', $data))->assertJson(['RspCode' => '00']);
        [$child, $other] = $group->bookings()->orderBy('id')->get()->all();
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $payload = ['reason_code' => 'SERVICE_INTERRUPTED', 'reason' => 'Mất điện', 'supporting_information' => 'Hoàn một buổi', 'amount' => 150001, 'approve_now' => 1];
        $this->post(route('special-refunds.store', $child), $payload)->assertSessionHasErrors('amount');
        $payload['amount'] = 150000;
        $this->post(route('special-refunds.store', $child), $payload)->assertSessionHasNoErrors();
        $refund = $child->refundRequests()->firstOrFail();
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $refund), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'FIX-REFUND'])->assertSessionHasNoErrors();
        $this->get(route('bookings.vnpay.ipn', $data))->assertJson(['RspCode' => '00']);
        $this->assertSame('REFUNDED', $child->fresh()->payment_status);
        $this->assertSame('CANCELLED', $child->fresh()->status);
        $this->assertSame('PAID', $other->fresh()->payment_status);
        $this->assertSame('CONFIRMED', $other->fresh()->status);
        $this->assertSame('PARTIALLY_REFUNDED', $group->payment->fresh()->refund_status);
        $report = app(\App\Services\RevenueReportService::class);
        $this->assertEquals(300000, $report->report()['gross_revenue']);
        $this->assertEquals(150000, $report->report()['net_revenue']);
        $this->assertEquals(0, $report->report(null, null, collect([$child->id]))['net_revenue']);
        $this->assertEquals(150000, $report->report(null, null, collect([$other->id]))['net_revenue']);
        $this->get(route('admin.payments.index'))->assertOk()->assertSee($group->code);
        $this->get(route('admin.payments.show', $group->payment))->assertOk();
    }

    public function test_an_older_unpaid_single_booking_cannot_take_a_fixed_hold_or_paid_session(): void
    {
        $single = app(\App\Services\BookingService::class)->createBooking($this->customer->id,
            [['court_id' => $this->court->id, 'time_slot_id' => $this->slot->id, 'booking_date' => '2026-09-15']]);
        $this->travelTo($single->hold_expires_at);
        $group = $this->checkoutGroup();
        $singleData = $this->callbackData($group, ['vnp_TxnRef' => $single->id.now()->format('YmdHis'), 'vnp_Amount' => '15000000']);
        $this->get(route('bookings.vnpay.ipn', $singleData))->assertJson(['RspCode' => '02']);
        $this->assertSame('PENDING_PAYMENT', $single->fresh()->status);
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group)))->assertJson(['RspCode' => '00']);
        $this->get(route('bookings.vnpay.ipn', $singleData))->assertJson(['RspCode' => '02']);
        $this->assertSame('PENDING', $single->payment->fresh()->status);
    }

    public function test_late_callback_without_scheduler_expires_group_and_records_review(): void
    {
        $group = $this->checkoutGroup();
        $this->travel(16)->minutes();
        $this->get(route('bookings.vnpay.ipn', $this->callbackData($group)))->assertJson(['RspCode' => '02']);
        $this->assertSame('EXPIRED', $group->fresh()->status);
        $this->assertSame(2, $group->bookings()->where('status', 'EXPIRED')->count());
        $this->assertDatabaseHas('payment_transaction_logs', ['payment_id' => $group->payment->id, 'action' => 'VNPAY_REQUIRES_REVIEW']);
        $this->get(route('bookings.show', $group->bookings->first()))->assertOk();
    }

    public function test_fully_discounted_group_activates_without_gateway_and_completes_after_last_session(): void
    {
        Voucher::create(['code' => 'FREEFIX', 'name' => 'Free', 'discount_type' => 'FIXED', 'discount_value' => 300000,
            'min_order_amount' => 0, 'usage_limit' => 1, 'used_count' => 0, 'start_at' => now()->subDay(), 'end_at' => now()->addYear(), 'status' => 'ACTIVE']);
        $draft = $this->review($this->preview($this->definition(['end_date' => '2026-09-22', 'voucher_code' => 'FREEFIX'])));
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->post(route('bookings.fixed.pay', $group))->assertRedirect(route('bookings.fixed.show', $group));
        $this->assertSame('ACTIVE', $group->fresh()->status);
        [$first, $last] = $group->bookings()->get()->all();
        $first->update(['status' => 'COMPLETED']);
        $this->assertSame('ACTIVE', $group->fresh()->status);
        $last->update(['status' => 'COMPLETED']);
        $this->assertSame('COMPLETED', $group->fresh()->status);
    }

    public function test_group_notifications_are_sent_once_per_event_and_open_the_whole_order(): void
    {
        $group = $this->checkoutGroup();
        $notifications = \App\Models\Notification::where('user_id', $this->customer->id);
        $this->assertSame(1, (clone $notifications)->count());
        $created = (clone $notifications)->firstOrFail();
        $this->assertSame('fixed-booking:CREATED:'.$group->id, $created->unique_key);
        $this->assertStringContainsString('2 buổi', $created->content);
        $this->assertStringContainsString('300.000đ', $created->content);
        $this->get(route('notifications.open', $created))->assertRedirect(route('bookings.fixed.show', $group));
        $data = $this->callbackData($group);
        $this->get(route('bookings.vnpay.ipn', $data))->assertJson(['RspCode' => '00']);
        $this->get(route('bookings.vnpay.ipn', $data))->assertJson(['RspCode' => '00']);
        $this->assertSame(1, (clone $notifications)->where('type', 'PAYMENT')->count());
        $this->assertSame(2, (clone $notifications)->count());
        $this->assertSame(0, (clone $notifications)->whereIn('booking_id', $group->bookings->modelKeys())->count());
    }

    public function test_expiring_a_group_generates_one_notification(): void
    {
        $group = $this->checkoutGroup();
        $this->travel(16)->minutes();
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->artisan('bookings:expire-holds')->assertSuccessful();
        $this->assertSame(1, \App\Models\Notification::where('unique_key', 'fixed-booking:EXPIRED:'.$group->id)->count());
        $this->assertSame(2, \App\Models\Notification::where('user_id', $this->customer->id)->count());
    }

    public function test_newest_orders_come_first_and_a_fixed_group_uses_one_pagination_entry(): void
    {
        $draft = $this->review($this->preview());
        $this->post(route('bookings.store-recurring'), $this->confirmation($draft))->assertSessionHasNoErrors();
        $group = FixedBooking::firstOrFail();
        $this->travel(1)->minutes();
        $singles = [];
        for ($i = 0; $i < 16; $i++) {
            $singles[] = app(\App\Services\BookingService::class)->createBooking($this->customer->id,
                [['court_id' => $this->alternative->id, 'time_slot_id' => $this->slot->id, 'booking_date' => \Carbon\Carbon::parse('2026-09-15')->addDays($i)->toDateString()]])->id;
        }
        $page = $this->get(route('bookings.index'))->assertOk()->viewData('bookings');
        $this->assertSame(17, $page->total());
        $this->assertSame(array_slice(array_reverse($singles), 0, 15), $page->pluck('id')->all());
        $last = $this->get(route('bookings.index', ['page' => 2]))->assertOk()->assertSee($group->code)->viewData('bookings');
        $this->assertCount(2, $last);
        $this->assertSame($group->id, $last->last()->fixed_booking_id);
        $this->assertCount(14, $last->last()->fixedBooking->bookings);
    }

    public function test_existing_child_notifications_are_grouped_without_deleting_history(): void
    {
        $group = $this->checkoutGroup();
        $migration = require database_path('migrations/2026_09_10_030000_group_fixed_booking_notifications.php');
        $migration->down();
        foreach ($group->bookings as $child) {
            \Illuminate\Support\Facades\DB::table('user_notifications')->insert([
                'user_id' => $this->customer->id, 'booking_id' => $child->id, 'unique_key' => 'booking-created:'.$child->id,
                'type' => 'BOOKING_CREATED', 'title' => 'Old notification', 'content' => 'Old notification', 'is_read' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $migration->up();
        $this->assertSame(3, \Illuminate\Support\Facades\DB::table('user_notifications')->count());
        $this->assertSame(1, \App\Models\Notification::count());
        $this->assertSame(1, $this->customer->userNotifications()->where('is_read', false)->count());
        $this->assertSame(route('bookings.fixed.show', $group), \App\Models\Notification::firstOrFail()->action_url);
    }
}
