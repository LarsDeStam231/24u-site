<?php

namespace App\Models;

use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    /** @return array{amount: string, is_anonymous: string} */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_anonymous' => 'boolean'];
    }

    /** @return BelongsTo<Donor, $this> */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public static function completedAmountCents(): int
    {
        return (int) round((float) static::query()->where('status', 'completed')->sum('amount') * 100);
    }
}
