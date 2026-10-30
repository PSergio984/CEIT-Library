<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $number
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BagDeposit|null $openDeposit
 * @property-read Collection<int, BagDeposit> $deposits
 *
 * @mixin \Eloquent
 */
class BagSlot extends Model
{
    public const STATE_FREE = 'free';

    public const STATE_OCCUPIED = 'occupied';

    public const STATE_OUT_OF_SERVICE = 'out_of_service';

    protected $fillable = [
        'number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function deposits(): HasMany
    {
        return $this->hasMany(BagDeposit::class);
    }

    public function openDeposit(): HasOne
    {
        return $this->hasOne(BagDeposit::class)->where('status', BagDeposit::STATUS_OPEN);
    }

    /**
     * Slot state is derived: a slot carries no status column of its own —
     * occupancy comes from the open deposit bound to it.
     */
    public function state(): string
    {
        if (! $this->is_active) {
            return self::STATE_OUT_OF_SERVICE;
        }

        return $this->openDeposit()->exists() ? self::STATE_OCCUPIED : self::STATE_FREE;
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ! $this->openDeposit()->exists();
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->whereDoesntHave('openDeposit');
    }
}
