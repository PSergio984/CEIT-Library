<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $catalog_code
 * @property string $title
 * @property int $publication_year
 * @property string $paper_type
 * @property string $research_project_adviser
 * @property string $department
 * @property string $dean
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Author> $authors
 * @property-read int|null $authors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inventory> $copies
 * @property-read int|null $copies_count
 * @property-read mixed $available_copies_count
 * @property-read mixed $total_copies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper byDepartment($department)
 * @method static \Database\Factories\AcademicPaperFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper search($search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereCatalogCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereDean($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper wherePaperType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper wherePublicationYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereResearchProjectAdviser($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AcademicPaper whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
                ->orWhere('catalog_code', 'LIKE', "%{$search}%")
                ->orWhereHas('authors', function ($authorQuery) use ($search) {
                    $authorQuery->where('name', 'LIKE', "%{$search}%");
                });
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
                    // Format: CEIT-{DEPARTMENT_CODE}-{YEAR}-{SEQUENCE}
                    $departmentCode = self::getDepartmentCode($paper->department);
                    $year = substr($paper->publication_year, -2); // 2-digit year from publication_year

                    // Get the next sequence number for this department and year
                    $sequence = self::getNextSequence($departmentCode, $year);

                    $code = "CEIT-{$departmentCode}-{$year}-{$sequence}";
                } while (self::where('catalog_code', $code)->exists());
                $paper->catalog_code = $code;
            }
        });
    }

    /**
     * Get department code from department name
     */
    private static function getDepartmentCode($department)
    {
        $departmentCodes = [
            'Information Technology' => 'IT',
            'Civil Engineering' => 'CE',
            'Electrical Engineering' => 'EE',
        ];

        return $departmentCodes[$department] ?? 'XX';
    }

    /**
     * Get next sequence number for department and year
     */
    private static function getNextSequence($departmentCode, $year)
    {
        // Use database-side query to extract and find max sequence number
        $pattern = "CEIT-{$departmentCode}-{$year}-%";

        // Check if we're using SQLite (for testing) or MySQL (for production)
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            // SQLite-compatible query: use a simple approach with known format
            // Format: CEIT-{DEPARTMENT_CODE}-{YEAR}-{SEQUENCE}
            $highestSequence = self::where('catalog_code', 'like', $pattern)
                ->selectRaw('
                    COALESCE(
                        MAX(
                            CAST(
                                SUBSTR(
                                    catalog_code, 
                                    LENGTH("CEIT-' . $departmentCode . '-' . $year . '-") + 1
                                ) AS INTEGER
                            )
                        ), 
                        0
                    ) as max_sequence
                ')
                ->value('max_sequence');
        } else {
            // MySQL-compatible query using SUBSTRING_INDEX
            $highestSequence = self::where('catalog_code', 'like', $pattern)
                ->selectRaw('
                    COALESCE(
                        MAX(
                            CAST(
                                NULLIF(
                                    SUBSTRING_INDEX(catalog_code, "-", -1),
                                    ""
                                ) AS UNSIGNED
                            )
                        ), 
                        0
                    ) as max_sequence
                ')
                ->value('max_sequence');
        }

        return str_pad($highestSequence + 1, 2, '0', STR_PAD_LEFT);
    }
}
