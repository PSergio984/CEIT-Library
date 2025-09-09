<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LibrarySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'time_in',
        'time_out',
        'status',
        'entry_method',
        'scanned_by',
        'duration_minutes',
        'notes',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
    ];

    // Relationship with user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Calculate duration when time_out is set
    public function calculateDuration()
    {
        if ($this->time_in && $this->time_out) {
            $this->duration_minutes = $this->time_in->diffInMinutes($this->time_out);
            return $this->duration_minutes;
        }
        return null;
    }

    // Check if session is currently active (user is in library)
    public function isActive()
    {
        return $this->status === 'active' && $this->time_in && !$this->time_out;
    }

    // Complete the session (time out)
    public function complete()
    {
        $this->time_out = Carbon::now();
        $this->status = 'completed';
        $this->calculateDuration();
        $this->save();
    }

    // Get active session for a user
    public static function getActiveSession($userId)
    {
        return static::where('user_id', $userId)
                    ->where('status', 'active')
                    ->whereNotNull('time_in')
                    ->whereNull('time_out')
                    ->first();
    }
}
