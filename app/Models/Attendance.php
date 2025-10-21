<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
 * @mixin \Eloquent
 */
class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'time_in',
        'time_out',
        'status',
        'scanned_by',
        'duration_minutes',
        'notes',
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

            if (!$wasCompleted && $isNowCompleted) {
                // Use the existing duration_minutes property instead of recalculating
                if ($attendance->duration_minutes >= 30) {
                    // Efficient idempotency check: use indexed related_attendance_id for exact lookup
                    $existingReward = ScoreIncrement::where('user_id', $attendance->user_id)
                        ->where('related_attendance_id', $attendance->id)
                        ->exists();

                    if (!$existingReward) {
                        // Create a ScoreIncrement record (which will auto-update user's credit_score via its model event)
                        ScoreIncrement::create([
                            'user_id' => $attendance->user_id,
                            'name' => 'Attendance 30+ Minutes',
                            'description' => "Stayed in library for {$attendance->duration_minutes} minutes",
                            'score_value' => 5,
                            'related_attendance_id' => $attendance->id,
                        ]);
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

    // Calculate duration when time_out is set
    public function calculateDuration()
    {
        if ($this->time_in && $this->time_out) {
            $this->duration_minutes = $this->time_in->diffInMinutes($this->time_out);
            return $this->duration_minutes;
        }
        return null;
    }

    // Check if session is currently active (user is in library)
    public function isActive()
    {
        return $this->status === 'active' && $this->time_in && !$this->time_out;
    }

    // Complete the session (time out)
    public function complete()
    {
        $this->time_out = Carbon::now();
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
