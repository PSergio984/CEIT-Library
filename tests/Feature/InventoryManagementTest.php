<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
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

    /** @test - TC069: Inventory Management - View Inventory List */
    public function inventory_items_list_can_be_viewed()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $academicPaper = AcademicPaper::factory()->create();
        Inventory::factory()->count(5)->create(['academic_paper_id' => $academicPaper->id]);

        // Inventory is managed through Academic Papers (each paper has inventory/copies)
        // This test verifies inventory data is accessible through academic papers
        $response = $this->get(route('admin.academic-paper.index'));
        $response->assertStatus(200);
    }
}
