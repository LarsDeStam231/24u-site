<?php

namespace App;

use App\Models\Donation;

class FundraisingTotal
{
    public function currentRaisedCents(): int
    {
        return Donation::completedAmountCents();
    }
}
