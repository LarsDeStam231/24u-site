<?php

namespace App;

use App\Models\Donation;
use App\Models\User;

class FundraisingTotal
{
    public function currentRaisedCents(): int
    {
        return Donation::completedAmountCents();
    }

    public function raisedCentsForStudent(User $student): int
    {
        return (int) round((float) $student->donations()->where('status', 'completed')->sum('amount') * 100);
    }
}
