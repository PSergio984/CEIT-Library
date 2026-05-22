<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Livewire\QrScanner;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
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
    #[Test]
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

        // Test invalid QR code scan via file upload
        $component = Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', 'invalid-qr-code-data');

        // Verify error is shown inline via hasError property
        $component->assertSet('hasError', true);
    }

    /** @test - TC030: QR Scanner - v6 Optimized Payload Validation */
    #[Test]
    public function qr_scanner_validates_v6_optimized_payload()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        $librarian = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);
        Librarian::factory()->create([
            'user_id' => $librarian->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        // Generate a v6-style payload
        $nonce = Str::random(16);
        $data = [
            'v' => 6,
            'user_id' => $student->id,
            'nonce' => $nonce,
        ];

        // Access the private/protected methods via a wrapper or by reflection
        // For testing, we'll manually recreate the HMAC logic
        $secret = config('app.qr_hmac_secret');
        if (! $secret) {
            config(['app.qr_hmac_secret' => 'test-secret-at-least-16-chars']);
            $secret = 'test-secret-at-least-16-chars';
        }

        // Canonical message: user_id|nonce
        $canonicalMessage = $student->id.'|'.$nonce;
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        $encryptedData = Crypt::encryptString(json_encode($data));

        $this->actingAs($librarian);

        // Test valid v6 QR code scan
        Livewire::test(QrScanner::class)
            ->call('handleScan', $encryptedData)
            ->assertHasNoErrors()
            ->assertDispatched('attendanceRecorded');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'status' => 'active',
        ]);
    }
}
