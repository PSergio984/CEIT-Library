<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'score',
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Update score based on violations
    public function updateScore()
    {
        $totalPenalty = UserViolation::getUserTotalPenalty($this->user_id);
        $this->score = max(0, 75 - $totalPenalty); // Minimum score is 0
        $this->save();

        return $this->score;
    }

    // Get credit score status
    public function getStatusAttribute()
    {
        if ($this->score >= 70) return 'Excellent';
        if ($this->score >= 50) return 'Good';
        if ($this->score >= 30) return 'Fair';
        if ($this->score >= 10) return 'Poor';
        return 'Critical';
    }

    // Check if user can access library services
    public function canAccessLibrary()
    {
        return $this->score >= 10; // Minimum score required
    }

    // Check if user can borrow thesis
    public function canBorrowThesis()
    {
        return $this->score >= 30; // Higher requirement for thesis access
    }

    // Get or create credit score for a user
    public static function getOrCreateForUser($userId)
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['score' => 75] // Default starting score
        );
    }

    // Scope for filtering by score range
    public function scopeByScoreRange($query, $minScore = null, $maxScore = null)
    {
        if ($minScore !== null) {
            $query->where('score', '>=', $minScore);
        }
        if ($maxScore !== null) {
            $query->where('score', '<=', $maxScore);
        }
        return $query;
    }

    // Scope for users with good standing
    public function scopeGoodStanding($query)
    {
        return $query->where('score', '>=', 30);
    }
}
