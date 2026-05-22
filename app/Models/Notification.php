<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (Notification $notification) {
            $subscriptions = PushSubscription::where('user_id', $notification->user_id)->get();

            if ($subscriptions->isEmpty()) {
                return;
            }

            $auth = [
                'VAPID' => [
                    'subject' => config('webpush.vapid.subject'),
                    'publicKey' => config('webpush.vapid.public_key'),
                    'privateKey' => config('webpush.vapid.private_key'),
                ],
            ];

            try {
                $webPush = new WebPush($auth);

                foreach ($subscriptions as $sub) {
                    $webPush->queueNotification(
                        Subscription::create([
                            'endpoint' => $sub->endpoint,
                            'publicKey' => $sub->public_key,
                            'authToken' => $sub->auth_token,
                            'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                        ]),
                        json_encode([
                            'title' => $notification->title,
                            'body' => $notification->message,
                            'url' => $notification->data['url'] ?? '/notifications',
                        ])
                    );
                }

                foreach ($webPush->flush() as $report) {
                    if (! $report->isSuccess()) {
                        if ($report->isSubscriptionExpired()) {
                            PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->error('Web Push notification failed: '.$e->getMessage());
            }
        });
    }

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
