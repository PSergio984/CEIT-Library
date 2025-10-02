<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // This ensures the database is reset between tests

    protected function setUp(): void
    {
        parent::setUp();

        // Set up fake storage for file uploads
        Storage::fake('public');
    }

    public function test_validation_rules(): void
    {
        // Test with missing required fields - first_name
        $component1 = Volt::test('pages.auth.register')
            ->set('first_name', '')
            ->set('last_name', 'User')
            ->set('student_no', '20-3001')
            ->set('email', 'janrelParente@plv.edu.ph')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $this->assertTrue($component1->instance()->getErrorBag()->has('first_name'),
                         'Expected first_name validation error');

        // Test with invalid email
        $component2 = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '20-3001')
            ->set('email', 'invalid-email')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $this->assertTrue($component2->instance()->getErrorBag()->has('email'),
                         'Expected email validation error');

        // Test with mismatched passwords
        $component3 = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '20-3001')
            ->set('email', 'janrelParente@plv.edu.ph')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different_password')
            ->call('register');

        $this->assertTrue($component3->instance()->getErrorBag()->has('password'),
                         'Expected password validation error');
    }

    public function test_registration_with_complete_data(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'janrelparente@plv.edu.ph')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!');

        $component->call('register');

        // Assert successful registration (no validation errors)
        $component->assertHasNoErrors();

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'janrelParente@plv.edu.ph',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'student_no' => '20-3001',
        ]);
    }


}
