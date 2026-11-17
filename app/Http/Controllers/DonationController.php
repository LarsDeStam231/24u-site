<?php

namespace App\Http\Controllers;

use App\DonationPayments;
use App\EventPageData;
use App\FundraisingTotal;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Mollie\Api\Exceptions\MollieException;

class DonationController extends Controller
{
    public function index(EventPageData $eventPageData): View
    {
        return view('site', ['pageData' => $eventPageData->forPage('donations')]);
    }

    public function total(FundraisingTotal $total): JsonResponse
    {
        return response()->json(['raisedCents' => $total->currentRaisedCents()])->header('Cache-Control', 'no-store');
    }

    /**
     * Register a pending donation and hand the donor over to the Mollie checkout.
     */
    public function store(Request $request, DonationPayments $payments): JsonResponse
    {
        abort_unless($payments->isConfigured(), 503, 'Online doneren is nog niet beschikbaar.');

        $limits = config('event.event.donation');
        $data = $request->validate([
            'amountCents' => ['required', 'integer', 'min:'.$limits['minCents'], 'max:'.$limits['maxCents']],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'anonymous' => ['sometimes', 'boolean'],
            'student' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{64}$/'],
        ], [
            'amountCents.*' => 'Kies een bedrag tussen € '.$this->euros($limits['minCents']).' en € '.$this->euros($limits['maxCents']).'.',
            'name.*' => 'Vul je naam in.',
            'email.*' => 'Vul een geldig e-mailadres in.',
            'student.*' => 'Deze donatielink is ongeldig.',
        ]);

        $student = isset($data['student']) ? $this->studentFor($data['student']) : null;

        $donation = new Donation;
        $donation->donor_id = $this->donorFor(trim($data['name']), Str::lower($data['email']))->id;
        $donation->user_id = $student?->id;
        $donation->amount = number_format($data['amountCents'] / 100, 2, '.', '');
        $donation->is_anonymous = $request->boolean('anonymous');
        $donation->status = 'pending';
        $donation->save();

        try {
            $checkoutUrl = $payments->start($donation);
        } catch (MollieException $exception) {
            report($exception);
            $checkoutUrl = null;
        }

        if (! $checkoutUrl) {
            $donation->status = 'failed';
            $donation->save();

            return response()->json(['message' => 'De betaling kon niet worden gestart. Probeer het later opnieuw.'], 502);
        }

        return response()->json(['checkoutUrl' => $checkoutUrl], 201);
    }

    /**
     * Show the donor the outcome of their payment after returning from Mollie.
     */
    public function show(Donation $donation, EventPageData $eventPageData, DonationPayments $payments): View
    {
        if ($donation->status === 'pending' && $donation->payment_id && $payments->isConfigured()) {
            $donation = rescue(fn (): ?Donation => $payments->sync($donation->payment_id), $donation) ?? $donation;
        }

        $pageData = $eventPageData->forPage('donations');
        $pageData['page']['donationResult'] = [
            'status' => $donation->status,
            'amountCents' => (int) round((float) $donation->amount * 100),
        ];

        return view('site', compact('pageData'));
    }

    private function studentFor(string $donationToken): User
    {
        $student = User::query()->where('donation_token', $donationToken)->first();

        if (! $student || ! $student->isApproved() || $student->role !== 'student' || ! $student->name) {
            throw ValidationException::withMessages(['student' => 'Deze donatielink is ongeldig.']);
        }

        return $student;
    }

    private function donorFor(string $name, string $email): Donor
    {
        $donor = Donor::query()->where('email', $email)->first();

        if ($donor) {
            return $donor;
        }

        $donor = new Donor;
        $donor->name = $name;
        $donor->email = $email;

        try {
            $donor->save();
        } catch (UniqueConstraintViolationException) {
            return Donor::query()->where('email', $email)->firstOrFail();
        }

        return $donor;
    }

    private function euros(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', '.');
    }
}
