<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleRegulation extends Model
{
    /** @use HasFactory<\Database\Factories\RuleRegulationFactory> */
    use HasFactory;

    protected $fillable = [
        'rule_header_id',
        'content',
    ];

    public function ruleHeader(): BelongsTo
    {
        return $this->belongsTo(RuleHeader::class);
    }

}
