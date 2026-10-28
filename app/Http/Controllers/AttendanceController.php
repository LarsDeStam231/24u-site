<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\QrPageData;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(QrPageData $pages): View
    {
        return $pages->render('attendance', 'Aanwezigheid scannen');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('scan-attendance');
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
            'action' => ['required', 'in:check-in,check-out'],
        ]);
        abort_unless(now()->betweenIncluded(CarbonImmutable::parse(config('event.event.startsAt')),
            CarbonImmutable::parse(config('event.event.endsAt'))), 422, 'Aanwezigheid registreren kan alleen tijdens het evenement.');

        return Cache::lock('qr:attendance:'.hash('sha256', $data['token']), 10)->block(3, function () use ($data, $request): JsonResponse {
            return DB::transaction(function () use ($data, $request): JsonResponse {
                $user = User::query()->where('attendance_token', $data['token'])
                    ->where('registration_status', 'approved')->lockForUpdate()->firstOrFail();
                $isPresent = $data['action'] === 'check-in';
                $changed = $user->is_present !== $isPresent;
                if ($changed) {
                    $user->is_present = $isPresent;
                    $user->save();
                    DB::table('attendance_logs')->insert([
                        'user_id' => $user->id, 'scanned_by' => $request->user()->id,
                        'action' => $data['action'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return response()->json(['name' => $user->name, 'isPresent' => $user->is_present,
                    'message' => $changed ? 'Aanwezigheid bijgewerkt.' : 'Deze aanwezigheid was al geregistreerd.']);
            });
        });
    }
}
