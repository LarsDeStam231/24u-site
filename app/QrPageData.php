<?php

namespace App;

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
            'login' => route('login.store'), 'loginPage' => route('login'), 'studentLogin' => route('students.login.store'),
            'activate' => route('qr.activate.store'), 'invite' => route('qr.invite'), 'register' => route('registration.store'),
            'pairPage' => route('qr.pair'), 'pairCreate' => route('qr.pair.store'),
            'pairApprove' => route('qr.pair.update'), 'pairConsume' => route('qr.pair.consume'),
            'account' => route('account.index'), 'logout' => route('logout'),
            'attendance' => route('attendance.index'), 'attendanceStore' => route('attendance.store'),
            'requests' => route('account.requests'), 'inviteSelected' => route('account.invite-selected'),
            'studentsLoginPage' => route('students.login'),
        ], ...$data];

        return view('site', compact('pageData'));
    }
}
