<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Welcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct()
    {
        // Delay the welcome email by 5 seconds to avoid rate limiting
        $this->delay(now()->addSeconds(30));
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to CEIT Library Management System')
            ->view('mail.welcome');
    }
}
