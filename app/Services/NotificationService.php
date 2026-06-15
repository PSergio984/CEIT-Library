<?php

namespace App\Services;

use App\Mail\GenericNotification;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class NotificationService
{
    public function notify(User $user, string $category, string $title, string $message, array $data = [])
    {
        // 1. Create DB Notification (In-App)
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $category,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        // 2. Check Preferences
        $prefs = $user->notificationPreferences()->where('category', $category)->first();

        // 3. Email
        if ($prefs?->email) {
            Mail::to($user->email)->send(new GenericNotification($title, $message));
        }

        // 4. Push
        if ($prefs?->push) {
            $this->sendPush($user, $notification);
        }
    }

    protected function sendPush(User $user, Notification $notification)
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

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
    }
}
