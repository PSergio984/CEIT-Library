<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoreIncrement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'score_value',
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Update score based on violations
    public function updateScore()
    {
        $totalPenalty = ViolationTransaction::getUserTotalPenalty($this->user_id);
        $this->score_value = max(0, 75 - $totalPenalty); // Minimum score is 0
        $this->save();

        return $this->score_value;
    }

    // Get credit score status
    public function getStatusAttribute()
    {
        if ($this->score_value >= 70) return 'Excellent';
        if ($this->score_value >= 50) return 'Good';
        if ($this->score_value >= 30) return 'Fair';
        if ($this->score_value >= 10) return 'Poor';
        return 'Critical';
    }

    // Check if user can access library services
    public function canAccessLibrary()
    {
        return $this->score_value >= 10; // Minimum score required
    }

    // Check if user can borrow academic paper
    public function canBorrowAcademicPaper()
    {
        return $this->score_value >= 30; // Higher requirement for academic paper access
    }

    // Get or create credit score for a user
    public static function getOrCreateForUser($userId)
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['score_value' => 75] // Default starting score
        );
    }

    // Scope for filtering by score range
    public function scopeByScoreRange($query, $minScore = null, $maxScore = null)
    {
        if ($minScore !== null) {
            $query->where('score_value', '>=', $minScore);
        }
        if ($maxScore !== null) {
            $query->where('score_value', '<=', $maxScore);
        }
        return $query;
    }

    // Scope for users with good standing
    public function scopeGoodStanding($query)
    {
        return $query->where('score_value', '>=', 30);
    }
}
