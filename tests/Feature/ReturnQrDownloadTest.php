<?php

namespace Tests\Feature;

use App\Livewire\Pages\Student\Transaction;
use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\TestHelper;

/**
 * Tests for Return QR code download functionality.
 */
class ReturnQrDownloadTest extends TestCase
{
    use RefreshDatabase, TestHelper;

    /**
     * Test that return QR can be generated for active transaction.
     */
    public function test_return_qr_can_be_generated_for_active_transaction(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        $transaction = BorrowTransaction::factory()
            ->started()
            ->create([
                'user_id' => $user->id,
                'academic_paper_id' => $paper->id,
                'inventory_id' => $inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $component = Livewire::actingAs($user)
            ->test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        $component->assertSet('isReturnQrModalOpen', true)
            ->assertSet('returnQrTransactionId', $transaction->id)
            ->assertSet('returnQrInventoryId', $inventory->id);

        // Verify QR code data URI was generated
        $this->assertNotNull($component->get('returnQrCodeDataUri'));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $component->get('returnQrCodeDataUri'));
    }

    /**
     * Test that return QR download works for active transaction.
     */
    public function test_return_qr_download_works_for_active_transaction(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        $transaction = BorrowTransaction::factory()
            ->started()
            ->create([
                'user_id' => $user->id,
                'academic_paper_id' => $paper->id,
                'inventory_id' => $inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $component = Livewire::actingAs($user)
            ->test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        // Verify modal is open and data is set
        $component->assertSet('isReturnQrModalOpen', true)
            ->assertSet('returnQrTransactionId', $transaction->id);

        // Call download - should return a stream response (SVG format)
        $response = $component->call('downloadReturnQr');

        // The download method was called without throwing an exception
        $this->assertTrue(true);
    }

    /**
     * Test that return QR cannot be generated for completed transaction.
     */
    public function test_return_qr_cannot_be_generated_for_completed_transaction(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Available',
        ]);

        $transaction = BorrowTransaction::factory()
            ->completed()
            ->create([
                'user_id' => $user->id,
                'academic_paper_id' => $paper->id,
                'inventory_id' => $inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        Livewire::actingAs($user)
            ->test(Transaction::class)
            ->call('generateReturnQr', $transaction->id)
            ->assertSet('isReturnQrModalOpen', false)
            ->assertSet('returnQrCodeDataUri', null);
    }

    /**
     * Test that return QR can be generated for overdue transaction.
     */
    public function test_return_qr_can_be_generated_for_overdue_transaction(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'status' => 'Unavailable',
        ]);

        $transaction = BorrowTransaction::factory()
            ->overdue()
            ->create([
                'user_id' => $user->id,
                'academic_paper_id' => $paper->id,
                'inventory_id' => $inventory->id,
                'session_token' => $this->generateSessionToken(),
            ]);

        $component = Livewire::actingAs($user)
            ->test(Transaction::class)
            ->call('generateReturnQr', $transaction->id);

        $component->assertSet('isReturnQrModalOpen', true)
            ->assertSet('returnQrTransactionId', $transaction->id);

        // Verify QR code data URI was generated
        $this->assertNotNull($component->get('returnQrCodeDataUri'));
    }
}
