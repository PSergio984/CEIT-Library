<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
