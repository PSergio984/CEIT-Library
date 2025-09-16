<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ThesisSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'thesis_id',
        'thesis_copy_id',
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

    // Relationship with thesis
    public function thesis()
    {
        return $this->belongsTo(Thesis::class);
    }

    // Relationship with thesis copy
    public function thesisCopy()
    {
        return $this->belongsTo(ThesisCopy::class);
    }

    // Generate unique session token for QR code
    public static function generateSessionToken()
    {
        do {
            $token = Str::random(64);
        } while (static::where('session_token', $token)->exists());

        return $token;
    }

    // Start the thesis reading session
    public function startSession()
    {
        $this->time_in = Carbon::now();
        $this->status = 'started';
        $this->save();

        // Update thesis status to Reserved
        $this->thesis->update(['status' => 'Reserved']);
    }

    // Complete the thesis reading session
    public function completeSession()
    {
        $this->time_out = Carbon::now();
        $this->status = 'completed';
        $this->calculateDuration();
        $this->save();

        // Update thesis status back to Available
        $this->thesis->update(['status' => 'Available']);
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

    // Get active session for a user and thesis
    public static function getActiveSession($userId, $thesisId)
    {
        return static::where('user_id', $userId)
                    ->where('thesis_id', $thesisId)
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
