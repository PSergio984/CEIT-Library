<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Librarian extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'expires_at',
        'created_by',
        'last_login_at',
        'shift_notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    // Relationship with the student user account
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with the admin who created this librarian duty
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Check if duty period is expired
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    // All librarians have the same permissions
    public function hasPermission($permission)
    {
        $defaultPermissions = ['scan_qr', 'create_violations', 'view_library_logs'];
        return in_array($permission, $defaultPermissions);
    }

    // Get active librarians (not expired and active status)
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '>', Carbon::now());
    }

    // Get librarian by user ID if they have active duty
    public static function getActiveLibrarianByUser($userId)
    {
        return static::where('user_id', $userId)
                    ->active()
                    ->first();
    }

    // Boot method to handle auto-expiry
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($librarian) {
            if ($librarian->isExpired()) {
                $librarian->status = 'expired';
            }
        });
    }
}
