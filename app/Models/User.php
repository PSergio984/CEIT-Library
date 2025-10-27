<?php

namespace App\Models;

use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property int $credit_score
 * @property string $account_status
 * @property string $password
 * @property int $is_admin
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BorrowTransaction> $borrowTransactions
 * @property-read int|null $borrow_transactions_count
 * @property-read \App\Models\ScoreIncrement|null $creditScore
 * @property-read \App\Models\Librarian|null $librarianDuty
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attendance> $librarySessions
 * @property-read int|null $library_sessions_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ViolationTransaction> $violations
 * @property-read int|null $violations_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreditScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'is_admin',
        'credit_score',
        'account_status',
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
            'credit_score' => 'integer',
            'account_status' => 'string',
        ];
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\CustomResetPassword($token));
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
        return $this->hasMany(Attendance::class);
    }

    public function borrowTransactions()
    {
        return $this->hasMany(BorrowTransaction::class);
    }

    public function violations()
    {
        return $this->hasMany(ViolationTransaction::class);
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
