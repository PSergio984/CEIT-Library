<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchAdviser extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the academic papers for this research adviser.
     */
    public function academicPapers()
    {
        return $this->hasMany(AcademicPaper::class);
    }
}
