<?php

namespace App\Models;

use App\Notifications\CustomResetPassword;
use App\Notifications\CustomVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property int $credit_score
 * @property string $account_status
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BorrowTransaction> $borrowTransactions
 * @property-read int|null $borrow_transactions_count
 * @property-read ScoreIncrement|null $creditScore
 * @property-read Librarian|null $librarianDuty
 * @property-read Collection<int, Attendance> $librarySessions
 * @property-read int|null $library_sessions_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, ViolationTransaction> $violations
 * @property-read int|null $violations_count
 *
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
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
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
        'role_id',
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
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    // Relationship with role
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // Relationship with librarian duties
    public function librarianDuty()
    {
        return $this->hasOne(Librarian::class);
    }

    // Role checker methods
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function isStudent(): bool
    {
        return $this->hasRole(Role::STUDENT);
    }

    public function hasLibrarianRole(): bool
    {
        return $this->hasRole(Role::LIBRARIAN);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    // Check if user has admin privileges (admin or super_admin)
    public function hasAdminAccess(): bool
    {
        return $this->role && $this->role->hasAdminAccess();
    }

    // Check if user has any admin-level access (librarian, admin or super_admin)
    public function hasLibrarianOrAdminAccess(): bool
    {
        return $this->role && $this->role->hasLibrarianOrAdminAccess();
    }

    // Check if user has active librarian batch duty (for QR scanning)
    public function hasActiveLibrarianDuty(): bool
    {
        $librarian = $this->librarianDuty;

        if (! $librarian || $librarian->status !== 'active') {
            return false;
        }

        return ! $librarian->isExpired();
    }

    // Alias for backward compatibility - checks BOTH role and batch duty
    public function isLibrarian(): bool
    {
        return $this->hasLibrarianRole() || $this->hasActiveLibrarianDuty();
    }

    // Get active librarian record
    public function getActiveLibrarianDuty(): ?Librarian
    {
        $librarian = $this->librarianDuty;

        if ($librarian && $librarian->status === 'active' && ! $librarian->isExpired()) {
            return $librarian;
        }

        return null;
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

    public function userNotifications()
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    public function unreadNotifications()
    {
        return $this->userNotifications()->unread();
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Alias for librarySessions().
     * Use attendances() for general attendance queries.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
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
