<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $content
    ) {}

    public function build()
    {
        return $this->subject($this->title)
            ->text($this->content);
    }
}
