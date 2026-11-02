<?php

namespace App;

use App\Models\FundraisingChallenge;
use App\Models\ProgramItem;
use Illuminate\Support\Facades\DB;

class EventContentCatalog
{
    public function seedProgramDefaults(): void
    {
        if (ProgramItem::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            foreach (config('event.event.schedule') as $sortOrder => $item) {
                ProgramItem::query()->firstOrCreate(
                    ['slug' => $item['id']],
                    [
                        'event_date' => $item['date'],
                        'time_range' => $item['time'],
                        'title' => $item['title'],
                        'description' => $item['description'],
                        'responsible' => $item['responsible'] ?? null,
                        'presenter' => $item['presenter'] ?? null,
                        'production' => $item['production'] ?? null,
                        'materials' => $item['materials'] ?? null,
                        'donation_challenge' => $item['donationChallenge'] ?? null,
                        'sort_order' => $sortOrder,
                    ],
                );
            }
        });
    }

    public function seedChallengeDefaults(): void
    {
        if (FundraisingChallenge::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            foreach (config('event.event.challenges') as $sortOrder => $challenge) {
                FundraisingChallenge::query()->firstOrCreate(
                    ['slug' => 'challenge-'.$challenge['amountCents']],
                    [
                        'amount_cents' => $challenge['amountCents'],
                        'name' => $challenge['name'],
                        'description' => $challenge['description'],
                        'sort_order' => $sortOrder,
                    ],
                );
            }
        });
    }
}
