<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AccountDonationController extends Controller
{
    /**
     * List the donations with their donor details for administrators, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-donations');
        $data = $request->validate(['status' => ['nullable', Rule::in(Donation::STATUSES)]]);

        $donations = Donation::query()
            ->with(['donor:id,name,email', 'student:id,name'])
            ->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $donations->through(fn (Donation $donation): array => [
            'id' => $donation->id,
            'amountCents' => (int) round((float) $donation->amount * 100),
            'status' => $donation->status,
            'paymentMethod' => $donation->payment_method,
            'anonymous' => $donation->is_anonymous,
            'donorName' => $donation->donor->name,
            'donorEmail' => $donation->donor->email,
            'studentName' => $donation->student?->name,
            'createdAt' => $donation->created_at?->toIso8601String(),
        ]);

        return response()->json([
            ...$donations->toArray(),
            'totals' => [
                'completedCents' => Donation::completedAmountCents(),
                'completedCount' => Donation::query()->where('status', 'completed')->count(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
