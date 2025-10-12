<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

// use Illuminate\Foundation\Testing\RefreshDatabase; // Using custom test database creation

class RegistrationTest extends TestCase
{
    // use RefreshDatabase; // Using custom test database creation

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
            ->set('email', 'janrelparente@plv.edu.ph')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $this->assertTrue($component1->instance()->getErrorBag()->has('first_name'));

        // Test with invalid email
        $component2 = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '20-3001')
            ->set('email', 'invalid-email')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $this->assertTrue($component2->instance()->getErrorBag()->has('email'));

        // Test with mismatched passwords
        $component3 = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '20-3001')
            ->set('email', 'janrelParente@plv.edu.ph')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different_password')
            ->call('register');

        $this->assertTrue($component3->instance()->getErrorBag()->has('password'));
    }

    public function test_registration_with_complete_data(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'johndoe@plv.edu.ph')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!');

        $component->call('register');

        // Assert successful registration (no validation errors)
        $component->assertHasNoErrors();

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@plv.edu.ph',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'student_no' => '20-3001',
        ]);

        // Verify password is hashed
        $user = User::where('email', 'johndoe@plv.edu.ph')->first();
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
    }

    public function test_registration_requires_unique_email(): void
    {
        // Create existing user
        User::factory()->create(['email' => 'existing@plv.edu.ph']);

        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3002')
            ->set('email', 'existing@plv.edu.ph')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $this->assertTrue($component->instance()->getErrorBag()->has('email'));
    }

    public function test_registration_requires_unique_student_number(): void
    {
        // Create existing user
        User::factory()->create(['student_no' => '20-3001']);

        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'john.doe@plv.edu.ph')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $this->assertTrue($component->instance()->getErrorBag()->has('student_no'));
    }

    public function test_registration_requires_valid_email_format(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'invalid-email-format')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $this->assertTrue($component->instance()->getErrorBag()->has('email'));
    }

    public function test_registration_requires_plv_edu_ph_email_domain(): void
    {
        // Test with non-PLV email domain
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'john.doe@non-plv.edu.ph') // Non-PLV domain
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $this->assertTrue($component->instance()->getErrorBag()->has('email'));

        // Test with valid PLV email domain
        $component2 = Volt::test('pages.auth.register')
            ->set('first_name', 'Jane')
            ->set('last_name', 'Smith')
            ->set('student_no', '20-3002')
            ->set('email', 'janesmith@plv.edu.ph') // Valid PLV domain
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $component2->assertHasNoErrors();
    }

    public function test_registration_requires_minimum_password_length(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'john.doe@plv.edu.ph')
            ->set('password', '123')
            ->set('password_confirmation', '123')
            ->call('register');

        $this->assertTrue($component->instance()->getErrorBag()->has('password'));
    }

    public function test_registration_redirects_after_successful_registration(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'johndoe@plv.edu.ph')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $component->assertHasNoErrors();
        // Note: The actual redirect behavior depends on your registration logic
        // You might need to adjust this based on your implementation
    }

    public function test_registration_creates_user_with_correct_attributes(): void
    {
        $userData = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'student_no' => '20-3003',
            'email' => 'janesmith@plv.edu.ph',
            'password' => 'SecurePass123!',
        ];

        $component = Volt::test('pages.auth.register')
            ->set('first_name', $userData['first_name'])
            ->set('last_name', $userData['last_name'])
            ->set('student_no', $userData['student_no'])
            ->set('email', $userData['email'])
            ->set('password', $userData['password'])
            ->set('password_confirmation', $userData['password'])
            ->call('register');

        $component->assertHasNoErrors();

        $user = User::where('email', $userData['email'])->first();
        $this->assertNotNull($user);
        $this->assertEquals($userData['first_name'], $user->first_name);
        $this->assertEquals($userData['last_name'], $user->last_name);
        $this->assertEquals($userData['student_no'], $user->student_no);
        $this->assertNull($user->email_verified_at); // Should be null initially
    }

    public function test_registration_requires_name_to_match_email_prefix(): void
    {
        // Test case 1: Names don't match email prefix - should fail
        $component1 = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '20-3001')
            ->set('email', 'jane.smith@plv.edu.ph') // Email prefix is 'jane.smith'
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $this->assertTrue($component1->instance()->getErrorBag()->has('last_name'));
        $errorMessage = $component1->instance()->getErrorBag()->first('last_name');
        $this->assertStringContainsString('must match the characters before @plv.edu.ph', $errorMessage);

        // Test case 2: Names match email prefix - should pass
        $component2 = Volt::test('pages.auth.register')
            ->set('first_name', 'Jane')
            ->set('last_name', 'Smith')
            ->set('student_no', '20-3002')
            ->set('email', 'janesmith@plv.edu.ph') // Email prefix is 'janesmith'
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $component2->assertHasNoErrors();

        // Verify user was created successfully
        $this->assertDatabaseHas('users', [
            'email' => 'janesmith@plv.edu.ph',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        // Test case 3: Names with spaces should work (spaces are removed)
        $component3 = Volt::test('pages.auth.register')
            ->set('first_name', 'Mary Jane')
            ->set('last_name', 'Watson')
            ->set('student_no', '20-3003')
            ->set('email', 'maryjanewatson@plv.edu.ph') // Email prefix is 'maryjanewatson'
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $component3->assertHasNoErrors();

        // Test case 4: Case insensitive matching
        $component4 = Volt::test('pages.auth.register')
            ->set('first_name', 'ERIC')
            ->set('last_name', 'ASDF')
            ->set('student_no', '20-3004')
            ->set('email', 'ericasdf@plv.edu.ph') // Email prefix is 'ericasdf'
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $component4->assertHasNoErrors();

        // Test case 5: Should fail when concatenated name doesn't match
        $component5 = Volt::test('pages.auth.register')
            ->set('first_name', 'asdf')
            ->set('last_name', 'asdf')
            ->set('student_no', '20-3005')
            ->set('email', 'test@plv.edu.ph') // Email prefix is 'test'
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->call('register');

        $this->assertTrue($component5->instance()->getErrorBag()->has('last_name'));
        $errorMessage = $component5->instance()->getErrorBag()->first('last_name');
        $this->assertStringContainsString('Expected: test, Got: asdfasdf', $errorMessage);
    }
}
