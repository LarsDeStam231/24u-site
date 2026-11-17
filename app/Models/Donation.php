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

    /** @var list<string> */
    public const STATUSES = ['pending', 'completed', 'failed', 'cancelled', 'refunded', 'expired'];

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

    /**
     * The student whose personal donation page was used, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function completedAmountCents(): int
    {
        return (int) round((float) static::query()->where('status', 'completed')->sum('amount') * 100);
    }
}
