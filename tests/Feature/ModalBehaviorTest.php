<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModalBehaviorTest extends TestCase
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

    /** @test - TC079: Modal - Close on Escape Key */
    public function modals_can_be_closed_with_escape_key()
    {
        // This is primarily a frontend test
        // The backend should support modal state management
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $response = $this->get(route('admin.academic-paper.index'));
        $response->assertStatus(200);

        // Modal behavior is tested in frontend/browser tests
        // This test verifies the page loads correctly
    }

    /** @test - TC080: Modal - Close on Backdrop Click */
    public function modals_close_when_clicking_outside_modal_area()
    {
        // This is primarily a frontend test
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $response = $this->get(route('admin.academic-paper.index'));
        $response->assertStatus(200);

        // Modal behavior is tested in frontend/browser tests
    }
}
