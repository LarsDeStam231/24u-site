<?php

namespace App\Http\Controllers;

use App\EventPageData;
use App\FundraisingTotal;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DonationController extends Controller
{
    public function index(EventPageData $eventPageData): View
    {
        return view('site', ['pageData' => $eventPageData->forPage('donations')]);
    }

    public function total(FundraisingTotal $total): JsonResponse
    {
        return response()->json(['raisedCents' => $total->currentRaisedCents()])->header('Cache-Control', 'no-store');
    }
}
