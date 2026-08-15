<?php

namespace Tests\Feature\Livewire;

use App\Livewire\QrScanner;
use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class QrScannerFileUploadTest extends TestCase
{
    use CreatesQrCanonicalMessage, RefreshDatabase;

    /**
     * Test that file upload scan with valid QR data successfully records attendance
     */
    public function test_file_upload_scan_with_valid_qr_records_attendance(): void
    {
        // Create a student user
        $student = User::factory()->create([
            'role_id' => 1, // student
        ]);

        // Create a librarian user
        $librarian = User::factory()->create([
            'role_id' => 2, // librarian
        ]);

        // Create librarian duty record manually (avoid factory column issues)
        Librarian::create([
            'user_id' => $librarian->id,
            'batch_no' => 2025001,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);

        // Generate valid QR data (v7 format)
        $qrJson = $this->generateValidQrData($student);

        // Act as the librarian
        $this->actingAs($librarian);

        // Test the component
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $qrJson)
            ->assertDispatched('attendanceRecorded');

        // Assert attendance was created
        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test that file upload scan with tampered hash shows error
     */
    public function test_file_upload_scan_with_tampered_hash_shows_error(): void
    {
        // Create a student user
        $student = User::factory()->create([
            'role_id' => 1,
        ]);

        // Create a librarian user
        $librarian = User::factory()->create([
            'role_id' => 2,
        ]);

        // Generate QR data with INVALID hash (tampered)
        $data = [
            'v' => 7,
            'user_id' => $student->id,
            'nonce' => Str::random(16),
            'timestamp' => time(),
            'hash' => 'invalid-tampered-hash-12345', // Wrong hash
        ];

        // Encrypt the data
        $encryptedData = Crypt::encryptString(json_encode($data));
        $qrJson = json_encode(['encrypted' => $encryptedData]);

        // Act as the librarian
        $this->actingAs($librarian);

        // Test the component
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $qrJson)
            ->assertSet('hasError', true);

        // Assert NO attendance was created
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $student->id,
        ]);
    }

    /**
     * Test that file upload scan with empty data shows error
     */
    public function test_file_upload_scan_with_empty_data_shows_error(): void
    {
        $librarian = User::factory()->create([
            'role_id' => 2,
        ]);

        $this->actingAs($librarian);

        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', '')
            ->assertSet('hasError', true);
    }

    /**
     * Test that file upload scan with invalid encrypted data shows error
     */
    public function test_file_upload_scan_with_invalid_encrypted_data_shows_error(): void
    {
        $librarian = User::factory()->create([
            'role_id' => 2,
        ]);

        $this->actingAs($librarian);

        // Test with completely invalid data
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', 'invalid-qr-data-12345')
            ->assertSet('hasError', true);
    }

    /**
     * Test that file upload scan prevents replay attacks (using nonce more than once)
     */
    public function test_file_upload_scan_prevents_replay_attacks(): void
    {
        // Create a student user
        $student = User::factory()->create([
            'role_id' => 1,
        ]);

        // Create a librarian user
        $librarian = User::factory()->create([
            'role_id' => 2,
        ]);

        // Create librarian duty record manually
        Librarian::create([
            'user_id' => $librarian->id,
            'batch_no' => 2025002,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);

        // Generate valid QR data (v7 format)
        $qrJson = $this->generateValidQrData($student);

        // Act as the librarian
        $this->actingAs($librarian);

        // First scan - should succeed (check-in)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $qrJson)
            ->assertDispatched('attendanceRecorded');

        // Second scan with SAME QR code - should fail (replay attack prevention via nonce reuse)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $qrJson)
            ->assertSet('hasError', true);
    }

    /**
     * Helper method to generate valid QR data in v7 format
     */
    private function generateValidQrData(User $user): string
    {
        $secret = config('app.qr_hmac_secret');
        $data = [
            'v' => 7,
            'user_id' => $user->id,
            'nonce' => Str::random(16),
            'timestamp' => time(),
        ];

        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        $encryptedData = Crypt::encryptString(json_encode($data));

        return json_encode(['encrypted' => $encryptedData]);
    }
}
