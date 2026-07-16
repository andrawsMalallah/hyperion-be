<?php

namespace App\Notifications;

use App\Models\Exercise;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExerciseRejected extends Notification
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
        $message = (new MailMessage)
            ->subject('Update on your contributed exercise')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your contributed exercise "'.$this->exercise->name.'" was not accepted into the shared catalog.');

        // Only include a reason paragraph when the reviewer wrote one.
        if (filled($this->exercise->rejection_reason)) {
            $message->line('Reason: '.$this->exercise->rejection_reason);
        }

        return $message
            ->line('You can adjust and submit a new exercise from the Contribute page any time.')
            ->line('Thanks for contributing.');
    }
}
