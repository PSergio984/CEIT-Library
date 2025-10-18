<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property int $score_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $status
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement byScoreRange($minScore = null, $maxScore = null)
 * @method static \Database\Factories\ScoreIncrementFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement goodStanding()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereScoreValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScoreIncrement whereUserId($value)
 * @mixin \Eloquent
 */
class ScoreIncrement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'score_value',
    ];

    protected static function booted()
    {
        // When a score increment is created, add points to user's credit_score
        static::created(function ($scoreIncrement) {
            $user = User::find($scoreIncrement->user_id);
            if ($user) {
                $newScore = $user->credit_score + $scoreIncrement->score_value;
                $user->credit_score = max(0, min(100, $newScore)); // Cap between 0-100
                $user->save();
            }
        });

        // When a score increment is deleted, subtract points from user's credit_score
        static::deleted(function ($scoreIncrement) {
            $user = User::find($scoreIncrement->user_id);
            if ($user) {
                $newScore = $user->credit_score - $scoreIncrement->score_value;
                $user->credit_score = max(0, min(100, $newScore)); // Cap between 0-100
                $user->save();
            }
        });
    }

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
