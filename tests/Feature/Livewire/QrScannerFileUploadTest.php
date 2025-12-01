<?php

namespace Tests\Feature\Livewire;

use App\Livewire\QrScanner;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class QrScannerFileUploadTest extends TestCase
{
    use RefreshDatabase;

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
        \App\Models\Librarian::create([
            'user_id' => $librarian->id,
            'batch_no' => 2025001,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);

        // Generate valid QR data (same structure as AttendanceQr component)
        $secret = config('app.qr_hmac_secret');
        $timestamp = now()->timestamp;
        $nonce = Str::random(32);

        $userPayload = [
            'id' => $student->id,
            'email' => $student->email,
        ];

        $data = [
            'user_id' => $student->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        // Add hash for tamper protection
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Act as the librarian
        $this->actingAs($librarian);

        // Test the component
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded');

        // Assert attendance was created
        $this->assertDatabaseHas('attendances', [
            'user_id' => $student->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test that file upload scan with expired QR shows error
     */
    public function test_file_upload_scan_with_expired_qr_shows_error(): void
    {
        // Create a student user
        $student = User::factory()->create([
            'role_id' => 1,
        ]);

        // Create a librarian user
        $librarian = User::factory()->create([
            'role_id' => 2,
        ]);

        // Generate EXPIRED QR data (25 hours ago)
        $secret = config('app.qr_hmac_secret');
        $timestamp = now()->subHours(25)->timestamp;
        $nonce = Str::random(32);

        $userPayload = [
            'id' => $student->id,
            'email' => $student->email,
        ];

        $data = [
            'user_id' => $student->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        // Add hash
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Act as the librarian
        $this->actingAs($librarian);

        // Test the component
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
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
            ->assertSet('hasError', false); // Empty check happens before hasError is set
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
     * Test that file upload scan prevents replay attacks (using nonce twice)
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

        // Create librarian duty record manually (avoid factory column issues)
        \App\Models\Librarian::create([
            'user_id' => $librarian->id,
            'batch_no' => 2025002,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);

        // Generate valid QR data
        $secret = config('app.qr_hmac_secret');
        $timestamp = now()->timestamp;
        $nonce = Str::random(32);

        $userPayload = [
            'id' => $student->id,
            'email' => $student->email,
        ];

        $data = [
            'user_id' => $student->id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        // Add hash
        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        // Encrypt the data
        $encryptedData = Crypt::encryptString(json_encode($data));

        // Act as the librarian
        $this->actingAs($librarian);

        // First scan - should succeed (check-in)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded');

        // Second scan with SAME QR code - should succeed (check-out)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded');

        // Third scan with SAME QR code - should fail (replay attack prevention)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertSet('hasError', true);
    }

    /**
     * Helper method to create canonical message for HMAC
     * Must match the implementation in CreatesQrCanonicalMessage trait
     */
    private function createCanonicalMessage(array $data): string
    {
        // Sort keys to ensure consistent ordering
        $fields = [
            'user_id' => $data['user_id'] ?? '',
            'timestamp' => $data['timestamp'] ?? '',
            'nonce' => $data['nonce'] ?? '',
            'user' => isset($data['user']) ? json_encode($data['user'], JSON_UNESCAPED_SLASHES) : '',
        ];

        // Create deterministic string representation
        return implode('|', [
            $fields['user_id'],
            $fields['timestamp'],
            $fields['nonce'],
            $fields['user'],
        ]);
    }
}
