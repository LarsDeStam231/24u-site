<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\Donor;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mollie\Api\Exceptions\ServiceUnavailableException;
use Mollie\Api\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DonationPaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PAYMENTS_URL = 'https://api.mollie.com/v2/payments';

    private const PAYMENT_URL = 'https://api.mollie.com/v2/payments/tr_test123';

    private const CHECKOUT_URL = 'https://www.mollie.com/checkout/select-method/test123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mollie.key' => 'test_'.str_repeat('a', 30), 'app.url' => 'https://24uur.example']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function molliePayment(array $overrides = []): array
    {
        return array_replace([
            'resource' => 'payment', 'id' => 'tr_test123', 'mode' => 'test', 'profileId' => 'pfl_test',
            'amount' => ['value' => '12.50', 'currency' => 'EUR'], 'description' => 'Donatie', 'status' => 'open',
            '_links' => ['checkout' => ['href' => self::CHECKOUT_URL, 'type' => 'text/html']],
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return ['amountCents' => 1250, 'name' => ' Jan Jansen ', 'email' => 'Jan@Example.com', 'anonymous' => true];
    }

    private function pendingDonation(): Donation
    {
        return Donation::factory()->create(['amount' => '12.50', 'payment_id' => 'tr_test123']);
    }

    public function test_a_donation_is_stored_as_pending_and_the_donor_is_sent_to_the_mollie_checkout(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::PAYMENTS_URL => Http::response($this->molliePayment(), 201)]);

        $this->postJson(route('donations.store'), $this->validPayload())
            ->assertCreated()->assertExactJson(['checkoutUrl' => self::CHECKOUT_URL]);

        $donation = Donation::query()->sole();
        $this->assertSame(['12.50', 'pending', 'tr_test123', true, null], [
            $donation->amount, $donation->status, $donation->payment_id, $donation->is_anonymous, $donation->user_id,
        ]);
        $this->assertDatabaseHas('donors', ['id' => $donation->donor_id, 'name' => 'Jan Jansen', 'email' => 'jan@example.com']);
        Http::assertSent(function (Request $request) use ($donation): bool {
            $payment = json_decode($request->body(), true);

            return $request->method() === 'POST' && $request->url() === self::PAYMENTS_URL
                && $request->hasHeader('Authorization', 'Bearer test_'.str_repeat('a', 30))
                && $payment['amount'] === ['currency' => 'EUR', 'value' => '12.50']
                && $payment['webhookUrl'] === 'https://24uur.example/api/mollie/webhook'
                && str_starts_with($payment['redirectUrl'], 'https://24uur.example/doneren/bedankt/'.$donation->id.'?signature=')
                && $payment['metadata'] === ['donation_id' => $donation->id];
        });
    }

    public function test_the_checkout_urls_ignore_a_forged_host_and_skip_the_webhook_without_https(): void
    {
        config(['app.url' => 'http://localhost:8000']);
        Http::preventStrayRequests();
        Http::fake([self::PAYMENTS_URL => Http::response($this->molliePayment(), 201)]);

        $this->withHeader('Host', 'evil.example')->postJson(route('donations.store'), $this->validPayload())->assertCreated();

        Http::assertSent(function (Request $request): bool {
            $payment = json_decode($request->body(), true);

            return ! array_key_exists('webhookUrl', $payment)
                && str_starts_with($payment['redirectUrl'], 'http://localhost:8000/doneren/bedankt/');
        });
    }

    public function test_a_donation_through_a_personal_link_counts_for_that_student(): void
    {
        $student = User::factory()->approved()->create(['role' => 'student', 'donation_token' => str_repeat('b', 64)]);
        Http::preventStrayRequests();
        Http::fake([self::PAYMENTS_URL => Http::response($this->molliePayment(), 201)]);

        $this->postJson(route('donations.store'), [...$this->validPayload(), 'student' => $student->donation_token])->assertCreated();

        $this->assertDatabaseHas('donations', ['user_id' => $student->id, 'status' => 'pending']);
    }

    public function test_a_personal_link_of_an_unapproved_student_is_rejected_before_mollie_is_called(): void
    {
        $student = User::factory()->create(['role' => 'student', 'donation_token' => str_repeat('c', 64)]);
        Http::preventStrayRequests();
        Http::fake();

        $this->postJson(route('donations.store'), [...$this->validPayload(), 'student' => $student->donation_token])
            ->assertUnprocessable()->assertJsonValidationErrors(['student' => 'Deze donatielink is ongeldig.']);

        $this->assertDatabaseCount('donations', 0);
        Http::assertNothingSent();
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function invalidPayloads(): array
    {
        $amount = 'Kies een bedrag tussen € 1 en € 5.000.';

        return [
            'below the minimum' => [['amountCents' => 99], 'amountCents', $amount],
            'above the maximum' => [['amountCents' => 500001], 'amountCents', $amount],
            'fractional cents' => [['amountCents' => 12.5], 'amountCents', $amount],
            'negative amount' => [['amountCents' => -1250], 'amountCents', $amount],
            'missing name' => [['name' => ''], 'name', 'Vul je naam in.'],
            'invalid email' => [['email' => 'geen-email'], 'email', 'Vul een geldig e-mailadres in.'],
            'malformed student link' => [['student' => 'kort'], 'student', 'Deze donatielink is ongeldig.'],
        ];
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_donations_are_rejected_before_mollie_is_called(array $overrides, string $field, string $message): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->postJson(route('donations.store'), [...$this->validPayload(), ...$overrides])
            ->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);

        $this->assertDatabaseCount('donations', 0);
        Http::assertNothingSent();
    }

    public function test_a_returning_donor_is_reused_by_email_address(): void
    {
        $donor = Donor::factory()->create(['name' => 'Jan Jansen', 'email' => 'jan@example.com']);
        Http::preventStrayRequests();
        Http::fake([self::PAYMENTS_URL => Http::response($this->molliePayment(), 201)]);

        $this->postJson(route('donations.store'), $this->validPayload())->assertCreated();

        $this->assertDatabaseCount('donors', 1);
        $this->assertDatabaseHas('donations', ['donor_id' => $donor->id]);
    }

    public function test_a_refused_mollie_payment_marks_the_donation_as_failed_without_leaking_details(): void
    {
        Exceptions::fake();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENTS_URL => Http::response(
            ['status' => 422, 'title' => 'Unprocessable Entity', 'detail' => 'The amount is higher than the maximum'], 422,
        )]);

        $this->postJson(route('donations.store'), $this->validPayload())->assertStatus(502)
            ->assertExactJson(['message' => 'De betaling kon niet worden gestart. Probeer het later opnieuw.']);

        $this->assertDatabaseHas('donations', ['status' => 'failed', 'payment_id' => null]);
        Exceptions::assertReported(ValidationException::class);
    }

    public function test_without_a_valid_api_key_donating_is_unavailable_and_the_form_is_not_offered(): void
    {
        config(['mollie.key' => 'geen-geldige-sleutel']);
        Http::preventStrayRequests();
        Http::fake();

        $this->postJson(route('donations.store'), $this->validPayload())->assertServiceUnavailable();
        $this->get(route('donations.index'))->assertOk()
            ->assertViewHas('pageData.links', fn (array $links): bool => ! array_key_exists('donationStore', $links));

        $this->assertDatabaseCount('donations', 0);
        Http::assertNothingSent();
    }

    public function test_with_a_valid_api_key_the_form_is_offered_without_exposing_the_key(): void
    {
        $this->get(route('donations.index'))->assertOk()
            ->assertViewHas('pageData.links.donationStore', route('donations.store'))
            ->assertViewHas('pageData.event.donation', ['minCents' => 100, 'maxCents' => 500000])
            ->assertDontSee('test_'.str_repeat('a', 30));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function mollieStatuses(): array
    {
        $paid = ['status' => 'paid', 'paidAt' => '2026-12-16T10:00:00+00:00', 'method' => 'ideal'];

        return [
            'open' => [['status' => 'open'], 'pending'],
            'pending' => [['status' => 'pending'], 'pending'],
            'paid' => [$paid, 'completed'],
            'canceled' => [['status' => 'canceled'], 'cancelled'],
            'expired' => [['status' => 'expired'], 'expired'],
            'failed' => [['status' => 'failed'], 'failed'],
            'refunded' => [[...$paid, 'amountRefunded' => ['value' => '12.50', 'currency' => 'EUR']], 'refunded'],
            'charged back' => [[...$paid, 'amountChargedBack' => ['value' => '12.50', 'currency' => 'EUR']], 'refunded'],
        ];
    }

    /** @param array<string, mixed> $payment */
    #[DataProvider('mollieStatuses')]
    public function test_the_webhook_copies_the_status_that_mollie_reports(array $payment, string $expectedStatus): void
    {
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENT_URL => Http::response($this->molliePayment($payment))]);

        $this->post(route('api.mollie.webhook'), ['id' => 'tr_test123', 'status' => 'paid'])->assertOk();

        $this->assertSame($expectedStatus, $donation->fresh()->status);
        $this->getJson(route('donations.total'))->assertExactJson(['raisedCents' => $expectedStatus === 'completed' ? 1250 : 0]);
    }

    public function test_a_repeated_webhook_for_a_paid_donation_counts_it_once_and_stores_the_payment_method(): void
    {
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENT_URL => Http::response($this->molliePayment([
            'status' => 'paid', 'paidAt' => '2026-12-16T10:00:00+00:00', 'method' => 'ideal',
        ]))]);

        $this->post(route('api.mollie.webhook'), ['id' => 'tr_test123'])->assertOk();
        $this->post(route('api.mollie.webhook'), ['id' => 'tr_test123'])->assertOk();

        $this->assertDatabaseHas('donations', ['id' => $donation->id, 'status' => 'completed', 'payment_method' => 'ideal']);
        $this->getJson(route('donations.total'))->assertExactJson(['raisedCents' => 1250]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function forgedWebhooks(): array
    {
        return [
            'unknown payment' => [['id' => 'tr_unknown']],
            'malformed id' => [['id' => '../payments?x=1']],
            'array id' => [['id' => ['tr_test123']]],
            'missing id' => [['status' => 'paid']],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('forgedWebhooks')]
    public function test_forged_webhooks_get_the_same_answer_without_reaching_mollie_or_changing_donations(array $payload): void
    {
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake();

        $this->post(route('api.mollie.webhook'), $payload)->assertOk()->assertContent('OK');

        $this->assertSame('pending', $donation->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_paid_payment_with_a_different_amount_does_not_complete_the_donation(): void
    {
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENT_URL => Http::response($this->molliePayment([
            'status' => 'paid', 'paidAt' => '2026-12-16T10:00:00+00:00', 'amount' => ['value' => '0.01', 'currency' => 'EUR'],
        ]))]);

        $this->post(route('api.mollie.webhook'), ['id' => 'tr_test123'])->assertOk();

        $this->assertSame('pending', $donation->fresh()->status);
    }

    public function test_the_webhook_fails_when_mollie_is_unavailable_so_mollie_retries_it(): void
    {
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENT_URL => Http::response(['status' => 503, 'title' => 'Service Unavailable'], 503)]);

        $this->post(route('api.mollie.webhook'), ['id' => 'tr_test123'])->assertServerError();

        $this->assertSame('pending', $donation->fresh()->status);
    }

    public function test_the_return_page_shows_the_status_confirmed_by_mollie(): void
    {
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENT_URL => Http::response($this->molliePayment([
            'status' => 'paid', 'paidAt' => '2026-12-16T10:00:00+00:00',
        ]))]);

        $this->get(URL::signedRoute('donations.return', ['donation' => $donation], absolute: false))->assertOk()
            ->assertViewHas('pageData.page.donationResult', ['status' => 'completed', 'amountCents' => 1250])
            ->assertViewHas('pageData.event.fundraising.raisedCents', 1250)
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame('completed', $donation->fresh()->status);
    }

    public function test_the_return_page_stays_available_and_pending_when_mollie_cannot_be_reached(): void
    {
        Exceptions::fake();
        $donation = $this->pendingDonation();
        Http::preventStrayRequests();
        Http::fake([self::PAYMENT_URL => Http::response(['status' => 503, 'title' => 'Service Unavailable'], 503)]);

        $this->get(URL::signedRoute('donations.return', ['donation' => $donation], absolute: false))->assertOk()
            ->assertViewHas('pageData.page.donationResult', ['status' => 'pending', 'amountCents' => 1250]);

        Exceptions::assertReported(ServiceUnavailableException::class);
    }

    public function test_the_return_page_does_not_ask_mollie_again_about_a_settled_donation(): void
    {
        $donation = Donation::factory()->completed()->create(['amount' => '12.50', 'payment_id' => 'tr_test123']);
        Http::preventStrayRequests();
        Http::fake();

        $this->get(URL::signedRoute('donations.return', ['donation' => $donation], absolute: false))->assertOk()
            ->assertViewHas('pageData.page.donationResult.status', 'completed');

        Http::assertNothingSent();
    }

    public function test_the_return_page_rejects_links_without_a_valid_signature(): void
    {
        $donation = $this->pendingDonation();
        $other = Donation::factory()->create(['payment_id' => 'tr_other']);
        $signedPath = URL::signedRoute('donations.return', ['donation' => $donation], absolute: false);
        Http::preventStrayRequests();
        Http::fake();

        $this->get(route('donations.return', ['donation' => $donation]))->assertForbidden();
        $this->get(str_replace('/'.$donation->id.'?', '/'.$other->id.'?', $signedPath))->assertForbidden();

        Http::assertNothingSent();
    }
}
