<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminBorrowTransactions;
use App\Livewire\Pages\Student\Transaction;
use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Borrow Security Tests
 *
 * Tests security features of the QR code borrow/return system:
 * - Prevention of double-borrowing
 * - Enforcement that only the original borrower can return using their QR
 * - Student ability to retrieve return QR from transaction history
 */
class BorrowSecurityTest extends TestCase
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

    /**
     * Create a QR payload matching the expected format.
     */
    protected function createQrPayload(array $borrowData): string
    {
        $payload = ['p' => $borrowData];
        $encrypted = Crypt::encryptString(json_encode($payload));

        return json_encode(['encrypted' => $encrypted]);
    }

    /** @test - TC-BS-001: Cannot borrow a book that is already borrowed by another user */
    #[Test]
    public function cannot_borrow_already_borrowed_book(): void
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $borrower = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $attacker = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',  // Initially available
        ]);

        // First borrower successfully borrows the book
        BorrowTransaction::factory()->create([
            'user_id' => $borrower->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now(),
            'time_out' => null,
        ]);

        // Update inventory status to Unavailable (as happens after borrow)
        $inventory->update(['status' => 'Unavailable']);

        // Attacker tries to borrow the same book using their own QR
        $attackerQrData = [
            'inventory_id' => $inventory->id,
            'paper_id' => $paper->id,
            'requested_by' => $attacker->id,
        ];
        $qrContent = $this->createQrPayload($attackerQrData);

        $this->actingAs($admin);

        $component = Livewire::test(AdminBorrowTransactions::class);
        $result = $component->call('processScannedQr', $qrContent);

        // Should not open the confirm borrow modal
        $component->assertSet('showConfirmBorrowModal', false);

        // The response should indicate found = false (error occurred)
        // We verify by checking that the pending borrow data was NOT set
        $pendingData = $component->get('pendingBorrowData');
        $this->assertEmpty($pendingData);
    }

    /** @test - TC-BS-002: Double borrow attempt with stale inventory status is blocked */
    #[Test]
    public function double_borrow_blocked_even_with_stale_inventory_status(): void
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $borrower = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $attacker = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);

        // Simulate stale status: inventory says Available but transaction exists
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',  // Stale/incorrect status
        ]);

        // Active transaction exists (status inconsistency scenario)
        BorrowTransaction::factory()->create([
            'user_id' => $borrower->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now(),
            'time_out' => null,
        ]);

        // Attacker tries to borrow - the security check should catch this
        $attackerQrData = [
            'inventory_id' => $inventory->id,
            'paper_id' => $paper->id,
            'requested_by' => $attacker->id,
        ];
        $qrContent = $this->createQrPayload($attackerQrData);

        $this->actingAs($admin);

        $component = Livewire::test(AdminBorrowTransactions::class);
        $component->call('processScannedQr', $qrContent);

        // Should not open confirm modal due to security check
        $component->assertSet('showConfirmBorrowModal', false);

        // Verify no pending data was set
        $pendingData = $component->get('pendingBorrowData');
        $this->assertEmpty($pendingData);
    }

    /** @test - TC-BS-003: Only original borrower's QR can return the book */
    #[Test]
    public function only_original_borrower_qr_can_return_book(): void
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $originalBorrower = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'first_name' => 'Original',
            'last_name' => 'Borrower',
        ]);
        $attacker = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',  // Currently borrowed
        ]);

        // Original borrower has the book
        BorrowTransaction::factory()->create([
            'user_id' => $originalBorrower->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now()->subHours(2),
            'time_out' => null,
        ]);

        // Attacker copies/creates QR with their own user ID trying to return
        $attackerQrData = [
            'inventory_id' => $inventory->id,
            'paper_id' => $paper->id,
            'requested_by' => $attacker->id,  // Attacker's ID, not original borrower
        ];
        $qrContent = $this->createQrPayload($attackerQrData);

        $this->actingAs($admin);

        $component = Livewire::test(AdminBorrowTransactions::class);
        $result = $component->call('processScannedQr', $qrContent);

        // Verify the book was NOT returned
        $inventory->refresh();
        $this->assertEquals('Unavailable', $inventory->status);

        // Verify the transaction is still active
        $transaction = BorrowTransaction::where('inventory_id', $inventory->id)
            ->whereNull('time_out')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('started', $transaction->status);
    }

    /** @test - TC-BS-004: Original borrower CAN return with their QR */
    #[Test]
    public function original_borrower_can_return_with_their_qr(): void
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $borrower = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',  // Currently borrowed
        ]);

        // Borrower has the book
        BorrowTransaction::factory()->create([
            'user_id' => $borrower->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now()->subHours(2),
            'time_out' => null,
        ]);

        // Borrower uses THEIR OWN QR to return
        $borrowerQrData = [
            'inventory_id' => $inventory->id,
            'paper_id' => $paper->id,
            'requested_by' => $borrower->id,  // Original borrower's ID
        ];
        $qrContent = $this->createQrPayload($borrowerQrData);

        $this->actingAs($admin);

        $component = Livewire::test(AdminBorrowTransactions::class);
        $component->call('processScannedQr', $qrContent);

        // Verify the book WAS returned
        $inventory->refresh();
        $this->assertEquals('Available', $inventory->status);

        // Verify the transaction was completed
        $transaction = BorrowTransaction::where('inventory_id', $inventory->id)
            ->where('status', 'completed')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->time_out);
    }

    /** @test - TC-BS-005: Student can generate return QR from transaction history */
    #[Test]
    public function student_can_generate_return_qr_from_transaction_history(): void
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Return QR Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        // Student has an active borrow
        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now()->subHours(1),
            'time_out' => null,
        ]);

        $this->actingAs($student);

        $component = Livewire::test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        // Verify modal opened and QR was generated
        $component->assertSet('isReturnQrModalOpen', true);

        $qrDataUri = $component->get('returnQrCodeDataUri');
        $this->assertNotNull($qrDataUri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qrDataUri);

        $paperTitle = $component->get('returnQrPaperTitle');
        $this->assertEquals('Return QR Test Paper', $paperTitle);

        $returnTransactionId = $component->get('returnQrTransactionId');
        $this->assertEquals($transaction->id, $returnTransactionId);
    }

    /** @test - TC-BS-006: Return QR modal can be closed */
    #[Test]
    public function return_qr_modal_can_be_closed(): void
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now()->subHours(1),
            'time_out' => null,
        ]);

        $this->actingAs($student);

        $component = Livewire::test(Transaction::class)
            ->call('generateReturnQr', $transaction->id)
            ->assertSet('isReturnQrModalOpen', true)
            ->call('closeReturnQrModal')
            ->assertSet('isReturnQrModalOpen', false)
            ->assertSet('returnQrCodeDataUri', null)
            ->assertSet('returnQrPaperTitle', null)
            ->assertSet('returnQrTransactionId', null);
    }

    /** @test - TC-BS-007: Cannot generate return QR for another user's transaction */
    #[Test]
    public function cannot_generate_return_qr_for_another_users_transaction(): void
    {
        $student1 = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $student2 = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        // Transaction belongs to student1
        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $student1->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now()->subHours(1),
            'time_out' => null,
        ]);

        // Student2 tries to generate return QR for student1's transaction
        $this->actingAs($student2);

        $component = Livewire::test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        // Modal should NOT open
        $component->assertSet('isReturnQrModalOpen', false);

        // QR should NOT be generated
        $qrDataUri = $component->get('returnQrCodeDataUri');
        $this->assertNull($qrDataUri);
    }

    /** @test - TC-BS-008: Cannot generate return QR for completed transaction */
    #[Test]
    public function cannot_generate_return_qr_for_completed_transaction(): void
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',  // Already returned
        ]);

        // Completed transaction
        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'completed',
            'time_in' => now()->subHours(3),
            'time_out' => now()->subHours(1),
        ]);

        $this->actingAs($student);

        $component = Livewire::test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        // Modal should NOT open for completed transaction
        $component->assertSet('isReturnQrModalOpen', false);
        $this->assertNull($component->get('returnQrCodeDataUri'));
    }

    /** @test - TC-BS-009: Generated return QR works with admin scanner */
    #[Test]
    public function generated_return_qr_works_with_admin_scanner(): void
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Full Flow Test']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'started',
            'time_in' => now()->subHours(1),
            'time_out' => null,
        ]);

        // Student generates return QR from transaction history
        $this->actingAs($student);
        $studentComponent = Livewire::test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        // The QR contains the same data format that original borrow QR would have
        // Since we can't directly decode the SVG QR, we'll simulate the same payload

        // Build the expected return QR payload
        $returnQrData = [
            'inventory_id' => $inventory->id,
            'paper_id' => $paper->id,
            'requested_by' => $student->id,
        ];
        $qrContent = $this->createQrPayload($returnQrData);

        // Admin scans the return QR
        $this->actingAs($admin);
        $adminComponent = Livewire::test(AdminBorrowTransactions::class)
            ->call('processScannedQr', $qrContent);

        // Verify the book was returned
        $inventory->refresh();
        $this->assertEquals('Available', $inventory->status);

        $transaction->refresh();
        $this->assertEquals('completed', $transaction->status);
        $this->assertNotNull($transaction->time_out);
    }

    /** @test - TC-BS-010: Can generate return QR for overdue transaction */
    #[Test]
    public function can_generate_return_qr_for_overdue_transaction(): void
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $paper = AcademicPaper::factory()->create(['title' => 'Overdue Test Paper']);
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        // Overdue transaction
        $transaction = BorrowTransaction::factory()->create([
            'user_id' => $student->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => 'overdue',
            'time_in' => now()->subDays(3),
            'time_out' => null,
            'expires_at' => now()->subDays(2),
        ]);

        $this->actingAs($student);

        $component = Livewire::test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        // Should work for overdue transactions too
        $component->assertSet('isReturnQrModalOpen', true);
        $this->assertNotNull($component->get('returnQrCodeDataUri'));
        $this->assertEquals('Overdue Test Paper', $component->get('returnQrPaperTitle'));
    }
}
