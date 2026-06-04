<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminAssignLibrarians;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LibrarianBatchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $disableLivewireLazyLoading = true;

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

    /** @test - TC066: Librarian Batch - Create with 5 Students */
    #[Test]
    public function librarian_batch_requires_exactly_5_students()
    {
        $superAdmin = User::factory()->create(['role_id' => $this->getRoleId('super_admin')]);
        $this->actingAs($superAdmin);

        // Create 10 students
        $students = User::factory()->count(10)->create(['role_id' => $this->getRoleId('student')]);

        // Attempt to create batch with only 2 students
        $component = Livewire::test(AdminAssignLibrarians::class)
            ->set('newBatchNo', '20250001')
            ->set('selectedStudents', array_slice($students->pluck('id')->toArray(), 0, 2));

        $component->call('createBatch');
        $component->assertHasErrors();

        // Create batch with exactly 5 students
        $component = Livewire::test(AdminAssignLibrarians::class)
            ->set('newBatchNo', '20250002')
            ->set('selectedStudents', array_slice($students->pluck('id')->toArray(), 0, 5));

        $component->call('createBatch');
        $component->assertHasNoErrors();
    }

    /** @test - TC067: Librarian Batch - Assign to Date */
    #[Test]
    public function librarian_batch_can_be_assigned_to_specific_date()
    {
        $superAdmin = User::factory()->create(['role_id' => $this->getRoleId('super_admin')]);
        $this->actingAs($superAdmin);

        $librarians = Librarian::factory()->count(5)->create([
            'start_date' => null,
            'end_date' => null,
            'status' => 'inactive',
        ]);

        $batchNo = '20250001';
        foreach ($librarians as $lib) {
            $lib->update(['batch_no' => $batchNo]);
        }

        $librarian = $librarians->first();
        $today = date('Y-m-d');

        $component = Livewire::test(AdminAssignLibrarians::class)
            ->call('openEditModal', $batchNo)
            ->set('editingDateStart', $today)
            ->call('saveBatchAssignment');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('librarians', [
            'id' => $librarian->id,
            'status' => 'active',
        ]);
    }
}
