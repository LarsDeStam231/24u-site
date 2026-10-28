<?php

namespace App\Models;

use Database\Factories\PasskeyFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\LaravelPasskeys\Models\Passkey as BasePasskey;
use Spatie\LaravelPasskeys\Support\CredentialRecordConverter;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredentialSource;

class Passkey extends BasePasskey
{
    protected $hidden = ['data', 'credential_id'];

    protected static function newFactory(): Factory
    {
        return PasskeyFactory::new();
    }

    public function data(): Attribute
    {
        $serializer = Serializer::make();

        return new Attribute(
            get: fn (string $value): PublicKeyCredentialSource => CredentialRecordConverter::toPublicKeyCredentialSource(
                $serializer->fromJson($value, PublicKeyCredentialSource::class),
            ),
            set: fn (PublicKeyCredentialSource $value): array => [
                'credential_id' => self::encodeCredentialId($value->publicKeyCredentialId),
                'data' => $serializer->toJson($value),
            ],
        );
    }

    public static function encodeCredentialId(string $raw): string
    {
        return base64_encode($raw);
    }
}
