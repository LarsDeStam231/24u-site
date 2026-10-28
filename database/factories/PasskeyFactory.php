<?php

namespace Database\Factories;

use App\Models\Passkey;
use Spatie\LaravelPasskeys\Database\Factories\PasskeyFactory as BasePasskeyFactory;

class PasskeyFactory extends BasePasskeyFactory
{
    protected $model = Passkey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $attributes = parent::definition();
        $attributes['data']->publicKeyCredentialId = random_bytes(32);

        return $attributes;
    }
}
