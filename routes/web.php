<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LivestreamController;
use App\Http\Controllers\LoginOptionsController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\PasskeyRegistrationController;
use App\Http\Controllers\PasswordSessionController;
use App\Http\Controllers\PasswordSetupController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\QrAccountController;
use App\Http\Controllers\QrActivationController;
use App\Http\Controllers\QrPairingController;
use App\Http\Controllers\QrSessionController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\StudentDonationController;
use App\Http\Middleware\EnsureApprovedAccount;
use App\Http\Middleware\ProtectQrResponses;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/livestream', [LivestreamController::class, 'index'])->name('livestream.index');
Route::get('/doneren', [DonationController::class, 'index'])->name('donations.index');
Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.index');
Route::get('/programma', [ProgramController::class, 'index'])->name('program.index');
Route::get('/sponsoren', [SponsorController::class, 'index'])->name('sponsors.index');
Route::get('/over-ons', [AboutController::class, 'index'])->name('about.index');

Route::middleware(ProtectQrResponses::class)->group(function (): void {
    Route::redirect('/inloggen/wachtwoord', '/inloggen')->name('password.login');
    Route::get('/inloggen', [PasswordSessionController::class, 'create'])->name('login');
    Route::post('/inloggen', [PasswordSessionController::class, 'store'])->middleware('throttle:password-login')->name('password.login.store');
    Route::post('/inloggen/opties', [LoginOptionsController::class, 'store'])->middleware('throttle:20,1')->name('login.options');
    Route::get('/wachtwoord/instellen', [PasswordSetupController::class, 'create'])->name('password.reset');
    Route::post('/wachtwoord/e-mail', [PasswordSetupController::class, 'store'])->middleware('throttle:qr-mail')->name('password.email');
    Route::post('/wachtwoord/instellen', [PasswordSetupController::class, 'update'])->middleware('throttle:password-login')->name('password.update');
    Route::get('/inloggen/qr', [QrSessionController::class, 'create'])->name('qr.login');
    Route::get('/studenten/inloggen', [QrSessionController::class, 'create'])->name('students.login');
    Route::post('/inloggen/qr', [QrSessionController::class, 'store'])->middleware('throttle:qr-login')->name('login.store');
    Route::post('/studenten/inloggen', [QrSessionController::class, 'store'])->middleware('throttle:qr-login')->name('students.login.store');
    Route::get('/activeren', [QrActivationController::class, 'create'])->name('qr.activate');
    Route::post('/activeren', [QrActivationController::class, 'store'])->middleware('throttle:qr-login')->name('qr.activate.store');
    Route::post('/activatielink', [QrActivationController::class, 'invite'])->middleware('throttle:qr-mail')->name('qr.invite');
    Route::get('/registreren', [RegistrationController::class, 'create'])->name('registration.create');
    Route::post('/registreren', [RegistrationController::class, 'store'])->middleware('throttle:qr-mail')->name('registration.store');
    Route::get('/computer-koppelen', [QrPairingController::class, 'create'])->name('qr.pair');
    Route::post('/computer-koppelen/nieuw', [QrPairingController::class, 'store'])->middleware('throttle:10,1')->name('qr.pair.store');
    Route::post('/computer-koppelen/status', [QrPairingController::class, 'consume'])->middleware('throttle:qr-poll')->name('qr.pair.consume');
    Route::delete('/computer-koppelen', [QrPairingController::class, 'destroy'])->middleware('throttle:20,1')->name('qr.pair.destroy');
    Route::post('/computer-koppelen/opties', [QrPairingController::class, 'options'])->middleware('throttle:qr-login')->name('qr.pair.options');
    Route::post('/computer-koppelen/bevestigen', [QrPairingController::class, 'update'])->middleware('throttle:qr-login')->name('qr.pair.update');
    Route::get('/passkey-koppelen', [PasskeyRegistrationController::class, 'create'])->name('passkeys.registration');
    Route::post('/passkey-koppelen/account', [PasskeyRegistrationController::class, 'authorizeAccount'])->middleware('throttle:password-login')->name('passkeys.registration.authorize');
    Route::post('/passkey-koppelen/opties', [PasskeyRegistrationController::class, 'options'])->middleware('throttle:qr-login')->name('passkeys.registration.options');
    Route::post('/passkey-koppelen', [PasskeyRegistrationController::class, 'store'])->middleware('throttle:qr-login')->name('passkeys.registration.store');
    Route::get('/aanwezigheid', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/uitloggen', [QrSessionController::class, 'destroy'])->middleware('auth')->name('logout');
    Route::get('/studenten/doneren/{student:donation_token}', [StudentDonationController::class, 'show'])
        ->where('student', '[A-Za-z0-9]{64}')->name('students.donation');

    Route::middleware(['auth', EnsureApprovedAccount::class])->group(function (): void {
        Route::get('/account', [QrAccountController::class, 'index'])->name('account.index');
        Route::get('/account/passkeys', [PasskeyController::class, 'index'])->name('passkeys.index');
        Route::post('/account/passkeys', [PasskeyController::class, 'store'])->middleware('throttle:10,1')->name('passkeys.store');
        Route::delete('/account/passkeys/{passkey}', [PasskeyController::class, 'destroy'])->middleware('throttle:password-login')->name('passkeys.destroy');
        Route::post('/aanwezigheid', [AttendanceController::class, 'store'])->middleware('throttle:60,1')->name('attendance.store');
        Route::get('/account/aanvragen', [QrAccountController::class, 'requests'])->name('account.requests');
        Route::post('/account/studenten-uitnodigen', [QrAccountController::class, 'inviteSelected'])->middleware('throttle:3,1')->name('account.invite-selected');
        Route::patch('/account/aanvragen/{user}', [QrAccountController::class, 'update'])->middleware('throttle:20,1')->name('account.approve');
        Route::post('/account/aanvragen/{user}/uitnodigen', [QrAccountController::class, 'invite'])->middleware('throttle:10,1')->name('account.invite');
    });
});
