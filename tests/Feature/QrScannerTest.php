<?php

namespace Tests\Feature;

use App\Livewire\QrScanner;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QrScannerTest extends TestCase
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

    /** @test - TC029: QR Scanner - Error Handling */
    public function qr_scanner_shows_inline_errors_instead_of_modal_alerts()
    {
        $librarianUser = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);
        Librarian::factory()->create([
            'user_id' => $librarianUser->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->actingAs($librarianUser);

        // Test invalid QR code scan via file upload (processScannedData handles the logic)
        $component = Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', 'invalid-qr-code-data');

        // Verify error is shown inline via hasError property (not modal)
        $component->assertSet('hasError', true);
        // The error should be displayed in a dismissible panel within the scanner
    }
}
