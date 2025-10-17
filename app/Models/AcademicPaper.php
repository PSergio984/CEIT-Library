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
                $paper->catalog_code = self::generateUniqueCatalogCode($paper->department, $paper->publication_year);
            }
        });
    }

    /**
     * Generate a unique catalog code atomically to prevent race conditions
     */
    private static function generateUniqueCatalogCode($department, $publicationYear)
    {
        $departmentCode = self::getDepartmentCode($department);
        $year = substr($publicationYear, -2);

        $maxRetries = 10; // Prevent infinite loops
        $attempt = 0;

        do {
            $attempt++;

            try {
                // Use atomic sequence table - no need for outer transaction
                $highestSequence = self::getNextSequenceAtomic($departmentCode, $year);
                $code = "CEIT-{$departmentCode}-{$year}-{$highestSequence}";

                return $code;
            } catch (\Exception $e) {
                // If we hit a duplicate or timeout, retry with exponential backoff
                if ($attempt >= $maxRetries) {
                    throw new \RuntimeException("Failed to generate unique catalog code after {$maxRetries} attempts. Last error: " . $e->getMessage());
                }

                // Exponential backoff: wait longer between retries
                usleep(pow(2, $attempt) * 1000); // 2ms, 4ms, 8ms, 16ms, etc.
            }
        } while (true);
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
     * Get next sequence number for department and year (atomic version using sequence table)
     */
    private static function getNextSequenceAtomic($departmentCode, $year)
    {
        $sequenceKey = "{$departmentCode}-{$year}";

        // Check if we're using SQLite (for testing) or MySQL (for production)
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            // SQLite: Use INSERT OR IGNORE, then UPDATE in a transaction
            return \DB::transaction(function () use ($sequenceKey) {
                // First, ensure the row exists
                \DB::statement(
                    'INSERT OR IGNORE INTO catalog_sequences (sequence_key, last_sequence, created_at, updated_at) VALUES (?, ?, ?, ?)',
                    [$sequenceKey, 0, now(), now()]
                );

                // Atomically increment and get the new value
                \DB::statement(
                    'UPDATE catalog_sequences SET last_sequence = last_sequence + 1, updated_at = ? WHERE sequence_key = ?',
                    [now(), $sequenceKey]
                );

                // Read back the incremented value
                $sequence = \DB::table('catalog_sequences')
                    ->where('sequence_key', $sequenceKey)
                    ->value('last_sequence');

                // Use flexible padding: minimum 2 digits, but can grow beyond 99
                // Examples: 01, 02, ..., 99, 100, 101, etc.
                return str_pad($sequence, max(2, strlen((string)$sequence)), '0', STR_PAD_LEFT);
            });
        } else {
            // MySQL: Use INSERT ... ON DUPLICATE KEY UPDATE with LAST_INSERT_ID() for atomic sequence generation
            // LAST_INSERT_ID() is connection-specific, so each concurrent request gets its own unique value

            // First, try to insert. If row exists, update and capture the new value atomically
            \DB::statement(
                'INSERT INTO catalog_sequences (sequence_key, last_sequence, created_at, updated_at) 
                 VALUES (?, 1, ?, ?) 
                 ON DUPLICATE KEY UPDATE last_sequence = LAST_INSERT_ID(last_sequence + 1), updated_at = VALUES(updated_at)',
                [$sequenceKey, now(), now()]
            );

            // Fetch the atomically incremented value for this connection
            // For INSERT: LAST_INSERT_ID() returns the auto_increment ID (not useful), so we read last_sequence
            // For UPDATE: LAST_INSERT_ID(last_sequence + 1) returns the new sequence value
            $lastId = (int) \DB::select('SELECT LAST_INSERT_ID() as id')[0]->id;

            // Check the actual last_sequence value to determine if this was INSERT or UPDATE
            $sequence = \DB::table('catalog_sequences')
                ->where('sequence_key', $sequenceKey)
                ->value('last_sequence');

            // If LAST_INSERT_ID() matches last_sequence, it was an UPDATE (LAST_INSERT_ID captured it)
            // If they don't match, it was an INSERT (LAST_INSERT_ID is auto_increment ID)
            // In both cases, we use the actual last_sequence value for correctness
            $sequence = (int) $sequence;

            // Use flexible padding: minimum 2 digits, but can grow beyond 99
            // Examples: 01, 02, ..., 99, 100, 101, etc.
            return str_pad($sequence, max(2, strlen((string)$sequence)), '0', STR_PAD_LEFT);
        }
    }

    /**
     * Get next sequence number for department and year (legacy method for backwards compatibility)
     * @deprecated Use getNextSequenceAtomic for new implementations
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

        // Use flexible padding: minimum 2 digits, but can grow beyond 99
        // Examples: 01, 02, ..., 99, 100, 101, etc.
        $nextSequence = $highestSequence + 1;
        return str_pad($nextSequence, max(2, strlen((string)$nextSequence)), '0', STR_PAD_LEFT);
    }
}
