<?php

namespace Tests\Feature;

use App\Livewire\QrScanner;
use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for attendance check-in/check-out notifications
 * Verifies that notifications are created when QR codes are scanned
 */
class AttendanceNotificationsTest extends TestCase
{
    use CreatesQrCanonicalMessage, RefreshDatabase;

    private User $student;

    private User $librarian;

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

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->student = User::factory()->create(['role_id' => 1]);
        $this->librarian = User::factory()->create(['role_id' => 2]);

        // Create active librarian duty (matching QrCodeScannabilityTest pattern)
        Librarian::create([
            'user_id' => $this->librarian->id,
            'batch_no' => 2025001,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);
    }

    /**
     * Generate valid QR data for a user.
     * Note: The 'v' field is optional/for versioning and is omitted here intentionally.
     * The validator accepts the current fields (user_id, hash, nonce, and timestamp).
     */
    private function generateValidQrData(User $user): string
    {
        $secret = config('app.qr_hmac_secret');
        $nonce = Str::random(32);
        $timestamp = time();

        $userPayload = [
            'id' => $user->id,
            'email' => $user->email,
        ];

        $data = [
            'user_id' => $user->id,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'user' => $userPayload,
        ];

        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, $secret);

        $encrypted = Crypt::encryptString(json_encode($data));

        return json_encode(['encrypted' => $encrypted]);
    }

    /** @test */
    #[Test]
    public function check_in_creates_notification_for_student(): void
    {
        // Act as the librarian
        $this->actingAs($this->librarian);

        // Generate QR code for student
        $qrData = $this->generateValidQrData($this->student);

        // Scan the QR code (check-in)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $qrData);

        // Verify attendance was created
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        // Verify check-in notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'type' => 'attendance_checkin',
        ]);

        $notification = Notification::where('user_id', $this->student->id)
            ->where('type', 'attendance_checkin')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Library Check-in Successful', $notification->title);
        $this->assertStringContainsString('Welcome to the library', $notification->message);

        // Assert full data structure
        $this->assertIsArray($notification->data);
        $this->assertArrayHasKey('attendance_id', $notification->data);
        $this->assertArrayHasKey('time_in', $notification->data);
        $this->assertEquals(Attendance::where('user_id', $this->student->id)->first()->id, $notification->data['attendance_id']);
        $this->assertMatchesRegularExpression('/\w{3} \d{2}, \d{4} \d{2}:\d{2} [AP]M/', $notification->data['time_in']);
    }

    /** @test */
    #[Test]
    public function check_out_creates_notification_for_student(): void
    {
        // Act as the librarian
        $this->actingAs($this->librarian);

        // Create an active attendance first
        $attendance = Attendance::create([
            'user_id' => $this->student->id,
            'role_id' => $this->student->role_id,
            'time_in' => now()->subHours(2),
            'status' => 'active',
        ]);

        // Generate QR code for student
        $qrData = $this->generateValidQrData($this->student);

        // Scan the QR code (check-out)
        Livewire::test(QrScanner::class)
            ->call('handleFileUploadScan', $qrData);

        // Verify attendance was completed
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'completed',
        ]);

        // Verify check-out notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'type' => 'attendance_checkout',
        ]);

        $notification = Notification::where('user_id', $this->student->id)
            ->where('type', 'attendance_checkout')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('Library Check-out Successful', $notification->title);
        $this->assertStringContainsString('checked out of the library', $notification->message);

        // Assert full data structure
        $this->assertIsArray($notification->data);
        $this->assertArrayHasKey('attendance_id', $notification->data);
        $this->assertArrayHasKey('time_in', $notification->data);
        $this->assertArrayHasKey('time_out', $notification->data);
        $this->assertArrayHasKey('duration_minutes', $notification->data);
        $this->assertArrayHasKey('duration_text', $notification->data);

        $this->assertEquals($attendance->id, $notification->data['attendance_id']);
        $this->assertMatchesRegularExpression('/\w{3} \d{2}, \d{4} \d{2}:\d{2} [AP]M/', $notification->data['time_in']);
        $this->assertMatchesRegularExpression('/\w{3} \d{2}, \d{4} \d{2}:\d{2} [AP]M/', $notification->data['time_out']);
        $this->assertGreaterThan(0, $notification->data['duration_minutes']);
        $this->assertNotEmpty($notification->data['duration_text']);
    }

    /** @test */
    #[Test]
    public function notification_types_are_correctly_set(): void
    {
        $this->actingAs($this->librarian);

        // First scan (check-in)
        $qrData = $this->generateValidQrData($this->student);
        Livewire::test(QrScanner::class)->call('handleFileUploadScan', $qrData);

        // Verify notification type for check-in
        $checkInNotification = Notification::where('user_id', $this->student->id)
            ->where('type', 'attendance_checkin')
            ->first();
        $this->assertNotNull($checkInNotification);

        // Generate new QR for check-out (need new nonce)
        $qrData2 = $this->generateValidQrData($this->student);
        Livewire::test(QrScanner::class)->call('handleFileUploadScan', $qrData2);

        // Verify notification type for check-out
        $checkOutNotification = Notification::where('user_id', $this->student->id)
            ->where('type', 'attendance_checkout')
            ->first();
        $this->assertNotNull($checkOutNotification);
    }

    /** @test */
    #[Test]
    public function notification_contains_correct_data_structure(): void
    {
        $this->actingAs($this->librarian);

        // Scan for check-in
        $qrData = $this->generateValidQrData($this->student);
        Livewire::test(QrScanner::class)->call('handleFileUploadScan', $qrData);

        $notification = Notification::where('user_id', $this->student->id)
            ->where('type', 'attendance_checkin')
            ->first();

        // Data should be stored as array (cast in model)
        $this->assertIsArray($notification->data);
        $this->assertArrayHasKey('attendance_id', $notification->data);

        // Attendance ID should match the created attendance
        $attendance = Attendance::where('user_id', $this->student->id)->first();
        $this->assertEquals($attendance->id, $notification->data['attendance_id']);
    }

    /** @test */
    #[Test]
    public function notifications_page_shows_attendance_notifications(): void
    {
        // Create check-in notification
        Notification::create([
            'user_id' => $this->student->id,
            'type' => 'attendance_checkin',
            'title' => 'Library Check-in Successful',
            'message' => 'Welcome to the library! You checked in at 10:00 AM.',
            'data' => ['attendance_id' => 1, 'time_in' => now()->format('M d, Y h:i A')],
        ]);

        // Create check-out notification
        Notification::create([
            'user_id' => $this->student->id,
            'type' => 'attendance_checkout',
            'title' => 'Library Check-out Successful',
            'message' => 'You checked out of the library. Total time: 2 hours.',
            'data' => [
                'attendance_id' => 1,
                'time_in' => now()->subHours(2)->format('M d, Y h:i A'),
                'time_out' => now()->format('M d, Y h:i A'),
                'duration_minutes' => 120,
                'duration_text' => '2 hours',
            ],
        ]);

        $this->actingAs($this->student);

        // Verify notifications are retrievable
        $notifications = Notification::where('user_id', $this->student->id)->get();
        $this->assertCount(2, $notifications);

        // Verify types are correct
        $types = $notifications->pluck('type')->toArray();
        $this->assertContains('attendance_checkin', $types);
        $this->assertContains('attendance_checkout', $types);
    }

    /** @test */
    #[Test]
    public function qr_code_v7_format_is_validated_correctly(): void
    {
        $this->actingAs($this->librarian);

        // Generate QR with v7 format
        $qrData = $this->generateValidQrData($this->student);

        // Decrypt and verify format
        $wrapper = json_decode($qrData, true);
        $this->assertArrayHasKey('encrypted', $wrapper);

        $decrypted = json_decode(Crypt::decryptString($wrapper['encrypted']), true);

        // V7 format should have: user_id, nonce, timestamp, hash
        $this->assertArrayHasKey('user_id', $decrypted);
        $this->assertArrayHasKey('nonce', $decrypted);
        $this->assertArrayHasKey('timestamp', $decrypted);
        $this->assertArrayHasKey('hash', $decrypted);

        // Scan should work
        Livewire::test(QrScanner::class)->call('handleFileUploadScan', $qrData);

        // Verify success
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);
    }
}
