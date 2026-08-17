<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_and_filter_customers(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        User::factory()->create(['name' => 'Nguyễn Khách VIP', 'email' => 'vip@example.com', 'role' => 'CUSTOMER', 'status' => 'LOCKED']);
        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => 'Khách VIP', 'status' => 'LOCKED']))->assertOk()->assertSee('vip@example.com');
    }

    public function test_admin_can_view_booking_history(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        Booking::create(['booking_code' => 'CUSTOMER-HISTORY', 'user_id' => $customer->id]);
        $this->actingAs($admin)->get(route('admin.customers.show', $customer))->assertOk()->assertSee('CUSTOMER-HISTORY');
    }

    public function test_locked_customer_cannot_login_and_can_be_unlocked(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['email' => 'locked@example.com', 'password' => 'password', 'role' => 'CUSTOMER', 'status' => 'LOCKED']);
        $this->post('/login', ['email' => 'locked@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($admin)->put(route('admin.customers.toggle-status', $customer))->assertRedirect();
        $this->assertSame('ACTIVE', $customer->fresh()->status);
    }

    public function test_admin_can_update_customer_information(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $this->actingAs($admin)->put(route('admin.customers.update', $customer), ['name' => 'Tên cập nhật', 'email' => 'updated@example.com', 'phone' => '0901234567'])->assertRedirect(route('admin.customers.show', $customer));
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'phone' => '0901234567']);
    }
}
