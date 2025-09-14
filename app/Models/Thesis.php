<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thesis extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_code',
        'title',
        'year',
        'research_project_adviser',
        'department',
        'member1',
        'member2',
        'member3',
        'member4',
        'dean',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    // Relationship with thesis copies
    public function copies()
    {
        return $this->hasMany(ThesisCopy::class);
    }

    // Get available copies count
    public function getAvailableCopiesCountAttribute()
    {
        return $this->copies()->where('status', 'Available')->count();
    }

    // Get total copies count
    public function getTotalCopiesCountAttribute()
    {
        return $this->copies()->count();
    }

    // Check if thesis has available copies
    public function hasAvailableCopies()
    {
        return $this->available_copies_count > 0;
    }

    // Scope for searching theses
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('research_project_adviser', 'LIKE', "%{$search}%")
              ->orWhere('catalog_code', 'LIKE', "%{$search}%");
        });
    }

    // Scope for filtering by department
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    // Scope for available theses only
    public function scopeAvailable($query)
    {
        return $query->where('status', 'Available');
    }
}
