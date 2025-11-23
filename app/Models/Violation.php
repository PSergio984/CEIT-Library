<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property int $penalty_score
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ViolationTransaction> $userViolations
 * @property-read int|null $user_violations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation byPenalty($minPenalty = null, $maxPenalty = null)
 * @method static \Database\Factories\ViolationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation wherePenaltyScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Violation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'penalty_score',
    ];

    // Relationship with user violations
    public function userViolations()
    {
        return $this->hasMany(ViolationTransaction::class);
    }

    // Get users who have this violation
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_violations')
            ->withPivot(['date_occurred', 'remarks'])
            ->withTimestamps();
    }

    // Scope for filtering by penalty 
    public function scopeByPenalty($query, $minPenalty = null, $maxPenalty = null)
    {
        if ($minPenalty) {
            $query->where('penalty_score', '>=', $minPenalty);
        }
        if ($maxPenalty) {
            $query->where('penalty_score', '<=', $maxPenalty);
        }
        return $query;
    }
}
