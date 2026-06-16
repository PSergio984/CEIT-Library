<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Notification $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $subscriptions = PushSubscription::where('user_id', $this->user->id)->get();

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
                        'title' => $this->notification->title,
                        'body' => $this->notification->message,
                        'url' => $this->notification->data['url'] ?? '/notifications',
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
    }
}
