<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
    ];

    /**
     * Get the user that owns the conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the messages belonging to the conversation, in insertion order.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * Auto-title a conversation from the first user message (D-18),
     * truncated to 120 characters with a fallback when blank.
     */
    public static function makeTitle(string $content): string
    {
        $title = mb_substr(trim($content), 0, 120);

        return $title === '' ? 'New conversation' : $title;
    }
}
