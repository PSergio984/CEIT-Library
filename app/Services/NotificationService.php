<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Mail\GenericNotification;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notify(User $user, string $category, string $title, string $message, array $data = [])
    {
        // 1. Create DB Notification (In-App)
        Notification::create([
            'user_id' => $user->id,
            'type' => $category,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        // 2. Check Preferences
        $prefs = $user->notificationPreferences()->where('category', $category)->first();

        if ($prefs?->email) {
            Mail::to($user->email)->send(new GenericNotification($title, $message));
        }

        // Push implementation deferred
    }
}
