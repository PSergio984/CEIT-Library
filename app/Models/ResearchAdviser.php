<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchAdviser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the academic papers for this research adviser.
     */
    public function academicPapers(): HasMany
    {
        return $this->hasMany(AcademicPaper::class);
    }
}
