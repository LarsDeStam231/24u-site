<?php

use App\Http\Controllers\DiscordVerificationController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Middleware\EnsureDiscordBotToken;
use Illuminate\Support\Facades\Route;

Route::post('/discord/verify', [DiscordVerificationController::class, 'store'])
    ->middleware([EnsureDiscordBotToken::class, 'throttle:60,1'])
    ->name('api.discord.verify');

Route::post('/mollie/webhook', [MollieWebhookController::class, 'store'])
    ->middleware('throttle:600,1')
    ->name('api.mollie.webhook');
