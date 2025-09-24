<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
