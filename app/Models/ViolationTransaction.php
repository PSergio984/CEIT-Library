<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $violation_id
 * @property \Illuminate\Support\Carbon $date_occurred
 * @property string $severity
 * @property string|null $remarks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Violation $violation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction byDateRange($startDate = null, $endDate = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction bySeverity($severity)
 * @method static \Database\Factories\ViolationTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction recent($days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereDateOccurred($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ViolationTransaction whereSeverity($value)
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
        'violation_penalty',
        'date_occurred',
        'severity',
        'remarks',
    ];

    protected $casts = [
        'date_occurred' => 'date',
    ];

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

                // Update the stored penalty
                $violationTransaction->violation_penalty = $newPenalty;

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
     */
    protected static function updateUserCreditScoreAtomic(int $userId, int $delta): void
    {
        // Use parameterized query with DB::statement for proper binding
        \DB::statement(
            'UPDATE users SET credit_score = LEAST(100, GREATEST(0, credit_score + ?)) WHERE id = ?',
            [$delta, $userId]
        );
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

    // Scope for filtering by severity
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
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
}
