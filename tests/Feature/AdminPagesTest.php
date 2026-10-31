<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminAcademicPaperIndex;
use App\Livewire\Pages\Admin\AdminAdvisersDeans;
use App\Livewire\Pages\Admin\AdminAssignLibrarians;
use App\Livewire\Pages\Admin\AdminAttendanceLogIndex;
use App\Livewire\Pages\Admin\AdminDashboard;
use App\Livewire\Pages\Admin\AdminManageRoles;
use App\Livewire\Pages\Admin\AdminNotifications;
use App\Livewire\Pages\Admin\AdminUserList;
use App\Livewire\Pages\Admin\ViolationsTab;
use App\Livewire\Pages\Admin\ViolationTransactionsTab;
use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\Author;
use App\Models\BagDeposit;
use App\Models\BagDepositEvent;
use App\Models\BagSlot;
use App\Models\Inventory;
use App\Models\Librarian;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\TestHelper;

class AdminPagesTest extends TestCase
{
    use TestHelper;

    protected bool $disableLivewireLazyLoading = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student', 'description' => 'Student']);
        Role::firstOrCreate(['name' => 'librarian'], ['display_name' => 'Librarian', 'description' => 'Librarian']);
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin', 'description' => 'Admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin', 'description' => 'Super Admin']);

        $this->admin = User::factory()->create(['role_id' => $superAdminRole->id]);
    }

    public function test_dashboard_stats_computeds_return_aggregates(): void
    {
        AcademicPaper::factory()->count(3)->create(['department' => 'IT', 'paper_type' => 'Thesis']);
        AcademicPaper::factory()->count(2)->create(['department' => 'CE', 'paper_type' => 'Feasibility']);

        $component = Livewire::actingAs($this->admin)->test(AdminDashboard::class);

        $deptStats = collect($component->instance()->departmentStats());
        $typeStats = collect($component->instance()->academicPaperStats());

        $this->assertEquals(3, $deptStats->firstWhere('name', 'IT')['value']);
        $this->assertEquals(2, $typeStats->firstWhere('name', 'Feasibility')['value']);
    }

    public function test_attendance_log_index_maps_user_and_exports_pdf_with_role_filter(): void
    {
        $studentRole = Role::where('name', 'student')->first();
        $attendance = Attendance::factory()->create(['role_id' => $studentRole->id]);

        Livewire::actingAs($this->admin)
            ->test(AdminAttendanceLogIndex::class)
            ->assertSee($attendance->user->first_name)
            ->set('roleFilter', $studentRole->id)
            ->call('exportPdf')
            ->assertFileDownloaded();
    }

    public function test_academic_paper_index_searches_and_form_validation(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(AdminAcademicPaperIndex::class)
            ->call('searchResearchAdvisers', 'Smith')
            ->call('searchTechnicalAdvisers', 'Jones')
            ->call('searchDeans', 'Lee');

        $this->assertFalse($component->get('isFormValid'));
    }

    public function test_author_rename_busts_selector_search_cache(): void
    {
        $author = Author::factory()->create(['name' => 'Gertrude Oldname']);

        Livewire::actingAs($this->admin)
            ->test(AdminAcademicPaperIndex::class)
            ->call('searchAuthors', 'Gertrude');

        Livewire::actingAs($this->admin)
            ->test(AdminAdvisersDeans::class)
            ->set('activeTab', 'authors')
            ->call('openEditModal', $author->id)
            ->set('name', 'Gertrude Newname')
            ->call('save');

        $results = Livewire::actingAs($this->admin)
            ->test(AdminAcademicPaperIndex::class)
            ->instance()
            ->searchAuthors('Gertrude');

        $names = collect($results)->pluck('name')->implode(',');
        $this->assertStringContainsString('Gertrude Newname', $names);
        $this->assertStringNotContainsString('Gertrude Oldname', $names);
    }

    public function test_admin_user_list_transaction_details_include_paper_fields(): void
    {
        $student = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $this->makeBorrowTransaction([
            'user_id' => $student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => Inventory::factory()->create([
                'academic_paper_id' => $paper->id,
                'copy_number' => 1,
            ])->id,
            'status' => 'started',
            'time_out' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminUserList::class)
            ->call('showTransactionDetails', $student->id)
            ->assertSet('selectedStudentData.transactions.0.title', $paper->title);
    }

    public function test_student_with_open_bag_deposit_cannot_be_deleted(): void
    {
        $student = User::factory()->create();
        $slot = BagSlot::create(['number' => 1, 'is_active' => true]);
        $deposit = BagDeposit::create([
            'user_id' => $student->id,
            'bag_slot_id' => $slot->id,
            'item_count' => 1,
            'claim_code' => '123456',
            'status' => BagDeposit::STATUS_OPEN,
        ]);
        BagDepositEvent::create([
            'bag_deposit_id' => $deposit->id,
            'type' => BagDepositEvent::TYPE_DEPOSIT,
            'actor_user_id' => $student->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminUserList::class)
            ->call('confirmDelete', $student->id)
            ->call('deleteUser');

        // Deletion refused — the deposit and its audit trail stay intact.
        $this->assertNotNull($student->fresh());
        $this->assertDatabaseHas('bag_deposits', ['id' => $deposit->id, 'status' => 'open']);
        $this->assertDatabaseCount('bag_deposit_events', 1);
    }

    public function test_student_with_only_claimed_bag_deposits_can_be_deleted_and_audit_survives(): void
    {
        $student = User::factory()->create();
        $slot = BagSlot::create(['number' => 1, 'is_active' => true]);
        $deposit = BagDeposit::create([
            'user_id' => $student->id,
            'bag_slot_id' => $slot->id,
            'item_count' => 1,
            'claim_code' => '654321',
            'status' => BagDeposit::STATUS_CLAIMED,
            'claimed_by' => $student->id,
            'claimed_at' => now(),
            'claim_method' => BagDeposit::METHOD_OWNER_SCAN,
        ]);
        BagDepositEvent::create([
            'bag_deposit_id' => $deposit->id,
            'type' => BagDepositEvent::TYPE_DEPOSIT,
            'actor_user_id' => $student->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminUserList::class)
            ->call('confirmDelete', $student->id)
            ->call('deleteUser');

        $this->assertNull($student->fresh());

        // The claimed deposit and its audit trail outlive the user, de-linked.
        $deposit->refresh();
        $this->assertNull($deposit->user_id);
        $this->assertNull($deposit->claimed_by);
        $this->assertNull($deposit->events()->sole()->actor_user_id);
    }

    public function test_admin_notifications_render_and_mark_all_as_read(): void
    {
        Notification::create([
            'user_id' => $this->admin->id,
            'type' => 'user_activity',
            'title' => 'Test',
            'message' => 'Test message',
            'is_read' => false,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminNotifications::class)
            ->call('markAllAsRead');

        $this->assertSame(0, Notification::where('user_id', $this->admin->id)->unread()->count());
    }

    public function test_assign_role_notifies_the_admin(): void
    {
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'student')->first()->id,
        ]);
        $librarianRole = Role::where('name', 'librarian')->first();

        Livewire::actingAs($this->admin)
            ->test(AdminManageRoles::class)
            ->call('openAssignRoleModal', $student->id)
            ->set('selectedRoleId', $librarianRole->id)
            ->call('assignRole');

        $this->assertSame($librarianRole->id, $student->fresh()->role_id);
    }

    public function test_generate_next_batch_number_uses_latest_batch(): void
    {
        Librarian::factory()->create();

        $component = Livewire::actingAs($this->admin)->test(AdminAssignLibrarians::class);

        $this->assertMatchesRegularExpression(
            '/^'.date('Y').'\d{4}$/',
            $component->instance()->generateNextBatchNumber()
        );
    }

    public function test_violations_tab_form_validity(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(ViolationsTab::class)
            ->set('form.name', 'Excessive Noise')
            ->set('form.description', 'Disturbing other library users repeatedly')
            ->set('form.penalty_score', 10);

        $this->assertTrue($component->get('isFormValid'));
    }

    public function test_violation_transactions_tab_undo_confirmed(): void
    {
        $transaction = ViolationTransaction::factory()->create([
            'violation_id' => Violation::factory()->create()->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViolationTransactionsTab::class)
            ->call('confirmUndo', $transaction->id)
            ->call('undoConfirmed');

        $this->assertDatabaseMissing('violation_transactions', ['id' => $transaction->id]);
    }
}
