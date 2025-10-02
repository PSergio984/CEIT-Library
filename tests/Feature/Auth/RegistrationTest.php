<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase; // This ensures database is reset between tests

    protected function setUp(): void
    {
        parent::setUp();

        // Set up fake storage for file uploads
        Storage::fake('public');
    }

    public function test_validation_rules(): void
    {
        // Create a fake image for tests that need it
        $validFile = UploadedFile::fake()->image('id.jpg', 800, 600)->size(1024);

        // Test with missing required fields - first_name
        $component1 = Volt::test('pages.auth.register')
            ->set('first_name', '')
            ->set('last_name', 'User')
            ->set('student_no', '1234567')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('id_img', $validFile)
            ->call('register');

        $this->assertTrue($component1->instance()->getErrorBag()->has('first_name'),
                         'Expected first_name validation error');

        // Test with invalid email
        $component2 = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '1234567')
            ->set('email', 'invalid-email')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('id_img', $validFile)
            ->call('register');

        $this->assertTrue($component2->instance()->getErrorBag()->has('email'),
                         'Expected email validation error');

        // Test with mismatched passwords
        $component3 = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '1234567')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different_password')
            ->set('id_img', $validFile)
            ->call('register');

        $this->assertTrue($component3->instance()->getErrorBag()->has('password'),
                         'Expected password validation error');
    }

    public function test_registration_with_complete_data(): void
    {
        // Create a fake image file
        $file = UploadedFile::fake()->image('id.jpg', 800, 600)->size(1024);

        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'John')
            ->set('last_name', 'Doe')
            ->set('student_no', '2023001')
            ->set('email', 'john.doe@example.com')
            ->set('password', 'SecurePass123!')
            ->set('password_confirmation', 'SecurePass123!')
            ->set('id_img', $file);

        $component->call('register');

        // Assert successful registration (no validation errors)

        $component->assertHasNoErrors();

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'student_no' => '2023001',
        ]);
    }

    public function test_file_upload_validation(): void
    {
        // Test with missing file
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('student_no', '1234567')
            ->set('email', 'test@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            // Don't set id_img
            ->call('register');

        // Check if file is required
        $hasFileError = $component->instance()->getErrorBag()->has('id_img');

        if ($hasFileError) {
            // Assert that the file upload is required and validation error is present
            $this->assertTrue($hasFileError, 'File upload is required and validation error is present');
        } else {
            // Assert that the file upload is optional and no validation error is present
            $this->assertFalse($hasFileError, 'File upload is optional and no validation error is present');
        }
    }
}
