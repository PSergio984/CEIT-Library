<?php

namespace Tests\Feature\Security;

use App\Livewire\Pages\Admin\AdminManageRoles;
use App\Livewire\Pages\Admin\AdminUserList;
use App\Livewire\QrScanner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FrontendSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $student;

    protected $librarian;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::firstOrCreate(['name' => 'librarian'], ['display_name' => 'Librarian']);
        Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->first()->id,
        ]);

        $this->student = User::factory()->create([
            'role_id' => Role::where('name', 'student')->first()->id,
        ]);

        $this->librarian = User::factory()->create([
            'role_id' => Role::where('name', 'librarian')->first()->id,
        ]);
    }

    /** @test */
    public function test_unauthorized_user_cannot_assign_roles()
    {
        Livewire::actingAs($this->student)
            ->test(AdminManageRoles::class)
            ->call('assignRole')
            ->assertForbidden(); // Should fail until redundant auth is added
    }

    /** @test */
    public function test_unauthorized_user_cannot_delete_users()
    {
        $userToDelete = User::factory()->create();

        Livewire::actingAs($this->librarian)
            ->test(AdminUserList::class)
            ->set('selectedStudentId', $userToDelete->id)
            ->call('deleteUser')
            ->assertForbidden(); // Should fail until redundant auth is added
    }

    /** @test */
    public function test_search_input_validation_prevents_excessive_length()
    {
        $longSearch = str_repeat('a', 101);

        Livewire::actingAs($this->admin)
            ->test(AdminUserList::class)
            ->set('search', $longSearch)
            ->assertHasErrors(['search' => 'max']);
    }

    /** @test */
    public function test_unauthorized_user_cannot_handle_scan()
    {
        Livewire::actingAs($this->student)
            ->test(QrScanner::class)
            ->call('handleScan', 'some-data')
            ->assertForbidden();
    }
}
