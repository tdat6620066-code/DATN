<?php

namespace Tests\Feature;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_promotion_page_shows_only_vouchers_that_can_be_used(): void
    {
        Voucher::create($this->voucher(['code' => 'LIVE10']));
        Voucher::create($this->voucher(['code' => 'USED10', 'used_count' => 1, 'usage_limit' => 1]));
        Voucher::create($this->voucher(['code' => 'OFF10', 'status' => 'INACTIVE']));

        $this->get(route('promotions.index'))
            ->assertOk()
            ->assertSee('LIVE10')
            ->assertDontSee('USED10')
            ->assertDontSee('OFF10');
    }

    private function voucher(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10', 'name' => 'Ưu đãi đặt sân', 'discount_type' => 'PERCENTAGE',
            'discount_value' => 10, 'min_order_amount' => 0, 'start_at' => now()->subHour(),
            'end_at' => now()->addDay(), 'usage_limit' => null, 'used_count' => 0, 'status' => 'ACTIVE',
        ], $overrides);
    }
}
