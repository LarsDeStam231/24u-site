<?php

namespace App\Http\Controllers;

use App\DonationPayments;
use App\Models\Donation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MollieWebhookController extends Controller
{
    /**
     * Refresh a donation after Mollie reports that its payment changed.
     *
     * The request only names a payment. Its status is fetched from Mollie, and
     * unknown payments get the same response so the endpoint reveals nothing.
     */
    public function store(Request $request, DonationPayments $payments): Response
    {
        $paymentId = $request->input('id');

        if (is_string($paymentId)
            && preg_match('/^tr_[A-Za-z0-9]{1,64}$/', $paymentId) === 1
            && $payments->isConfigured()
            && Donation::query()->where('payment_id', $paymentId)->exists()) {
            $payments->sync($paymentId);
        }

        return response('OK');
    }
}
