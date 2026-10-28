<?php

namespace App\Http\Controllers;

use App\EventPageData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(EventPageData $eventPageData): View
    {
        $pageData = $eventPageData->forPage('home');
        $pageData['page'] = [
            'name' => 'registration',
            'heading' => 'Meld je aan voor 24 Uur Live',
            'description' => 'Vraag een account aan. Een beheerder keurt je aanmelding goed en wijst je een rol toe.',
            'documentTitle' => 'Registreren — DCTerra 24 Uur Live',
            'canonical' => route('registration.create'),
            'submitUrl' => route('registration.store'),
        ];

        return view('site', compact('pageData'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        User::firstOrCreate(['email' => Str::lower($data['email'])]);

        return response()->json(['message' => 'Je aanvraag is ontvangen. Een beheerder moet je account eerst goedkeuren.'], 202);
    }
}
