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
        'date_occurred',
        'severity',
        'remarks',
    ];

    protected $casts = [
        'date_occurred' => 'date',
    ];

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
