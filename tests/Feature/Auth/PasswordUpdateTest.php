<?php

namespace Tests\Feature\Auth;

use App\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase; // Using custom test database creation
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    // use RefreshDatabase; // Using custom test database creation

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password')
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        // Refresh the user from database to get the latest data
        $user->refresh();

        // Add debugging to see what's happening
    }
}
