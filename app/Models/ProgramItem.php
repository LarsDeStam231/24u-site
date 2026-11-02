<?php

namespace App\Models;

use Database\Factories\ProgramItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramItem extends Model
{
    /** @use HasFactory<ProgramItemFactory> */
    use HasFactory;

    protected $fillable = [
        'slug', 'event_date', 'time_range', 'title', 'description', 'responsible', 'presenter',
        'production', 'materials', 'donation_challenge', 'sort_order',
    ];

    /** @return array{event_date: string} */
    protected function casts(): array
    {
        return ['event_date' => 'date:Y-m-d'];
    }
}
