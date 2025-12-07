<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $academic_paper_id
 * @property int $copy_number
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicPaper $academicPaper
 *
 * @method static \Database\Factories\InventoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereAcademicPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereCopyNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventory whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_paper_id',
        'copy_number',
        'status',
    ];

    protected $casts = [
        'copy_number' => 'integer',
    ];

    /**
     * Get the academic paper that owns this copy
     */
    public function academicPaper()
    {
        return $this->belongsTo(AcademicPaper::class);
    }

    /**
     * Check if this copy is available
     */
    public function isAvailable()
    {
        return $this->status === 'Available';
    }

    /**
     * Check if this copy is reserved
     */
    public function isReserved()
    {
        return $this->status === 'Reserved';
    }

    /**
     * Check if this copy is unavailable
     */
    public function isUnavailable()
    {
        return $this->status === 'Unavailable';
    }
}
