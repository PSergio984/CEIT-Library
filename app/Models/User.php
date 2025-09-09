<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationship with librarian duties
    public function librarianDuty()
    {
        return $this->hasOne(Librarian::class);
    }

    // Check if user has active librarian privileges
    public function isLibrarian()
    {
        return $this->librarianDuty()
                   ->where('status', 'active')
                   ->where('expires_at', '>', now())
                   ->exists();
    }

    // Get active librarian record
    public function getActiveLibrarianDuty()
    {
        return $this->librarianDuty()
                   ->where('status', 'active')
                   ->where('expires_at', '>', now())
                   ->first();
    }

    // Check if user has specific librarian permission
    public function hasLibrarianPermission($permission)
    {
        $librarian = $this->getActiveLibrarianDuty();
        return $librarian && $librarian->hasPermission($permission);
    }

    // Relationships for library usage tracking
    public function librarySessions()
    {
        return $this->hasMany(LibrarySession::class);
    }

    public function thesisSessions()
    {
        return $this->hasMany(ThesisSession::class);
    }

    public function violations()
    {
        return $this->hasMany(UserViolation::class);
    }

    public function creditScore()
    {
        return $this->hasOne(CreditScore::class);
    }

    // Check if user is currently in the library
    public function isInLibrary()
    {
        return $this->librarySessions()
                   ->where('status', 'active')
                   ->whereNotNull('time_in')
                   ->whereNull('time_out')
                   ->exists();
    }
}
