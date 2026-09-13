<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registrationData(): array
    {
        return [
            'name' => 'Test Customer',
            'email' => 'newcustomer@example.com',
            'phone' => '0987654321',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ];
    }

    public function test_valid_registration_creates_and_logs_in_customer(): void
    {
        $this->post(route('register.store'), $this->registrationData())
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'newcustomer@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('CUSTOMER', $user->role);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_invalid_phone_displays_field_error_without_flashing_password(): void
    {
        $data = array_replace($this->registrationData(), ['phone' => '123']);

        $this->from(route('register'))->post(route('register.store'), $data)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('phone')
            ->assertSessionMissing('error')
            ->assertSessionHas('_old_input.email', $data['email'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get(route('register'))->assertSee('Số điện thoại không hợp lệ.');
    }

    public function test_missing_terms_and_mismatched_password_display_field_errors(): void
    {
        $data = array_replace($this->registrationData(), ['password_confirmation' => 'different']);
        unset($data['terms']);

        $this->from(route('register'))->post(route('register.store'), $data)
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['password', 'terms']);

        $this->assertDatabaseCount('users', 0);
    }
}
