<?php

namespace Tests\Feature;

use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LibrarianBatchTest extends TestCase
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

    /** @test - TC066: Librarian Batch - Create with 5 Students */
    public function librarian_batch_requires_exactly_5_students()
    {
        $superAdmin = User::factory()->create(['role_id' => $this->getRoleId('super_admin')]);
        $this->actingAs($superAdmin);

        // Create 10 students
        $students = User::factory()->count(10)->create(['role_id' => $this->getRoleId('student')]);

        // Attempt to create batch with only 2 students
        $component = Volt::test('pages.admin.assign-librarians.create-batch')
            ->set('selectedStudents', array_slice($students->pluck('id')->toArray(), 0, 2));

        $component->call('createBatch');
        $component->assertHasErrors();

        // Create batch with exactly 5 students
        $component = Volt::test('pages.admin.assign-librarians.create-batch')
            ->set('selectedStudents', array_slice($students->pluck('id')->toArray(), 0, 5));

        $component->call('createBatch');
        $component->assertHasNoErrors();
    }

    /** @test - TC067: Librarian Batch - Assign to Date */
    public function librarian_batch_can_be_assigned_to_specific_date()
    {
        $superAdmin = User::factory()->create(['role_id' => $this->getRoleId('super_admin')]);
        $this->actingAs($superAdmin);

        $librarianUser = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);
        $librarian = Librarian::factory()->create([
            'user_id' => $librarianUser->id,
            'start_date' => null,
            'end_date' => null,
            'status' => 'inactive',
        ]);

        // Assign to a future weekday date (not Sunday)
        $futureDate = now()->next('Monday')->toDateString();

        $component = Volt::test('pages.admin.assign-librarians.edit-batch')
            ->set('librarianId', $librarian->id)
            ->set('form.start_date', $futureDate)
            ->set('form.end_date', now()->parse($futureDate)->addDay()->toDateString());

        $component->call('updateBatch');
        $component->assertHasNoErrors();

        $this->assertDatabaseHas('librarians', [
            'id' => $librarian->id,
            'status' => 'active',
        ]);
    }
}

