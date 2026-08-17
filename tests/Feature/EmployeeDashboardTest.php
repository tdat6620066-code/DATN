<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_open_dashboard(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('TRUNG TÂM VẬN HÀNH');
    }

    public function test_customer_cannot_open_employee_dashboard(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        $this->actingAs($customer)
            ->get(route('employee.dashboard'))
            ->assertForbidden();
    }

    public function test_employee_is_redirected_to_dashboard_after_login(): void
    {
        User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'password',
            'role' => 'EMPLOYEE',
        ]);

        $this->post('/login', ['email' => 'employee@example.com', 'password' => 'password'])
            ->assertRedirect(route('employee.dashboard'));
    }
}
