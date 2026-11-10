<?php

namespace App;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QrPageData
{
    public function __construct(private EventPageData $eventPageData) {}

    /** @param array<string, mixed> $data */
    public function render(string $mode, string $heading, array $data = []): View
    {
        $pageData = $this->eventPageData->forPage('home');
        $pageData['page'] = [
            'name' => 'qr', 'heading' => $heading, 'description' => 'Jouw account bij DCTerra 24 Uur Live.',
            'documentTitle' => $heading.' — DCTerra 24 Uur Live', 'canonical' => url()->current(),
        ];
        $pageData['qr'] = ['mode' => $mode, 'authenticated' => auth()->check(), 'endpoints' => [
            'login' => route('login.store'), 'loginPage' => route('qr.login'), 'studentLogin' => route('students.login.store'),
            'activate' => route('qr.activate.store'), 'invite' => route('qr.invite'), 'register' => route('registration.store'),
            'pairPage' => route('qr.pair'), 'pairCreate' => route('qr.pair.store'),
            'pairApprove' => route('qr.pair.update'), 'pairConsume' => route('qr.pair.consume'),
            'account' => route('account.index'), 'logout' => route('logout'),
            'personalDonationTotal' => route('account.donations.total'),
            'attendance' => route('attendance.index'), 'attendanceStore' => route('attendance.store'),
            'requests' => route('account.requests'), 'inviteSelected' => route('account.invite-selected'),
            'content' => route('account.content.index'),
            'programStore' => route('account.content.program.store'),
            'programUpdate' => route('account.content.program.update', ['programItem' => '__PROGRAM_ITEM__']),
            'programDelete' => route('account.content.program.destroy', ['programItem' => '__PROGRAM_ITEM__']),
            'challengeStore' => route('account.content.challenges.store'),
            'challengeUpdate' => route('account.content.challenges.update', ['challenge' => '__CHALLENGE__']),
            'challengeDelete' => route('account.content.challenges.destroy', ['challenge' => '__CHALLENGE__']),
            'sponsorStore' => route('account.content.sponsors.store'),
            'sponsorUpdate' => route('account.content.sponsors.update', ['sponsor' => '__SPONSOR__']),
            'sponsorDelete' => route('account.content.sponsors.destroy', ['sponsor' => '__SPONSOR__']),
            'studentsLoginPage' => route('students.login'),
            'loginOptions' => route('login.options'), 'passwordMail' => route('password.email'),
            'passwordLogin' => route('password.login.store'),
            'pairOptions' => route('qr.pair.options'),
            'pairCancel' => route('qr.pair.destroy'),
            'passkeys' => route('passkeys.index'), 'passkeyCreate' => route('passkeys.store'),
            'registrationAuthorize' => route('passkeys.registration.authorize'),
            'registrationOptions' => route('passkeys.registration.options'), 'registrationStore' => route('passkeys.registration.store'),
        ], 'canManageContent' => Gate::allows('manage-event-content'),
            'canManageChallengeLadder' => Gate::allows('manage-challenge-ladder'), ...$data];

        return view('site', compact('pageData'));
    }
}
