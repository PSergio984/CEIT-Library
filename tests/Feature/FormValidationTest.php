<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Livewire\Pages\Admin\CreateAcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\Role;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FormValidationTest extends TestCase
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

    protected function seedRequiredData(): void
    {
        Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student', 'description' => 'Student']);
        Role::firstOrCreate(['name' => 'librarian'], ['display_name' => 'Librarian', 'description' => 'Librarian']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'description' => 'Admin']);
        Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin', 'description' => 'Super Admin']);

        ResearchAdviser::factory()->count(3)->create();
        TechnicalAdviser::factory()->count(3)->create();
        Dean::factory()->count(3)->create();
        Author::factory()->count(5)->create();
    }

    /** @test - TC081: Form Validation - Required Fields */
    #[Test]
    public function required_field_validation_works_on_forms()
    {
        $this->seedRequiredData();
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Attempt to create academic paper without required fields
        $component = Livewire::test(CreateAcademicPaper::class)
            ->set('form.title', '') // Empty title
            ->set('form.paper_type', '');

        $component->call('save');
        $component->assertHasErrors(['form.title', 'form.paper_type']);

        // Fill all required fields
        $component = Livewire::test(CreateAcademicPaper::class)
            ->set('form.title', 'Test Paper')
            ->set('form.publication_year', 2024)
            ->set('form.paper_type', 'Thesis')
            ->set('form.research_adviser_id', ResearchAdviser::first()->id)
            ->set('form.technical_adviser_id', TechnicalAdviser::first()->id)
            ->set('form.department', 'Information Technology')
            ->set('form.dean_id', Dean::first()->id)
            ->set('form.author_ids', [Author::first()->id])
            ->set('form.number_of_copies', 1);

        $component->call('save');
        $component->assertHasNoErrors();
    }

    // ============================================================================
    // AUTHENTICATION FORMS TESTS
    // ============================================================================

    /** @test */
    #[Test]
    public function register_form_validates_first_name_field()
    {
        $component = Volt::test('pages.auth.register');

        // Test empty first name
        $component
            ->set('first_name', '')
            ->set('last_name', 'Dela Cruz')
            ->set('email', 'juandelacruz@plv.edu.ph')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register');

        $component->assertHasErrors(['first_name']);

        // Test first name with numbers
        $component
            ->set('first_name', 'Juan123')
            ->call('register');

        $component->assertHasErrors(['first_name']);

        // Test first name too short (less than 2 chars)
        $component
            ->set('first_name', 'J')
            ->call('register');

        $component->assertHasErrors(['first_name']);
    }

    /** @test */
    #[Test]
    public function register_form_validates_last_name_field()
    {
        $component = Volt::test('pages.auth.register');

        // Test empty last name
        $component
            ->set('first_name', 'Juan')
            ->set('last_name', '')
            ->set('email', 'juandelacruz@plv.edu.ph')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register');

        $component->assertHasErrors(['last_name']);

        // Test last name with special characters
        $component
            ->set('last_name', 'Dela@Cruz')
            ->call('register');

        $component->assertHasErrors(['last_name']);
    }

    /** @test */
    #[Test]
    public function register_form_validates_email_domain()
    {
        $component = Volt::test('pages.auth.register');

        // Test invalid email domain
        $component
            ->set('first_name', 'Juan')
            ->set('last_name', 'Dela Cruz')
            ->set('email', 'juan@gmail.com')
            ->set('password', 'Password123!')
            ->set('password_confirmation', 'Password123!')
            ->call('register');

        $component->assertHasErrors(['email']);
    }

    /** @test */
    #[Test]
    public function register_form_validates_password_requirements()
    {
        $component = Volt::test('pages.auth.register');

        // Test password too short
        $component
            ->set('first_name', 'Juan')
            ->set('last_name', 'Dela Cruz')
            ->set('email', 'juantest@plv.edu.ph')
            ->set('password', 'Pass1!')
            ->set('password_confirmation', 'Pass1!')
            ->call('register');

        $component->assertHasErrors(['password']);

        // Test password without uppercase
        $component
            ->set('password', 'password123!')
            ->set('password_confirmation', 'password123!')
            ->call('register');

        $component->assertHasErrors(['password']);

        // Test password without number
        $component
            ->set('password', 'Password!')
            ->set('password_confirmation', 'Password!')
            ->call('register');

        $component->assertHasErrors(['password']);
    }

    /** @test */
    #[Test]
    public function login_form_validates_email_field()
    {
        $component = Volt::test('pages.auth.login');

        // Test empty email
        $component
            ->set('form.email', '')
            ->set('form.password', 'password')
            ->call('login');

        $component->assertHasErrors(['form.email']);

        // Test invalid email format
        $component
            ->set('form.email', 'notanemail')
            ->call('login');

        $component->assertHasErrors(['form.email']);
    }

    /** @test */
    #[Test]
    public function login_form_validates_password_field()
    {
        $component = Volt::test('pages.auth.login');

        // Test empty password
        $component
            ->set('form.email', 'test@plv.edu.ph')
            ->set('form.password', '')
            ->call('login');

        $component->assertHasErrors(['form.password']);
    }

    /** @test */
    #[Test]
    public function forgot_password_form_validates_email_field()
    {
        $component = Volt::test('pages.auth.forgot-password');

        // Test empty email
        $component
            ->set('email', '')
            ->call('sendPasswordResetLink');

        $component->assertHasErrors(['email']);

        // Test invalid PLV email
        $component
            ->set('email', 'test@gmail.com')
            ->call('sendPasswordResetLink');

        $component->assertHasErrors(['email']);
    }

    /** @test */
    #[Test]
    public function reset_password_form_validates_password_requirements()
    {
        $component = Volt::test('pages.auth.reset-password', [
            'token' => 'test-token',
        ]);

        // Test password too short
        $component
            ->set('email', 'test@plv.edu.ph')
            ->set('password', 'Pass1!')
            ->set('password_confirmation', 'Pass1!')
            ->call('resetPassword');

        $component->assertHasErrors(['password']);
    }

    // ============================================================================
    // PROFILE FORMS TESTS
    // ============================================================================

    /** @test */
    #[Test]
    public function profile_update_form_validates_first_name()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form');

        // Test empty first name
        $component
            ->set('first_name', '')
            ->set('last_name', 'Doe')
            ->call('updateProfileInformation');

        $component->assertHasErrors(['first_name']);

        // Test first name too short
        $component
            ->set('first_name', 'J')
            ->call('updateProfileInformation');

        $component->assertHasErrors(['first_name']);

        // Test first name with numbers
        $component
            ->set('first_name', 'John123')
            ->call('updateProfileInformation');

        $component->assertHasErrors(['first_name']);
    }

    /** @test */
    #[Test]
    public function profile_update_form_validates_last_name()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form');

        // Test empty last name
        $component
            ->set('first_name', 'John')
            ->set('last_name', '')
            ->call('updateProfileInformation');

        $component->assertHasErrors(['last_name']);

        // Test last name with special characters
        $component
            ->set('last_name', 'Doe@#$')
            ->call('updateProfileInformation');

        $component->assertHasErrors(['last_name']);
    }

    /** @test */
    #[Test]
    public function update_password_form_validates_current_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form');

        // Test empty current password
        $component
            ->set('current_password', '')
            ->set('password', 'NewPassword123!')
            ->set('password_confirmation', 'NewPassword123!')
            ->call('updatePassword');

        $component->assertHasErrors(['current_password']);
    }

    /** @test */
    #[Test]
    public function update_password_form_validates_new_password_requirements()
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form');

        // Test password too short
        $component
            ->set('current_password', 'OldPassword123!')
            ->set('password', 'New1!')
            ->set('password_confirmation', 'New1!')
            ->call('updatePassword');

        $component->assertHasErrors(['password']);
    }

    /** @test */
    #[Test]
    public function delete_user_form_validates_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123!'),
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form');

        // Test empty password
        $component
            ->set('password', '')
            ->call('deleteUser');

        $component->assertHasErrors(['password']);
    }

    /** @test */
    #[Test]
    public function confirm_password_form_validates_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123!'),
        ]);
        $this->actingAs($user);

        $component = Volt::test('pages.auth.confirm-password');

        // Test empty password
        $component
            ->set('password', '')
            ->call('confirmPassword');

        $component->assertHasErrors(['password']);
    }

    /** @test */
    #[Test]
    public function valid_profile_update_submits_successfully()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('first_name', 'Jane')
            ->set('last_name', 'Smith')
            ->call('updateProfileInformation');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
    }

    /** @test */
    #[Test]
    public function valid_password_update_submits_successfully()
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword123!'),
        ]);
        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'OldPassword123!')
            ->set('password', 'NewPassword123!')
            ->set('password_confirmation', 'NewPassword123!')
            ->call('updatePassword');

        $component->assertHasNoErrors();
    }

    /** @test - Security Hardening: HTML Tag Rejection */
    #[Test]
    public function borrow_transaction_form_rejects_html_tags_in_notes()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Pages\Admin\AdminBorrowTransactions::class)
            ->set('form.notes', '<script>alert("xss")</script>')
            ->call('saveTransaction')
            ->assertHasErrors(['form.notes']);
    }

    /** @test - Security Hardening: Control Character Rejection */
    #[Test]
    public function borrow_transaction_form_rejects_control_characters_in_notes()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Pages\Admin\AdminBorrowTransactions::class)
            ->set('form.notes', "Illegal\0Character")
            ->call('saveTransaction')
            ->assertHasErrors(['form.notes']);
    }

    /** @test - Security Hardening: Search Filter Protection */
    #[Test]
    public function admin_borrow_transactions_validates_search_query()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        Livewire::test(\App\Livewire\Pages\Admin\AdminBorrowTransactions::class)
            ->set('search', '<b>Bold Search</b>')
            ->assertHasErrors(['search']);
            
        Livewire::test(\App\Livewire\Pages\Admin\AdminBorrowTransactions::class)
            ->set('search', "Control\x01Char")
            ->assertHasErrors(['search']);
    }
}
