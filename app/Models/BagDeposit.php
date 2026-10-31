<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int $bag_slot_id
 * @property int $item_count
 * @property string|null $description
 * @property string $claim_code
 * @property string|null $photo_path
 * @property string $status
 * @property int|null $claimed_by
 * @property Carbon|null $claimed_at
 * @property string|null $claim_method
 * @property Carbon|null $unclaimed_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read BagSlot $slot
 * @property-read User|null $claimedBy
 * @property-read Collection<int, BagDepositEvent> $events
 *
 * @mixin \Eloquent
 */
class BagDeposit extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLAIMED = 'claimed';

    public const METHOD_OWNER_SCAN = 'owner_scan';

    public const METHOD_CLAIM_CODE = 'claim_code';

    public const METHOD_RESCUE_LOOKUP = 'rescue_lookup';

    protected $fillable = [
        'user_id',
        'bag_slot_id',
        'item_count',
        'description',
        'claim_code',
        'photo_path',
        'status',
        'claimed_by',
        'claimed_at',
        'claim_method',
        'unclaimed_notified_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'unclaimed_notified_at' => 'datetime',
    ];

    // Bearer credential — anyone holding it can collect the bag.
    protected $hidden = [
        'claim_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(BagSlot::class, 'bag_slot_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BagDepositEvent::class);
    }

    /**
     * @param  Builder<BagDeposit>  $query
     * @return Builder<BagDeposit>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
