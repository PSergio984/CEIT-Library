<?php

namespace App\Services;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    private const VALIDATION_INVALID = 'invalid';

    /**
     * Decrypt and validate the attendance QR code data
     */
    public function decryptAndValidateAttendanceData(string $qrData): array|string
    {
        try {
            // Timestamp skew is intentionally not enforced (Offline Accessibility - Phase 7):
            // downloaded/screenshot QR codes must keep working offline.
            $data = app(QrPayloadValidator::class)->validate($qrData, enforceTimestampSkew: false, consumeNonce: false);

            if ($data === QrPayloadValidator::INVALID) {
                return self::VALIDATION_INVALID;
            }

            $user = app(QrPayloadValidator::class)->resolveUser($data);

            if ($user === QrPayloadValidator::INVALID) {
                return self::VALIDATION_INVALID;
            }

            Log::info('QR code validated successfully (v7)', [
                'user_id' => $user->id,
            ]);

            return [
                'user_id' => $data['user_id'],
                'user' => $user,
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during QR validation', ['error' => $e->getMessage()]);

            return self::VALIDATION_INVALID;
        }
    }

    /**
     * Process the attendance based on scanned QR data
     */
    public function processAttendance(array $data): array
    {
        $userId = $data['user_id'];
        $user = $data['user'];

        // Get the current user who is scanning
        $currentUser = Auth::user();

        // Get the librarian ID if current user has an active librarian duty
        $scannedBy = $currentUser?->getActiveLibrarianDuty()?->id;

        // If no librarian duty but user has admin access, store admin user ID
        $scannedByAdminId = null;
        if (! $scannedBy && $currentUser?->hasAdminAccess()) {
            $scannedByAdminId = $currentUser->id;
        }

        // Authorization Check: Ensure the scanner is authorized (librarian on duty or admin)
        if (! $scannedBy && ! $scannedByAdminId) {
            Log::warning('Unauthorized attendance scan attempt', [
                'user_id' => $currentUser?->id,
                'target_user_id' => $userId,
            ]);

            return [
                'success' => false,
                'message' => 'You are not authorized to process attendance scans.',
                'title' => 'Authorization Failed',
            ];
        }

        // Check if user has an active session
        $activeSession = Attendance::getActiveSession($userId);

        if ($activeSession) {
            // User is checking out (time out) - wrap in transaction
            try {
                $minutes = 0;
                $durationText = '';
                $result = DB::transaction(function () use ($activeSession, $user, &$minutes, &$durationText) {
                    $activeSession->time_out = Carbon::now();
                    $activeSession->status = 'completed';
                    $activeSession->calculateDuration();
                    $activeSession->save();

                    // Format duration message properly
                    $minutes = (int) $activeSession->duration_minutes;
                    $durationText = $this->formatDuration($minutes);

                    return [
                        'success' => true,
                        'message' => "Goodbye, {$user->first_name}! You stayed for {$durationText}.",
                        'title' => 'Check-out Successful',
                        'attendance' => $activeSession,
                        'action' => 'checkout',
                    ];
                });

                // Create check-out notification for the user
                try {
                    app(NotificationService::class)->notify(
                        $user,
                        'attendance_checkout',
                        'Library Check-out Successful',
                        "You checked out of the library. Total time: {$durationText}. Thank you for visiting!",
                        [
                            'attendance_id' => $activeSession->id,
                            'time_in' => $activeSession->time_in->format('M d, Y h:i A'),
                            'time_out' => $activeSession->time_out->format('M d, Y h:i A'),
                            'duration_minutes' => $minutes,
                            'duration_text' => $durationText,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('Checkout notification failed: '.$e->getMessage());
                }

                return $result;
            } catch (\Exception $e) {
                Log::error('Check-out transaction failed', [
                    'user_id' => $activeSession->user_id,
                    'attendance_id' => $activeSession->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'An error occurred while checking out. Please try again later.',
                    'title' => 'Check-out Failed',
                ];
            }
        } else {
            // User is checking in (time in) - wrap in transaction
            try {
                $attendance = null;
                $result = DB::transaction(function () use ($userId, $scannedBy, $scannedByAdminId, $user, &$attendance) {
                    $attendance = Attendance::create([
                        'user_id' => $userId,
                        'role_id' => $user->role_id,
                        'time_in' => Carbon::now(),
                        'status' => 'active',
                        'scanned_by' => $scannedBy,
                        'scanned_by_admin_id' => $scannedByAdminId,
                    ]);

                    return [
                        'success' => true,
                        'message' => "Welcome, {$user->first_name}! Enjoy your time in the library.",
                        'title' => 'Check-in Successful',
                        'attendance' => $attendance,
                        'action' => 'checkin',
                    ];
                });

                // Create check-in notification for the user
                try {
                    app(NotificationService::class)->notify(
                        $user,
                        'attendance_checkin',
                        'Library Check-in Successful',
                        "Welcome to the library! You checked in at {$attendance->time_in->format('h:i A')}. Enjoy your time!",
                        [
                            'attendance_id' => $attendance->id,
                            'time_in' => $attendance->time_in->format('M d, Y h:i A'),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('Checkin notification failed: '.$e->getMessage());
                }

                return $result;
            } catch (\Exception $e) {
                Log::error('Check-in transaction failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'An error occurred while checking in. Please try again later.',
                    'title' => 'Check-in Failed',
                ];
            }
        }
    }

    /**
     * Format duration in a human-readable way
     */
    public function formatDuration(int $minutes): string
    {
        if ($minutes < 1) {
            return 'less than 1 minute';
        } elseif ($minutes < 60) {
            return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        } else {
            $hours = (int) floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            $hoursText = $hours.' '.($hours === 1 ? 'hour' : 'hours');
            if ($remainingMinutes > 0) {
                return $hoursText.' and '.$remainingMinutes.' '.($remainingMinutes === 1 ? 'minute' : 'minutes');
            }

            return $hoursText;
        }
    }
}
