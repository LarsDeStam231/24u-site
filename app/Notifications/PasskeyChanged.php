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
        $brandName = config('event.event.name');
        $preposition = $this->change === 'verwijderd' ? 'van' : 'aan';

        return (new MailMessage)
            ->from(config('mail.from.address'), $brandName)
            ->subject('Online sleutel '.$this->change.' — '.$brandName)
            ->greeting('Hallo!')
            ->line('Er is een online sleutel '.$this->change.' '.$preposition.' jouw account: '.$this->name.'.')
            ->action('Online sleutels beheren', route('passkeys.index'))
            ->line('Heb je dit niet zelf gedaan? Wijzig je wachtwoord en verwijder onbekende online sleutels.')
            ->salutation('Met vriendelijke groet,')
            ->view(['mail.passkey-changed', 'mail.passkey-changed-text'], [
                'brandName' => $brandName,
            ]);
    }
}
