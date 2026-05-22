<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

class SendPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-push-notification {user : The ID or email of the user} {message=This is a test push notification.} {--title=Test Notification} {--url=/notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually send a push notification to a specified user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userInput = $this->argument('user');
        $message = $this->argument('message');
        $title = $this->option('title');
        $url = $this->option('url');

        $user = is_numeric($userInput)
            ? User::find($userInput)
            : User::where('email', $userInput)->first();

        if (! $user) {
            $this->error("User not found: {$userInput}");

            return 1;
        }

        $subscriptionCount = $user->pushSubscriptions()->count();
        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Active push subscriptions: {$subscriptionCount}");

        if ($subscriptionCount === 0) {
            $this->warn('This user has not registered any push subscriptions. The notification will be saved but no push message will be delivered.');
        }

        Notification::create([
            'user_id' => $user->id,
            'type' => 'manual_push',
            'title' => $title,
            'message' => $message,
            'data' => [
                'url' => $url,
            ],
        ]);

        $this->info('Notification sent and saved successfully!');

        return 0;
    }
}
