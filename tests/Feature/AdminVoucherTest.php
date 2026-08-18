<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_valid_voucher(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->post(route('admin.vouchers.store'), $this->payload())->assertRedirect(route('admin.vouchers.index'));
        $this->assertDatabaseHas('vouchers', ['code' => 'SUMMER50', 'discount_type' => 'PERCENTAGE']);
    }

    public function test_duplicate_code_invalid_dates_values_and_usage_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        Voucher::create($this->payload() + ['used_count' => 0]);
        $bad = $this->payload();
        $bad['discount_value'] = 120;
        $bad['end_at'] = now()->subDay()->format('Y-m-d H:i:s');
        $bad['usage_limit'] = -1;
        $this->actingAs($admin)->post(route('admin.vouchers.store'), $bad)->assertSessionHasErrors(['code', 'discount_value', 'end_at', 'usage_limit']);
    }

    public function test_voucher_respects_time_minimum_and_usage_limit(): void
    {
        $voucher = Voucher::create($this->payload() + ['used_count' => 10]);
        $service = app(VoucherService::class);
        $this->assertFalse($service->validateAndApply($voucher->code, 500000)['valid']);
        $voucher->update(['used_count' => 0]);
        $this->assertFalse($service->validateAndApply($voucher->code, 50000)['valid']);
        $voucher->update(['start_at' => now()->addDay(), 'end_at' => now()->addDays(2)]);
        $this->assertFalse($service->validateAndApply($voucher->code, 500000)['valid']);
    }

    public function test_discount_never_exceeds_booking_amount(): void
    {
        $voucher = Voucher::create(array_merge($this->payload(), ['discount_type' => 'FIXED', 'discount_value' => 500000, 'min_order_amount' => 0, 'used_count' => 0]));
        $this->assertEquals(100000, $voucher->calculateDiscount(100000));
    }

    private function payload(): array
    {
        return ['code' => 'SUMMER50', 'name' => 'Ưu đãi mùa hè', 'discount_type' => 'PERCENTAGE', 'discount_value' => 50, 'min_order_amount' => 100000, 'max_discount' => 200000, 'start_at' => now()->subHour(), 'end_at' => now()->addDays(10), 'usage_limit' => 10, 'conditions' => 'Áp dụng cho booking hợp lệ.', 'status' => 'ACTIVE'];
    }
}
