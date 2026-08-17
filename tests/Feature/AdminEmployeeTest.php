<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_employee_with_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->post(route('admin.employees.store'), ['name' => 'Nhân viên mới', 'email' => 'staff@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'EMPLOYEE', 'refund_approval_limit' => 500000, 'permissions' => ['employee.dashboard', 'refunds.manage']])->assertRedirect(route('admin.employees.index'));
        $staff = User::where('email', 'staff@example.com')->firstOrFail();
        $this->assertSame(['employee.dashboard', 'refunds.manage'], $staff->permissions);
    }

    public function test_duplicate_email_and_invalid_role_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        User::factory()->create(['email' => 'used@example.com']);
        $this->actingAs($admin)->post(route('admin.employees.store'), ['name' => 'Invalid', 'email' => 'used@example.com', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'SUPER_ADMIN'])->assertSessionHasErrors(['email', 'role']);
    }

    public function test_employee_without_permission_cannot_access_refunds(): void
    {
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'permissions' => ['employee.dashboard']]);
        $this->actingAs($staff)->get(route('employee.refund-requests.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('employee.dashboard'))->assertOk();
    }

    public function test_admin_can_lock_and_unlock_employee(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $staff = User::factory()->create(['role' => 'EMPLOYEE', 'status' => 'ACTIVE']);
        $this->actingAs($admin)->put(route('admin.employees.toggle-status', $staff))->assertRedirect();
        $this->assertSame('LOCKED', $staff->fresh()->status);
        $this->actingAs($admin)->put(route('admin.employees.toggle-status', $staff))->assertRedirect();
        $this->assertSame('ACTIVE', $staff->fresh()->status);
    }

    public function test_non_admin_cannot_manage_staff_roles(): void
    {
        $staff = User::factory()->create(['role' => 'EMPLOYEE']);
        $this->actingAs($staff)->get(route('admin.employees.index'))->assertForbidden();
    }
}
