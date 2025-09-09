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
        'copies',
        'research_project_adviser',
        'department',
        'member1',
        'member2',
        'member3',
        'member4',
        'dean',
        'status',
    ];

    // Relationship with thesis sessions
    public function sessions()
    {
        return $this->hasMany(ThesisSession::class);
    }

    // Get active/ongoing sessions for this thesis
    public function activeSessions()
    {
        return $this->sessions()->where('status', 'started');
    }

    // Check if thesis is currently being read
    public function isBeingRead()
    {
        return $this->activeSessions()->exists();
    }

    // Check if thesis is available for reading
    public function isAvailable()
    {
        return $this->status === 'Available' && !$this->isBeingRead();
    }

    // Get all members as an array
    public function getMembers()
    {
        return array_filter([
            $this->member1,
            $this->member2,
            $this->member3,
            $this->member4,
        ]);
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
