<?php

namespace Tests\Feature\Auth;

use App\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase; // Using custom test database creation
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    // use RefreshDatabase; // Using custom test database creation

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response
            ->assertSeeVolt('pages.auth.confirm-password')
            ->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $component = Volt::test('pages.auth.confirm-password')
            ->set('password', 'password');

        $component->call('confirmPassword');

        // First check if there are any errors that might prevent redirect
        $component->assertHasNoErrors();

        // Then check for redirect
        $component->assertRedirect('/dashboard');
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        $component = Volt::test('pages.auth.confirm-password')
            ->set('password', 'wrong-password');

        $component->call('confirmPassword');

        $component
            ->assertNoRedirect()
            ->assertHasErrors('password');
    }
}
