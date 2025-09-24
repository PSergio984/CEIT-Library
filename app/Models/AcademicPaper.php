<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPaper extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog_code',
        'title',
        'publication_year',
        'paper_type',
        'research_project_adviser',
        'department',
        'dean',
    ];

    protected $casts = [
        'publication_year' => 'integer',
    ];

    // Relationship with academic paper copies
    public function copies()
    {
        return $this->hasMany(Inventory::class);
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

    // Check if academic paper has available copies
    public function hasAvailableCopies()
    {
        return $this->available_copies_count > 0;
    }

    // Scope for searching academic papers
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

    // Many-to-many relationship with authors
    public function authors()
    {
        return $this->belongsToMany(Author::class, 'academic_paper_authors')->withTimestamps();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($paper) {
            if (empty($paper->catalog_code)) {
                do {
                    // Example: CAT-YYYYMMDD-XXXX (customize as needed)
                    $code = 'CAT-' . date('Ymd') . '-' . strtoupper(uniqid());
                } while (self::where('catalog_code', $code)->exists());
                $paper->catalog_code = $code;
            }
        });
    }
}
