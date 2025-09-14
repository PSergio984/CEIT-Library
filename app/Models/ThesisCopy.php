<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThesisCopy extends Model
{
    use HasFactory;

    protected $fillable = [
        'thesis_id',
        'copy_number',
        'status',
    ];

    protected $casts = [
        'copy_number' => 'integer',
    ];

    /**
     * Get the thesis that owns this copy
     */
    public function thesis()
    {
        return $this->belongsTo(Thesis::class);
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

    /**
     * Scope for available copies only
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'Available');
    }

    /**
     * Scope for reserved copies only
     */
    public function scopeReserved($query)
    {
        return $query->where('status', 'Reserved');
    }
}
