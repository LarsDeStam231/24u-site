<?php

namespace App\Models;

use Database\Factories\FundraisingChallengeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FundraisingChallenge extends Model
{
    /** @use HasFactory<FundraisingChallengeFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'amount_cents', 'name', 'description', 'sort_order'];

    /** @return array{amount_cents: string} */
    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }
}
