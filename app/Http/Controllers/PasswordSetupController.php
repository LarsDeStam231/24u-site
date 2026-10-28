<?php

namespace App\Http\Controllers;

use App\EventPageData;
use App\Models\User;
use App\QrCredentials;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordSetupController extends Controller
{
    public function create(EventPageData $pages): View
    {
        $pageData = $pages->forPage('home');
        $pageData['page'] = [
            'name' => 'password-setup', 'heading' => 'Wachtwoord instellen',
            'description' => 'Kies een wachtwoord voor jouw goedgekeurde account.',
            'documentTitle' => 'Wachtwoord instellen — DCTerra 24 Uur Live',
            'canonical' => route('password.reset'), 'submitUrl' => route('password.update'),
        ];

        return view('site', compact('pageData'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);
        Password::sendResetLink(['email' => Str::lower(trim($data['email'])), 'registration_status' => 'approved']);

        return response()->json(['message' => 'Als dit e-mailadres bij een goedgekeurd account hoort, ontvang je een link om je wachtwoord in te stellen.'], 202);
    }

    public function update(Request $request, QrCredentials $credentials): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:120', 'not_regex:/[<>]/'],
            'password' => ['required', 'string', 'max:72', 'confirmed', PasswordRule::min(12), function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Gebruik een wachtwoord van maximaal 72 bytes; sommige tekens tellen als meerdere bytes.');
                }
            }],
            'password_confirmation' => ['required', 'string', 'max:72'],
        ]);
        $status = Password::reset([
            'token' => $data['token'], 'password' => $data['password'],
            'password_confirmation' => $data['password_confirmation'],
            'email' => Str::lower(trim($data['email'])), 'registration_status' => 'approved',
        ], function (User $user, string $password) use ($data, $credentials): void {
            $user->password = $password;
            $user->email_verified_at ??= now();
            if (! $user->name && ! empty($data['name'])) {
                $user->name = trim($data['name']);
            }
            $user->setRememberToken(Str::random(60));
            $credentials->ensurePublicCodes($user);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['token' => 'Deze link is ongeldig of verlopen. Vraag een nieuwe e-mail aan.']);
        }

        return response()->json(['redirect' => route('login')]);
    }
}
