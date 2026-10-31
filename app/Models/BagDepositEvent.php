<?php

namespace App\Models;

use Database\Factories\BagDepositEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bag_deposit_id
 * @property string $type
 * @property int|null $actor_user_id
 * @property int|null $scanned_by
 * @property int|null $scanned_by_admin_id
 * @property string|null $method
 * @property string|null $note
 * @property bool $id_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BagDeposit $deposit
 * @property-read User|null $actor
 * @property-read Librarian|null $scannedByLibrarian
 * @property-read User|null $scannedByAdmin
 *
 * @mixin \Eloquent
 */
class BagDepositEvent extends Model
{
    /** @use HasFactory<BagDepositEventFactory> */
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_CLAIM = 'claim';

    public const TYPE_TEMP_ACCESS = 'temp_access';

    public const TYPE_OVERRIDE = 'override';

    protected $fillable = [
        'bag_deposit_id',
        'type',
        'actor_user_id',
        'scanned_by',
        'scanned_by_admin_id',
        'method',
        'note',
        'id_verified',
    ];

    protected $casts = [
        'id_verified' => 'boolean',
    ];

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(BagDeposit::class, 'bag_deposit_id');
    }

    /**
     * The Courier — the user who physically interacted with the counter
     * (depositor at drop-off, claimant at pickup).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function scannedByLibrarian(): BelongsTo
    {
        return $this->belongsTo(Librarian::class, 'scanned_by');
    }

    public function scannedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_admin_id');
    }
}
