<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailDomainValidationTest extends TestCase
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

    /** @test - TC043: Email Domain - Validation Rule */
    public function only_plv_edu_ph_emails_are_accepted_during_registration()
    {
        // Test invalid email domain using Livewire Volt component
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('email', 'user@gmail.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register');

        $component->assertHasErrors(['email']);

        // Test valid PLV email
        $component2 = Volt::test('pages.auth.register')
            ->set('first_name', 'Jane')
            ->set('last_name', 'Smith')
            ->set('email', 'janesmith@plv.edu.ph')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register');

        $component2->assertHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'janesmith@plv.edu.ph']);
    }
}

