<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    // Role constants
    const STUDENT = 'student';
    const LIBRARIAN = 'librarian';
    const SUPER_ADMIN = 'super_admin';

    /**
     * Get all users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Check if this role is student
     */
    public function isStudent(): bool
    {
        return $this->name === self::STUDENT;
    }

    /**
     * Check if this role is librarian
     */
    public function isLibrarian(): bool
    {
        return $this->name === self::LIBRARIAN;
    }

    /**
     * Check if this role is super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }

    /**
     * Check if this role has admin privileges (librarian or super_admin)
     */
    public function hasAdminAccess(): bool
    {
        return in_array($this->name, [self::LIBRARIAN, self::SUPER_ADMIN]);
    }
}
