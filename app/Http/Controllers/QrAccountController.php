<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\QrCredentials;
use App\QrPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QrAccountController extends Controller
{
    public function index(Request $request, QrPageData $pages, QrCredentials $credentials): View
    {
        $user = $request->user();

        return $pages->render('account', 'Mijn account', [
            'account' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role,
                'isPresent' => $user->is_present, 'studentNumber' => $user->student_number],
            'codes' => $credentials->publicCodes($user),
            'history' => DB::table('attendance_logs')->where('user_id', $user->id)->orderByDesc('id')
                ->limit(50)->get(['action', 'created_at'])->all(),
            'canManage' => Gate::allows('manage-qr-accounts'), 'canScan' => Gate::allows('scan-attendance'),
        ]);
    }

    public function requests(): JsonResponse
    {
        Gate::authorize('manage-qr-accounts');

        $users = User::query()->orderBy('id')->paginate(25, ['id', 'name', 'email', 'role', 'registration_status']);
        $users->through(fn (User $user): array => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->role, 'registration_status' => $user->registration_status,
            'approveUrl' => route('account.approve', $user), 'inviteUrl' => route('account.invite', $user),
        ]);

        return response()->json($users);
    }

    public function update(Request $request, User $user, QrCredentials $credentials): JsonResponse
    {
        Gate::authorize('manage-qr-accounts');
        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);
        abort_if($user->is($request->user()), 422, 'Je kunt je eigen beheerdersaccount hier niet wijzigen.');
        $user->role = $data['role'];
        $user->registration_status = $data['status'];
        $user->save();
        if ($user->isApproved()) {
            $credentials->invite($user);
        } else {
            Cache::forget('qr:activation:'.$user->id);
        }

        return response()->json(['message' => $user->isApproved() ? 'Goedgekeurd en activatiemail verstuurd.' : 'Aanvraag afgewezen.']);
    }

    public function invite(User $user, QrCredentials $credentials): JsonResponse
    {
        Gate::authorize('manage-qr-accounts');
        $credentials->invite($user);

        return response()->json(['message' => 'Activatiemail verstuurd. Bestaande QR-codes blijven geldig tot de link wordt gebruikt.']);
    }

    public function inviteSelected(Request $request, QrCredentials $credentials): JsonResponse
    {
        Gate::authorize('manage-qr-accounts');
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:25'], 'ids.*' => ['required', 'integer', 'distinct']]);
        $users = User::query()->whereIn('id', $data['ids'])->where('role', 'student')->where('registration_status', 'approved')->get();
        abort_unless($users->count() === count($data['ids']), 422, 'Selecteer alleen goedgekeurde studenten.');
        foreach ($users as $user) {
            $credentials->invite($user);
        }

        return response()->json(['message' => $users->count().' activatiemails verstuurd.']);
    }
}
