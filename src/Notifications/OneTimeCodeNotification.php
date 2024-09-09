<?php

namespace coucounco\LaravelOtc\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use coucounco\LaravelOtc\Models\OtcToken;

class OneTimeCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $token;

    public function __construct(OtcToken $token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function getToken(): OtcToken
    {
        return $this->token;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->greeting('Hello,')
            ->line('Code :')
            ->line($this->getToken()->code)
            ->salutation('Bye');
    }
}
