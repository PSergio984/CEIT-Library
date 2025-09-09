<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->hasMany(UserViolation::class);
    }

    // Get users who have this violation
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_violations')
                    ->withPivot(['date_occurred', 'Severity', 'remarks'])
                    ->withTimestamps();
    }

    // Scope for filtering by penalty severity
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

    // Get violation severity based on penalty score
    public function getSeverityAttribute()
    {
        if ($this->penalty_score <= 10) return 'Minor';
        if ($this->penalty_score <= 25) return 'Major';
        return 'Critical';
    }
}
