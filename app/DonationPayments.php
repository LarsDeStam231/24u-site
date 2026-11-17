<?php

namespace App;

use App\Models\Donation;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Mollie\Api\Exceptions\NotFoundException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;

class DonationPayments
{
    /**
     * Whether a well-formed Mollie API key is configured.
     */
    public function isConfigured(): bool
    {
        $key = config('mollie.key');

        return is_string($key) && preg_match('/^(test|live)_[A-Za-z0-9]{30,}$/', $key) === 1;
    }

    /**
     * Create the Mollie payment for a pending donation and return the checkout URL, if Mollie provides one.
     *
     * The return and webhook URLs are built from the configured application URL,
     * never from the incoming request, so a forged Host header cannot redirect them.
     */
    public function start(Donation $donation): ?string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $payload = [
            'amount' => ['currency' => 'EUR', 'value' => $donation->amount],
            'description' => 'Donatie '.config('event.event.name'),
            'redirectUrl' => $baseUrl.URL::signedRoute('donations.return', ['donation' => $donation], absolute: false),
            'locale' => 'nl_NL',
            'metadata' => ['donation_id' => $donation->id],
        ];

        if (str_starts_with($baseUrl, 'https://')) {
            $payload['webhookUrl'] = $baseUrl.route('api.mollie.webhook', [], false);
        }

        $payment = $this->client()->payments->create($payload);

        $donation->payment_id = $payment->id;
        $donation->save();

        return $payment->getCheckoutUrl();
    }

    /**
     * Copy the status that Mollie reports for a payment onto its donation.
     *
     * The status always comes from the Mollie API, so neither a webhook call
     * nor a visit to the return page can mark a donation as paid by itself.
     */
    public function sync(string $paymentId): ?Donation
    {
        try {
            $payment = $this->client()->payments->get($paymentId);
        } catch (NotFoundException) {
            return null;
        }

        return DB::transaction(function () use ($payment): ?Donation {
            $donation = Donation::query()->where('payment_id', $payment->id)->lockForUpdate()->first();

            if (! $donation) {
                return null;
            }

            if ($payment->amount->currency !== 'EUR' || $payment->amount->value !== $donation->amount) {
                Log::warning('Mollie payment amount does not match the donation.', ['donation_id' => $donation->id]);

                return $donation;
            }

            $donation->status = $this->statusFor($payment);
            $donation->payment_method = $payment->method instanceof BackedEnum ? $payment->method->value : $payment->method;
            $donation->save();

            return $donation;
        });
    }

    private function client(): MollieApiClient
    {
        return app(MollieApiClient::class);
    }

    private function statusFor(Payment $payment): string
    {
        if ($payment->isPaid()) {
            $returned = (float) ($payment->amountRefunded?->value ?? 0) + (float) ($payment->amountChargedBack?->value ?? 0);

            return $returned > 0 ? 'refunded' : 'completed';
        }

        $status = $payment->status instanceof PaymentStatus ? $payment->status->value : $payment->status;

        return match ($status) {
            'canceled' => 'cancelled',
            'expired' => 'expired',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
