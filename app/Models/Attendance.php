<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $time_in
 * @property \Illuminate\Support\Carbon|null $time_out
 * @property string $status
 * @property int|null $scanned_by
 * @property int|null $duration_minutes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Librarian|null $scannedByLibrarian
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\AttendanceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereScannedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereTimeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereTimeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attendance whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'time_in',
        'time_out',
        'status',
        'scanned_by',
        'scanned_by_admin_id',
        'duration_minutes',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
    ];

    protected static function booted()
    {
        // When attendance status transitions to completed, check if 30+ minutes and create ScoreIncrement
        static::updated(function ($attendance) {
            // Only award points if status just transitioned to completed (not already completed)
            $wasCompleted = $attendance->getOriginal('status') === 'completed';
            $isNowCompleted = $attendance->status === 'completed';

            if (! $wasCompleted && $isNowCompleted) {
                // Use the existing duration_minutes property instead of recalculating
                if ($attendance->duration_minutes >= 30) {
                    // Check daily limit (max 3 credit score rewards per day for attendance)
                    $todayAttendanceRewards = ScoreIncrement::where('user_id', $attendance->user_id)
                        ->where('name', 'Attendance 30+ Minutes')
                        ->whereDate('created_at', today())
                        ->count();

                    if ($todayAttendanceRewards >= 3) {
                        // Daily limit reached - no credit score, but can notify if needed
                        $user = $attendance->user;
                        if ($user) {
                            $durationDisplay = (int) $attendance->duration_minutes;
                            Notification::create([
                                'user_id' => $attendance->user_id,
                                'type' => 'attendance_checkout',
                                'title' => 'Checked Out Successfully!',
                                'message' => "You stayed in the library for {$durationDisplay} minutes. (Daily credit limit reached - max 3 rewards per day)",
                                'data' => [
                                    'attendance_id' => $attendance->id,
                                    'duration_minutes' => $attendance->duration_minutes,
                                    'score_awarded' => 0,
                                    'reason' => 'daily_limit_reached',
                                    'daily_count' => $todayAttendanceRewards,
                                ],
                            ]);
                        }

                        return;
                    }

                    // Efficient idempotency check: use indexed related_attendance_id for exact lookup
                    $existingReward = ScoreIncrement::where('user_id', $attendance->user_id)
                        ->where('related_attendance_id', $attendance->id)
                        ->exists();

                    if (! $existingReward) {
                        // Create a ScoreIncrement record (which will auto-update user's credit_score via its model event)
                        $scoreIncrement = ScoreIncrement::create([
                            'user_id' => $attendance->user_id,
                            'name' => 'Attendance 30+ Minutes',
                            'description' => "Stayed in library for {$attendance->duration_minutes} minutes",
                            'score_value' => 5,
                            'related_attendance_id' => $attendance->id,
                        ]);

                        // Create a notification for the user about their credit score increase
                        $user = $attendance->user;
                        if ($user) {
                            // Ensure duration is formatted as integer for display
                            $durationDisplay = (int) $attendance->duration_minutes;
                            Notification::create([
                                'user_id' => $attendance->user_id,
                                'type' => 'credit_score_increase',
                                'title' => 'Credit Score Increased!',
                                'message' => "Great job! You earned +5 credit points for staying in the library for {$durationDisplay} minutes. Your current credit score is {$user->credit_score}/100.",
                                'data' => [
                                    'score_increment_id' => $scoreIncrement->id,
                                    'score_value' => 5,
                                    'duration_minutes' => $attendance->duration_minutes,
                                    'attendance_id' => $attendance->id,
                                    'credit_score' => $user->credit_score,
                                    'earned_at' => now()->toDateTimeString(),
                                ],
                            ]);
                        }
                    }
                }
            }
        });

        // When attendance is deleted, delete related ScoreIncrement records via Eloquent
        // so the ScoreIncrement observers run and adjust user credit_score correctly
        static::deleting(function ($attendance) {
            ScoreIncrement::where('related_attendance_id', $attendance->id)->cursor()->each(function ($scoreIncrement) {
                $scoreIncrement->delete();
            });
        });
    }

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with librarian who scanned
    public function scannedByLibrarian()
    {
        return $this->belongsTo(Librarian::class, 'scanned_by');
    }

    // Relationship with admin who scanned (when no librarian duty)
    public function scannedByAdmin()
    {
        return $this->belongsTo(User::class, 'scanned_by_admin_id');
    }

    /**
     * Get the name of who scanned this attendance.
     * Priority: Librarian > Admin/Super Admin > 'N/A'
     * For Super Admin, display "Super Admin" instead of the name.
     */
    public function getScannedByNameAttribute(): string
    {
        // First check librarian
        if ($this->scannedByLibrarian && $this->scannedByLibrarian->user) {
            $user = $this->scannedByLibrarian->user;

            return trim($user->first_name.' '.$user->last_name) ?: 'N/A';
        }

        // Then check admin/super admin
        if ($this->scannedByAdmin) {
            // For Super Admin, display "Super Admin" instead of name
            if ($this->scannedByAdmin->isSuperAdmin()) {
                return 'Super Admin';
            }

            // For regular Admin, display their name
            return trim($this->scannedByAdmin->first_name.' '.$this->scannedByAdmin->last_name) ?: 'N/A';
        }

        return 'N/A';
    }

    // Relationship with role (snapshot at check-in time)
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // Calculate duration when time_out is set
    public function calculateDuration()
    {
        if ($this->time_in && $this->time_out) {
            // Cast to int to avoid decimal precision issues in notifications
            $this->duration_minutes = (int) $this->time_in->diffInMinutes($this->time_out);

            return $this->duration_minutes;
        }

        return null;
    }

    // Check if session is currently active (user is in library)
    public function isActive()
    {
        return $this->status === 'active' && $this->time_in && ! $this->time_out;
    }

    // Complete the session (time out)
    public function complete()
    {
        $this->time_out = Carbon::now('Asia/Manila');
        $this->status = 'completed';
        $this->calculateDuration();
        $this->save();
    }

    // Get active session for a user
    public static function getActiveSession($userId)
    {
        return static::where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->first();
    }
}
