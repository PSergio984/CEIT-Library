<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Mail\GenericNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            try {
                Mail::to($user->email)->queue(new GenericNotification($title, $message));
            } catch (\Throwable $e) {
                Log::error('Failed to queue email notification: '.$e->getMessage());
            }
        }

        // 4. Push
        if ($prefs?->push) {
            try {
                SendPushNotificationJob::dispatch($user, $notification);
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch push notification job: '.$e->getMessage());
            }
        }
    }
}
