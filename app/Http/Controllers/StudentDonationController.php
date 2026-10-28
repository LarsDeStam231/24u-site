<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\QrPageData;
use Illuminate\View\View;

class StudentDonationController extends Controller
{
    public function show(User $student, QrPageData $pages): View
    {
        abort_unless($student->isApproved() && $student->role === 'student' && $student->name, 404);

        return $pages->render('donation', 'Steun '.$student->name, [
            'studentName' => $student->name,
            'donateUrl' => config('event.event.foundation.donateUrl'),
            'rankingAvailable' => false,
        ]);
    }
}
