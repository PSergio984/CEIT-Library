<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $violation_id
 * @property int|null $attendance_id
 * @property \Illuminate\Support\Carbon $date_occurred
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Violation $violation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction byDateRange($startDate = null, $endDate = null)
 * @method static \Database\Factories\ViolationTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction recent($days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereDateOccurred($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereViolationId($value)
 * @mixin \Eloquent
 */
class ViolationTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'violation_id',
        'attendance_id',
        'violation_penalty',
        'date_occurred',
        'remarks',
    ];

    protected $casts = [
        'date_occurred' => 'datetime',
        'violation_penalty' => 'integer',
    ];
    /**
     * Build the remarks string for a missing timeout violation.
     * @param int $attendanceId
     * @param Carbon|string $date
     * @return string
     */
    public static function buildMissingTimeoutRemarks($attendanceId, $date): string
    {
        if (empty($date)) {
            throw new \InvalidArgumentException('Date parameter cannot be empty');
        }

        if ($date instanceof Carbon) {
            $dateStr = $date->format('M d, Y');
        } else {
            try {
                $dateStr = Carbon::parse($date)->format('M d, Y');
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid date format: {$date}", 0, $e);
            }
        }
        return "Failed to check out from session on {$dateStr}.";
    }

    protected static function booted()
    {
        // Store the original penalty when creating a violation transaction
        static::creating(function ($violationTransaction) {
            // If violation_penalty not explicitly set, fetch it from the violation
            if (!$violationTransaction->violation_penalty) {
                $violation = Violation::find($violationTransaction->violation_id);
                if ($violation) {
                    $violationTransaction->violation_penalty = $violation->penalty_score;
                }
            }
        });

        // When a violation is created, atomically subtract the stored penalty from user's credit_score
        static::created(function ($violationTransaction) {
            if ($violationTransaction->violation_penalty) {
                static::updateUserCreditScoreAtomic($violationTransaction->user_id, -$violationTransaction->violation_penalty);
            }
        });

        // When a violation is updated (e.g., violation_id changes), atomically adjust using stored penalties
        static::updated(function ($violationTransaction) {
            $oldViolationId = $violationTransaction->getOriginal('violation_id');
            $newViolationId = $violationTransaction->violation_id;
            $oldPenalty = $violationTransaction->getOriginal('violation_penalty');

            if ($oldViolationId !== $newViolationId) {
                // Fetch new violation penalty
                $newViolation = Violation::find($newViolationId);
                $newPenalty = $newViolation ? $newViolation->penalty_score : 0;

                // Persist stored penalty without firing events again
                $violationTransaction->updateQuietly(['violation_penalty' => $newPenalty]);

                // Remove old penalty and apply new penalty (delta = old - new because penalties are negative)
                $delta = $oldPenalty - $newPenalty;
                static::updateUserCreditScoreAtomic($violationTransaction->user_id, $delta);
            }
        });

        // When a violation is deleted, atomically add the stored penalty back to user's credit_score
        static::deleted(function ($violationTransaction) {
            // Use the stored penalty to ensure exact reversal
            if ($violationTransaction->violation_penalty) {
                static::updateUserCreditScoreAtomic($violationTransaction->user_id, $violationTransaction->violation_penalty);
            }
        });
    }

    /**
     * Atomically update user's credit score with proper clamping (0-100)
     * Uses a single SQL UPDATE to prevent race conditions
     * Handles missing users gracefully and uses parameterized queries for safety
     * Supports SQLite (CASE WHEN) and MySQL/Postgres (LEAST/GREATEST) for portability
     */
    protected static function updateUserCreditScoreAtomic(int $userId, int $delta): void
    {
        // Detect the database driver to use appropriate SQL functions
        $driver = \DB::connection()->getDriverName();

        // SQLite uses CASE WHEN for clarity and reliability
        if ($driver === 'sqlite') {
            // SQLite syntax: Use CASE WHEN for explicit clamping
            \DB::statement(
                'UPDATE users SET credit_score = CASE 
                    WHEN credit_score + ? < 0 THEN 0
                    WHEN credit_score + ? > 100 THEN 100
                    ELSE credit_score + ?
                END WHERE id = ?',
                [$delta, $delta, $delta, $userId]
            );
        } else {
            // MySQL/PostgreSQL syntax: LEAST for upper bound, GREATEST for lower bound
            \DB::statement(
                'UPDATE users SET credit_score = CAST(LEAST(100, GREATEST(0, CAST(credit_score AS SIGNED) + ?)) AS UNSIGNED) WHERE id = ?',
                [$delta, $userId]
            );
        }
    }

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with violation
    public function violation()
    {
        return $this->belongsTo(Violation::class);
    }

    // Get violations for a specific user
    public static function getUserViolations($userId)
    {
        return static::where('user_id', $userId)
            ->with('violation')
            ->orderBy('date_occurred', 'desc')
            ->get();
    }

    // Get total penalty score for a user
    public static function getUserTotalPenalty($userId)
    {
        return static::join('violations', 'violation_transactions.violation_id', '=', 'violations.id')
            ->where('violation_transactions.user_id', $userId)
            ->sum('violations.penalty_score');
    }

    // Scope for filtering by date range
    public function scopeByDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->where('date_occurred', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('date_occurred', '<=', $endDate);
        }
        return $query;
    }

    // Scope for recent violations (within last 30 days)
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('date_occurred', '>=', Carbon::now()->subDays($days));
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
