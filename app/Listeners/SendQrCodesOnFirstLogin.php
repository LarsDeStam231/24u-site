<?php

namespace App\Listeners;

use App\Models\User;
use App\QrCredentials;
use Illuminate\Auth\Events\Login;
use Throwable;

class SendQrCodesOnFirstLogin
{
    public function __construct(private QrCredentials $credentials) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        try {
            $this->credentials->emailShareableCodesOnce($event->user);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
