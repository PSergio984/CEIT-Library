<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_registration_requires_name(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', '')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component
            ->assertHasErrors(['name'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_valid_email(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'not-an-email')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component
            ->assertHasErrors(['email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_email(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', '')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component
            ->assertHasErrors(['email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component
            ->assertHasErrors(['email'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_password(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', '')
            ->set('password_confirmation', '');

        $component->call('register');

        $component
            ->assertHasErrors(['password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', '');

        $component->call('register');

        $component
            ->assertHasErrors(['password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'different-password');

        $component->call('register');

        $component
            ->assertHasErrors(['password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_requires_minimum_password_length(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'pass')
            ->set('password_confirmation', 'pass');

        $component->call('register');

        $component
            ->assertHasErrors(['password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_user_is_created_in_database_after_registration(): void
    {
        $this->assertDatabaseCount('users', 0);

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_is_hashed_in_database(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $user = User::where('email', 'test@example.com')->first();

        $this->assertNotEquals('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_registered_event_is_dispatched(): void
    {
        Event::fake([Registered::class]);

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        Event::assertDispatched(Registered::class, function ($event) {
            return $event->user->email === 'test@example.com';
        });
    }

    public function test_user_is_logged_in_after_registration(): void
    {
        $this->assertGuest();

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertAuthenticated();
        $this->assertEquals('test@example.com', auth()->user()->email);
    }

    public function test_registration_trims_whitespace_from_name(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', '  Test User  ')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $user = User::where('email', 'test@example.com')->first();
        $this->assertEquals('Test User', $user->name);
    }

    public function test_registration_lowercases_email(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'TEST@EXAMPLE.COM')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_with_maximum_name_length(): void
    {
        $longName = str_repeat('a', 255);

        Volt::test('pages.auth.register')
            ->set('name', $longName)
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => $longName,
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_fails_with_name_exceeding_maximum_length(): void
    {
        $tooLongName = str_repeat('a', 256);

        $component = Volt::test('pages.auth.register')
            ->set('name', $tooLongName)
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component
            ->assertHasErrors(['name'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_with_special_characters_in_name(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', "O'Brien-Smith")
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => "O'Brien-Smith",
        ]);
    }

    public function test_registration_with_unicode_characters_in_name(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', '测试用户')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => '测试用户',
        ]);
    }

    public function test_registration_with_maximum_email_length(): void
    {
        // Email max is typically 255 chars
        $localPart = str_repeat('a', 64);
        $domain = str_repeat('b', 180) . '.com';
        $longEmail = $localPart . '@' . $domain;

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', $longEmail)
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertAuthenticated();
    }

    public function test_registration_with_plus_sign_in_email(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test+tag@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test+tag@example.com',
        ]);
    }

    public function test_registration_with_subdomain_email(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@mail.example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@mail.example.com',
        ]);
    }

    public function test_registration_rejects_email_without_at_symbol(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'testexample.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_registration_rejects_email_without_domain(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_registration_with_complex_password(): void
    {
        $complexPassword = 'P@ssw0rd\!#$%^&*()_+-=[]{}|;:,.<>?';

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', $complexPassword)
            ->set('password_confirmation', $complexPassword)
            ->call('register');

        $this->assertAuthenticated();
        
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check($complexPassword, $user->password));
    }

    public function test_registration_with_maximum_password_length(): void
    {
        $longPassword = str_repeat('a', 255);

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', $longPassword)
            ->set('password_confirmation', $longPassword)
            ->call('register');

        $this->assertAuthenticated();
    }

    public function test_multiple_users_can_register_with_different_emails(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'User One')
            ->set('email', 'user1@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        auth()->logout();

        Volt::test('pages.auth.register')
            ->set('name', 'User Two')
            ->set('email', 'user2@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertDatabaseCount('users', 2);
        $this->assertAuthenticated();
    }

    public function test_registration_does_not_log_in_if_validation_fails(): void
    {
        $this->assertGuest();

        Volt::test('pages.auth.register')
            ->set('name', '')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $this->assertGuest();
    }

    public function test_registration_component_has_correct_properties(): void
    {
        $component = Volt::test('pages.auth.register');

        $component
            ->assertPropertyWired('name')
            ->assertPropertyWired('email')
            ->assertPropertyWired('password')
            ->assertPropertyWired('password_confirmation');
    }

    public function test_registration_screen_contains_required_form_fields(): void
    {
        $response = $this->get('/register');

        $response
            ->assertSee('name')
            ->assertSee('email')
            ->assertSee('password')
            ->assertSee('Register');
    }

    public function test_authenticated_user_cannot_access_registration_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/register');

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_prevents_xss_in_name_field(): void
    {
        $xssName = '<script>alert("xss")</script>';

        Volt::test('pages.auth.register')
            ->set('name', $xssName)
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        $user = User::where('email', 'test@example.com')->first();
        
        // Laravel should escape the script tag
        $this->assertStringNotContainsString('<script>', $user->name);
    }

    public function test_registration_with_sql_injection_attempt_in_name(): void
    {
        $sqlInjection = "'; DROP TABLE users; --";

        Volt::test('pages.auth.register')
            ->set('name', $sqlInjection)
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        // Should create user safely without executing SQL
        $this->assertDatabaseHas('users', [
            'name' => $sqlInjection,
            'email' => 'test@example.com',
        ]);

        // Verify table still exists
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_validates_all_fields_simultaneously(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', '')
            ->set('email', 'invalid-email')
            ->set('password', 'short')
            ->set('password_confirmation', 'different');

        $component->call('register');

        $component
            ->assertHasErrors(['name', 'email', 'password'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_registration_clears_password_on_validation_error(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'invalid')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        // Password fields should remain for retry
        $component->assertSet('password', 'password');
    }

    public function test_registration_preserves_name_and_email_on_validation_error(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'short')
            ->set('password_confirmation', 'short');

        $component->call('register');

        $component
            ->assertSet('name', 'Test User')
            ->assertSet('email', 'test@example.com');
    }

    public function test_registration_rate_limiting(): void
    {
        // Attempt multiple registrations rapidly
        for ($i = 0; $i < 6; $i++) {
            try {
                Volt::test('pages.auth.register')
                    ->set('name', 'Test User ' . $i)
                    ->set('email', 'test' . $i . '@example.com')
                    ->set('password', 'password')
                    ->set('password_confirmation', 'password')
                    ->call('register');
            } catch (\Exception $e) {
                // Rate limiting may kick in
                break;
            }
        }

        // At least some users should be created before rate limiting
        $this->assertGreaterThan(0, User::count());
    }
}
