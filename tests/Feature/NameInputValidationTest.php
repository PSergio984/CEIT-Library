<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NameInputValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function getRoleId(string $roleName): int
    {
        return Role::where('name', $roleName)->value('id') ?? match ($roleName) {
            'student' => 1,
            'librarian' => 2,
            'admin' => 3,
            'super_admin' => 4,
            default => 1,
        };
    }

    /** @test - TC044: Name Input - Special Characters Filtering */
    public function numbers_and_symbols_are_rejected_in_name_fields()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Test first name with numbers
        $component = Volt::test('profile.update-profile-information-form')
            ->set('first_name', 'Juan123')
            ->set('last_name', 'Doe')
            ->set('email', $user->email);

        $component->call('updateProfileInformation');
        $component->assertHasErrors(['first_name']);

        // Test last name with symbols
        $component = Volt::test('profile.update-profile-information-form')
            ->set('first_name', 'Juan')
            ->set('last_name', 'Dela@Cruz')
            ->set('email', $user->email);

        $component->call('updateProfileInformation');
        $component->assertHasErrors(['last_name']);

        // Test valid names
        $component = Volt::test('profile.update-profile-information-form')
            ->set('first_name', 'Juan')
            ->set('last_name', 'Dela Cruz')
            ->set('email', $user->email);

        $component->call('updateProfileInformation');
        $component->assertHasNoErrors();
    }
}
