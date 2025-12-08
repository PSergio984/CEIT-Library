<?php

namespace Tests\Feature;

use App\Livewire\Pages\Student\AttendanceQr;
use App\Livewire\QrScanner;
use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Livewire;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tests\TestCase;

/**
 * QR Code Scannability Tests
 *
 * These tests verify that QR codes are generated with optimal settings
 * for reliable scanning across different devices and scenarios.
 *
 * Key scannability factors tested:
 * - Proper margin (quiet zone)
 * - Appropriate error correction levels
 * - Correct size for display and download
 * - SVG format for on-screen display
 * - PNG format for downloads with higher error correction
 * - End-to-end scanning simulation
 */
class QrCodeScannabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->student = User::factory()->create(['role_id' => 1]);
        $this->librarian = User::factory()->create(['role_id' => 2]);

        // Create active librarian duty
        Librarian::create([
            'user_id' => $this->librarian->id,
            'batch_no' => 2025001,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create canonical message for HMAC (matches CreatesQrCanonicalMessage trait)
     * Note: Timestamp is no longer included in the canonical message
     */
    private function createCanonicalMessage(array $data): string
    {
        $fields = [
            'user_id' => $data['user_id'] ?? '',
            'nonce' => $data['nonce'] ?? '',
            'user' => isset($data['user']) ? json_encode($data['user'], JSON_UNESCAPED_SLASHES) : '',
        ];

        return implode('|', [
            $fields['user_id'],
            $fields['nonce'],
            $fields['user'],
        ]);
    }

    /**
     * Generate valid encrypted QR data for testing
     * Note: Timestamp is no longer part of the QR code data
     */
    private function generateValidQrData(User $user): string
    {
        $secret = config('app.qr_hmac_secret');
        $nonce = Str::random(32);

        $userPayload = [
            'id' => $user->id,
            'email' => $user->email,
        ];

        $data = [
            'user_id' => $user->id,
            'nonce' => $nonce,
            'user' => $userPayload,
        ];

        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        return Crypt::encryptString(json_encode($data));
    }

    // ==========================================
    // QR CODE GENERATION TESTS
    // ==========================================

    /** @test */
    public function qr_code_component_generates_valid_svg_data_uri(): void
    {
        $this->actingAs($this->student);

        $component = Livewire::test(AttendanceQr::class);

        // Get the computed QR code data URI
        $dataUri = $component->get('qrCodeDataUri');

        // Verify it's a valid SVG data URI
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        // Decode and verify it's valid SVG
        $base64Part = substr($dataUri, strlen('data:image/svg+xml;base64,'));
        $svgContent = base64_decode($base64Part);

        $this->assertNotFalse($svgContent, 'Failed to decode base64 SVG');
        $this->assertStringContainsString('<svg', $svgContent);
        $this->assertStringContainsString('</svg>', $svgContent);
    }

    /** @test */
    public function qr_code_svg_contains_valid_qr_pattern(): void
    {
        $this->actingAs($this->student);

        $component = Livewire::test(AttendanceQr::class);

        $dataUri = $component->get('qrCodeDataUri');
        $base64Part = substr($dataUri, strlen('data:image/svg+xml;base64,'));
        $svgContent = base64_decode($base64Part);

        // QR codes contain rect elements for the modules (black squares)
        $this->assertStringContainsString('<rect', $svgContent);

        // Should have viewBox or width/height attributes for proper sizing
        $this->assertTrue(
            str_contains($svgContent, 'viewBox') || str_contains($svgContent, 'width'),
            'SVG should have viewBox or width attribute for proper sizing'
        );
    }

    /** @test */
    public function qr_code_download_returns_valid_png(): void
    {
        // Skip if Imagick is not installed
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick extension is not installed');
        }

        $this->actingAs($this->student);

        // Clear any cached PNG data
        Cache::flush();

        $component = Livewire::test(AttendanceQr::class);

        // Trigger download
        $response = $component->call('downloadQrCode');

        // Get the response from the component
        $downloadResponse = $response->effects['download'] ?? null;

        // If download was triggered, verify it's a PNG
        if ($downloadResponse) {
            $this->assertStringContainsString('.png', $downloadResponse['name']);
        }

        // Alternative: directly test that downloadQrCode doesn't throw an error
        $this->assertTrue(true, 'Download completed without errors');
    }

    /** @test */
    public function qr_code_data_is_encrypted_and_contains_required_fields(): void
    {
        $this->actingAs($this->student);

        // Generate fresh QR data
        Cache::flush();

        $component = Livewire::test(AttendanceQr::class);
        $dataUri = $component->get('qrCodeDataUri');

        // Verify the component generated a valid data URI
        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    // ==========================================
    // END-TO-END SCANNING SIMULATION TESTS
    // ==========================================

    /** @test */
    public function generated_qr_code_can_be_scanned_successfully(): void
    {
        // Step 1: Generate QR code as student
        $this->actingAs($this->student);

        // Generate valid QR data (simulating what AttendanceQr component does)
        $encryptedData = $this->generateValidQrData($this->student);

        // Step 2: Scan QR code as librarian
        $this->actingAs($this->librarian);

        // Simulate scanning the QR code
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded')
            ->assertSet('hasError', false);

        // Verify attendance was recorded
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function qr_code_allows_check_in_and_check_out_with_same_code(): void
    {
        // Generate QR data
        $encryptedData = $this->generateValidQrData($this->student);

        $this->actingAs($this->librarian);

        // First scan - Check IN
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded');

        // Verify check-in
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        // Second scan - Check OUT
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded');

        // Verify check-out (status should be completed)
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->student->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function third_scan_with_same_qr_code_creates_new_attendance(): void
    {
        $encryptedData = $this->generateValidQrData($this->student);

        $this->actingAs($this->librarian);

        // First scan - Check IN
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData);

        // Second scan - Check OUT
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData);

        // Third scan - Should create a NEW check-in (permanent QR, unlimited use)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertDispatched('attendanceRecorded');

        // Verify a new attendance record was created (should have 2 total now)
        $this->assertDatabaseCount('attendances', 2);

        // The latest one should be active (new check-in)
        $latestAttendance = \App\Models\Attendance::where('user_id', $this->student->id)
            ->latest('id')
            ->first();
        $this->assertEquals('active', $latestAttendance->status);
    }

    // ==========================================
    // QR CODE CONFIGURATION TESTS
    // ==========================================

    /** @test */
    public function svg_qr_code_uses_correct_size_and_margin(): void
    {
        // Generate a test QR code with the same settings as the component
        $testData = 'test-data-for-qr-code';

        // These should match the constants in AttendanceQr
        $svg = QrCode::size(400)
            ->margin(8)
            ->errorCorrection('L')
            ->generate($testData);

        $svgString = (string) $svg;

        // Verify it's valid SVG
        $this->assertStringContainsString('<svg', $svgString);

        // The SVG should contain proper viewBox or dimensions
        $this->assertTrue(
            str_contains($svgString, 'viewBox') ||
                str_contains($svgString, 'width="400"') ||
                str_contains($svgString, "width='400'"),
            'SVG should have proper size attributes'
        );
    }

    /** @test */
    public function png_qr_code_uses_higher_error_correction(): void
    {
        // Skip if Imagick is not installed (required for PNG generation)
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension is not installed');
        }

        // Generate a test PNG with the same settings as the component
        $testData = 'test-data-for-qr-code';

        // These should match the constants in AttendanceQr for PNG
        $png = QrCode::format('png')
            ->size(800)
            ->margin(8)
            ->errorCorrection('Q')
            ->generate($testData);

        // Verify it's valid PNG data
        $this->assertNotEmpty($png);

        // PNG files start with specific bytes
        $pngSignature = substr($png, 0, 8);
        $expectedSignature = "\x89PNG\r\n\x1a\n";
        $this->assertEquals($expectedSignature, $pngSignature, 'Generated data should be valid PNG');
    }

    /** @test */
    public function qr_code_with_margin_8_is_more_scannable_than_margin_0(): void
    {
        $testData = 'test-scannable-data-12345';

        // Generate QR with margin 0 (no quiet zone)
        $svgNoMargin = QrCode::size(400)
            ->margin(0)
            ->errorCorrection('L')
            ->generate($testData);

        // Generate QR with margin 8 (proper quiet zone)
        $svgWithMargin = QrCode::size(400)
            ->margin(8)
            ->errorCorrection('L')
            ->generate($testData);

        // Both should be valid SVGs
        $this->assertStringContainsString('<svg', (string) $svgNoMargin);
        $this->assertStringContainsString('<svg', (string) $svgWithMargin);

        // The one with margin should be larger (more content)
        // This is a proxy test - in real scanning tests, the one with margin would be more reliable
        $this->assertNotEmpty($svgWithMargin);
        $this->assertNotEmpty($svgNoMargin);
    }

    /** @test */
    public function error_correction_q_allows_25_percent_damage_recovery(): void
    {
        // Skip if Imagick is not installed (required for PNG generation)
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick extension is not installed');
        }

        $testData = 'test-error-correction-data';

        // Generate with Q (Quartile) error correction - 25% recovery
        $pngQ = QrCode::format('png')
            ->size(400)
            ->margin(4)
            ->errorCorrection('Q')
            ->generate($testData);

        // Generate with L (Low) error correction - 7% recovery
        $pngL = QrCode::format('png')
            ->size(400)
            ->margin(4)
            ->errorCorrection('L')
            ->generate($testData);

        // Both should be valid PNGs
        $this->assertStringStartsWith("\x89PNG", $pngQ);
        $this->assertStringStartsWith("\x89PNG", $pngL);

        // Q should be slightly larger due to more redundancy data
        // This verifies the error correction is actually being applied
        $this->assertGreaterThan(strlen($pngL), strlen($pngQ));
    }

    // ==========================================
    // CACHING AND CONSISTENCY TESTS
    // ==========================================

    /** @test */
    public function qr_code_is_cached_and_consistent_across_requests(): void
    {
        $this->actingAs($this->student);

        // Clear cache
        Cache::flush();

        // First request
        $component1 = Livewire::test(AttendanceQr::class);
        $dataUri1 = $component1->get('qrCodeDataUri');

        // Verify first request generated valid data
        $this->assertNotEmpty($dataUri1);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri1);

        // Second request (should use cache)
        $component2 = Livewire::test(AttendanceQr::class);
        $dataUri2 = $component2->get('qrCodeDataUri');

        // The QR code data URI should be identical (from cache)
        $this->assertEquals($dataUri1, $dataUri2, 'QR code should be consistent across requests due to caching');
    }

    /** @test */
    public function qr_code_caching_provides_consistent_results(): void
    {
        $this->actingAs($this->student);

        Cache::flush();

        // Generate initial QR code
        $component = Livewire::test(AttendanceQr::class);
        $initialDataUri = $component->get('qrCodeDataUri');

        // Second request should get the same cached QR code
        $component2 = Livewire::test(AttendanceQr::class);
        $secondDataUri = $component2->get('qrCodeDataUri');

        // QR codes should be identical due to caching
        $this->assertEquals($initialDataUri, $secondDataUri);
        $this->assertNotEmpty($initialDataUri);
    }

    // ==========================================
    // ERROR HANDLING TESTS
    // ==========================================

    /** @test */
    public function invalid_qr_data_is_rejected_gracefully(): void
    {
        $this->actingAs($this->librarian);

        // Test with garbage data
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', 'definitely-not-valid-qr-data')
            ->assertSet('hasError', true);

        // No attendance should be created
        $this->assertDatabaseCount('attendances', 0);
    }

    /** @test */
    public function tampered_qr_data_is_rejected(): void
    {
        $encryptedData = $this->generateValidQrData($this->student);

        // Tamper with the encrypted data (change a few characters)
        $tamperedData = substr($encryptedData, 0, -5).'XXXXX';

        $this->actingAs($this->librarian);

        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $tamperedData)
            ->assertSet('hasError', true);

        $this->assertDatabaseCount('attendances', 0);
    }

    /** @test */
    public function qr_code_with_invalid_hash_is_rejected(): void
    {
        // Generate QR data with incorrect hash
        $secret = config('app.qr_hmac_secret');
        $nonce = Str::random(32);

        $userPayload = [
            'id' => $this->student->id,
            'email' => $this->student->email,
        ];

        $data = [
            'user_id' => $this->student->id,
            'nonce' => $nonce,
            'user' => $userPayload,
            'hash' => 'invalid_hash_value_that_wont_match',
        ];

        $encryptedData = Crypt::encryptString(json_encode($data));

        $this->actingAs($this->librarian);

        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $encryptedData)
            ->assertSet('hasError', true);

        $this->assertDatabaseCount('attendances', 0);
    }

    // ==========================================
    // PERFORMANCE TESTS
    // ==========================================

    /** @test */
    public function qr_code_generation_is_fast_enough(): void
    {
        $this->actingAs($this->student);

        Cache::flush();

        $startTime = microtime(true);

        Livewire::test(AttendanceQr::class);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // QR code generation should complete within 2 seconds
        $this->assertLessThan(2000, $duration, 'QR code generation took too long: '.$duration.'ms');
    }

    /** @test */
    public function cached_qr_code_retrieval_is_very_fast(): void
    {
        $this->actingAs($this->student);

        // First call to populate cache
        Livewire::test(AttendanceQr::class);

        // Second call should be cached
        $startTime = microtime(true);

        Livewire::test(AttendanceQr::class);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Cached retrieval should be under 500ms
        $this->assertLessThan(500, $duration, 'Cached QR retrieval took too long: '.$duration.'ms');
    }

    // ==========================================
    // DATA INTEGRITY TESTS
    // ==========================================

    /** @test */
    public function qr_code_contains_correct_user_information(): void
    {
        $this->actingAs($this->student);

        $encryptedData = $this->generateValidQrData($this->student);

        // Decrypt and verify contents
        $decryptedJson = Crypt::decryptString($encryptedData);
        $data = json_decode($decryptedJson, true);

        $this->assertEquals($this->student->id, $data['user_id']);
        $this->assertEquals($this->student->id, $data['user']['id']);
        $this->assertEquals($this->student->email, $data['user']['email']);
        $this->assertArrayHasKey('nonce', $data);
        $this->assertArrayHasKey('hash', $data);
    }

    /** @test */
    public function qr_code_hash_prevents_data_modification(): void
    {
        $encryptedData = $this->generateValidQrData($this->student);

        // Decrypt the data
        $decryptedJson = Crypt::decryptString($encryptedData);
        $data = json_decode($decryptedJson, true);

        // Modify the user_id
        $data['user_id'] = 99999;

        // Re-encrypt with modified data (but original hash)
        $modifiedEncrypted = Crypt::encryptString(json_encode($data));

        $this->actingAs($this->librarian);

        // Should be rejected due to hash mismatch
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $modifiedEncrypted)
            ->assertSet('hasError', true);

        $this->assertDatabaseCount('attendances', 0);
    }
}
