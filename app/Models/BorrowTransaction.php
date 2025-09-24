<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int $academic_paper_id
 * @property int $inventory_id
 * @property \Illuminate\Support\Carbon|null $time_in
 * @property \Illuminate\Support\Carbon|null $time_out
 * @property string $status
 * @property \Illuminate\Support\Carbon $expires_at
 * @property string $session_token
 * @property string|null $notes
 * @property int|null $duration_minutes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AcademicPaper $academicPaper
 * @property-read \App\Models\Inventory $inventory
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\BorrowTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereAcademicPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereInventoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereSessionToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereTimeIn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereTimeOut($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BorrowTransaction whereUserId($value)
 * @mixin \Eloquent
 */
class BorrowTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'academic_paper_id',
        'inventory_id',
        'time_in',
        'time_out',
        'status',
        'expires_at',
        'session_token',
        'notes',
        'duration_minutes',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship with academic paper
    public function academicPaper()
    {
        return $this->belongsTo(AcademicPaper::class);
    }

    // Relationship with inventory copy
    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    // Generate unique session token for QR code
    public static function generateSessionToken()
    {
        do {
            $token = Str::random(64);
        } while (static::where('session_token', $token)->exists());

        return $token;
    }

    // Start the academic paper reading session
    public function startSession()
    {
        $this->time_in = Carbon::now();
        $this->status = 'started';
        $this->save();

        // Update academic paper status to Reserved
        $this->academicPaper->update(['status' => 'Reserved']);
    }

    // Complete the academic paper reading session
    public function completeSession()
    {
        $this->time_out = Carbon::now();
        $this->status = 'completed';
        $this->calculateDuration();
        $this->save();

        // Update academic paper status back to Available
        $this->academicPaper->update(['status' => 'Available']);
    }

    // Calculate duration when session is completed
    public function calculateDuration()
    {
        if ($this->time_in && $this->time_out) {
            $this->duration_minutes = $this->time_in->diffInMinutes($this->time_out);
            return $this->duration_minutes;
        }
        return null;
    }

    // Check if session is expired
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    // Check if session is active
    public function isActive()
    {
        return $this->status === 'started' && !$this->isExpired();
    }

    // Find session by token (for QR scanning)
    public static function findByToken($token)
    {
        return static::where('session_token', $token)->first();
    }

    // Get active session for a user and academic paper
    public static function getActiveSession($userId, $academicPaperId)
    {
        return static::where('user_id', $userId)
                    ->where('academic_paper_id', $academicPaperId)
                    ->where('status', 'started')
                    ->first();
    }

    // Boot method to handle token generation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (!$session->session_token) {
                $session->session_token = static::generateSessionToken();
            }
        });
    }
}
