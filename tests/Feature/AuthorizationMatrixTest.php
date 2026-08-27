<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_areas(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
        $this->get(route('courts.index'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('employee.dashboard'))->assertRedirect(route('login'));
        $this->get(route('bookings.index'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_employee_or_admin_areas(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'status' => 'ACTIVE']);

        $this->actingAs($customer)->get(route('employee.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_employee_can_only_access_assigned_modules(): void
    {
        $employee = User::factory()->create([
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
            'permissions' => ['courts.status.manage'],
        ]);

        $this->actingAs($employee)->get(route('employee.courts.index'))->assertOk();
        $this->actingAs($employee)->get(route('employee.dashboard'))->assertForbidden();
        $this->actingAs($employee)->get(route('employee.refund-requests.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_is_redirected_from_shared_booking_url_to_admin_booking_management(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('bookings.index'))
            ->assertRedirect(route('admin.bookings.index'));
    }

    public function test_admin_cannot_enter_employee_area(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);

        $this->actingAs($admin)->get(route('employee.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('employee.refund-requests.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('employee.courts.index'))->assertForbidden();
    }

    public function test_staff_are_redirected_from_home_to_their_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'status' => 'ACTIVE']);
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'status' => 'ACTIVE']);
        $customer = User::factory()->create(['role' => 'CUSTOMER', 'status' => 'ACTIVE']);

        $this->actingAs($admin)->get(route('home'))
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($employee)->get(route('home'))
            ->assertRedirect(route('employee.dashboard'));
        $this->actingAs($customer)->get(route('home'))
            ->assertOk()->assertSee('Đặt sân ngay');
    }

    public function test_employee_is_redirected_from_shared_booking_url_to_operations(): void
    {
        $employee = User::factory()->create([
            'role' => 'EMPLOYEE',
            'status' => 'ACTIVE',
            'permissions' => ['employee.dashboard'],
        ]);

        $this->actingAs($employee)->get(route('bookings.index'))
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_locked_authenticated_account_is_logged_out(): void
    {
        $locked = User::factory()->create(['role' => 'ADMIN', 'status' => 'LOCKED']);

        $this->actingAs($locked)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
