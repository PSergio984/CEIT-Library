<?php

namespace App\Models;

use Database\Factories\RuleHeaderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleHeader extends Model
{
    /** @use HasFactory<RuleHeaderFactory> */
    use HasFactory;

    protected $fillable = ['title', 'order'];

    public function ruleRegulations(): HasMany
    {
        // A header has many rules, sorted by their own order
        return $this->hasMany(RuleRegulation::class)->orderBy('order');
    }
}
