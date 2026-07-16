<?php

namespace App\Notifications;

use App\Models\Exercise;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExerciseApproved extends Notification
{
    use Queueable;

    public function __construct(public Exercise $exercise) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your exercise was approved')
            ->greeting('Good news!')
            ->line('Your contributed exercise "'.$this->exercise->name.'" has been approved.')
            ->line('It is now part of the shared catalog and available to everyone in Hyperion.')
            ->line('Thanks for helping build the exercise library.');
    }
}
