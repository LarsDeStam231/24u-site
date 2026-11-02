<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasskeyChanged extends Notification
{
    public function __construct(public string $change, public string $name) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Passkey '.$this->change.' — 24 Uur Live')
            ->line('Er is een passkey '.$this->change.' aan jouw account: '.$this->name.'.')
            ->action('Passkeys beheren', route('passkeys.index'))
            ->line('Heb je dit niet zelf gedaan? Wijzig je wachtwoord en verwijder onbekende passkeys.');
    }
}
