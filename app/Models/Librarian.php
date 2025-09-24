<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $shift_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian active()
 * @method static \Database\Factories\LibrarianFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereShiftNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Librarian whereUserId($value)
 * @mixin \Eloquent
 */
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

    // Relationship with the Admin who created this librarian duty
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
