<?php

namespace App\Http\Controllers;

use App\EventPageData;
use Illuminate\View\View;

class ProgramController extends Controller
{
    public function index(EventPageData $eventPageData): View
    {
        return view('site', ['pageData' => $eventPageData->forPage('program')]);
    }
}
