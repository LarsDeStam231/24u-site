<?php

namespace App\Http\Controllers;

use App\EventContentCatalog;
use App\Models\FundraisingChallenge;
use App\Models\ProgramItem;
use App\Models\Sponsor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountContentController extends Controller
{
    public function index(EventContentCatalog $catalog): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $catalog->seedProgramDefaults();
        $catalog->seedChallengeDefaults();

        return response()->json([
            'program' => ProgramItem::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (ProgramItem $item): array => $this->programItemData($item))->all(),
            'challenges' => FundraisingChallenge::query()->orderBy('amount_cents')->get()
                ->map(fn (FundraisingChallenge $challenge): array => $this->challengeData($challenge))->all(),
            'sponsors' => Sponsor::query()->orderBy('company_name')->orderBy('id')->get()
                ->map(fn (Sponsor $sponsor): array => $this->sponsorData($sponsor))->all(),
        ]);
    }

    public function storeProgramItem(Request $request): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $data = $this->validateProgramItem($request);
        $programItem = ProgramItem::query()->create([
            ...$this->programItemAttributes($data),
            'slug' => 'program-'.Str::uuid(),
            'sort_order' => $data['sortOrder'] ?? ((int) ProgramItem::query()->max('sort_order') + 1),
        ]);

        return response()->json(['item' => $this->programItemData($programItem), 'message' => 'Programmaonderdeel toegevoegd.'], 201);
    }

    public function updateProgramItem(Request $request, ProgramItem $programItem): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $data = $this->validateProgramItem($request);
        $programItem->fill($this->programItemAttributes($data));
        $programItem->sort_order = $data['sortOrder'] ?? $programItem->sort_order;
        $programItem->save();

        return response()->json(['item' => $this->programItemData($programItem), 'message' => 'Programmaonderdeel bijgewerkt.']);
    }

    public function destroyProgramItem(ProgramItem $programItem): JsonResponse
    {
        Gate::authorize('manage-event-content');
        abort_if(ProgramItem::query()->count() <= 1, 422, 'Er moet minimaal één programmaonderdeel blijven staan.');
        $programItem->delete();

        return response()->json(['message' => 'Programmaonderdeel verwijderd.']);
    }

    public function storeChallenge(Request $request): JsonResponse
    {
        Gate::authorize('manage-challenge-ladder');
        $data = $request->validate([
            'amountCents' => [
                'required', 'integer', 'min:1', 'max:'.config('event.event.fundraising.goalCents'),
                Rule::unique('fundraising_challenges', 'amount_cents'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ]);
        $challenge = FundraisingChallenge::query()->create([
            'slug' => 'challenge-'.Str::uuid(),
            'amount_cents' => $data['amountCents'],
            'name' => $data['name'],
            'description' => $data['description'],
            'sort_order' => $data['amountCents'],
        ]);

        return response()->json(['challenge' => $this->challengeData($challenge), 'message' => 'Challenge toegevoegd.'], 201);
    }

    public function updateChallenge(Request $request, FundraisingChallenge $challenge): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
        ];

        if (Gate::allows('manage-challenge-ladder')) {
            $rules['amountCents'] = [
                'required', 'integer', 'min:1', 'max:'.config('event.event.fundraising.goalCents'),
                Rule::unique('fundraising_challenges', 'amount_cents')->ignore($challenge->id),
            ];
        } else {
            $rules['amountCents'] = ['prohibited'];
        }

        $data = $request->validate($rules);
        $challenge->name = $data['name'];
        $challenge->description = $data['description'];

        if (isset($data['amountCents'])) {
            $challenge->amount_cents = $data['amountCents'];
            $challenge->sort_order = $data['amountCents'];
        }

        $challenge->save();

        return response()->json(['challenge' => $this->challengeData($challenge), 'message' => 'Challenge bijgewerkt.']);
    }

    public function destroyChallenge(FundraisingChallenge $challenge): JsonResponse
    {
        Gate::authorize('manage-challenge-ladder');
        abort_if(FundraisingChallenge::query()->count() <= 1, 422, 'Er moet minimaal één challenge blijven staan.');
        $challenge->delete();

        return response()->json(['message' => 'Challenge verwijderd.']);
    }

    public function storeSponsor(Request $request): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $data = $this->validateSponsor($request);
        $sponsor = Sponsor::query()->create($this->sponsorAttributes($data));

        return response()->json(['sponsor' => $this->sponsorData($sponsor), 'message' => 'Sponsor toegevoegd.'], 201);
    }

    public function updateSponsor(Request $request, Sponsor $sponsor): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $data = $this->validateSponsor($request, $sponsor);
        $sponsor->update($this->sponsorAttributes($data));

        return response()->json(['sponsor' => $this->sponsorData($sponsor), 'message' => 'Sponsor bijgewerkt.']);
    }

    public function destroySponsor(Sponsor $sponsor): JsonResponse
    {
        Gate::authorize('manage-event-content');
        $sponsor->delete();

        return response()->json(['message' => 'Sponsor verwijderd.']);
    }

    /** @return array{date: string, time: string, title: string, description: string, responsible?: ?string, presenter?: ?string, production?: ?string, materials?: ?string, donationChallenge?: ?string, sortOrder?: ?int} */
    private function validateProgramItem(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'presenter' => ['nullable', 'string', 'max:255'],
            'production' => ['nullable', 'string', 'max:255'],
            'materials' => ['nullable', 'string', 'max:255'],
            'donationChallenge' => ['nullable', 'string', 'max:255'],
            'sortOrder' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array{date: string, time: string, title: string, description: string, responsible?: ?string, presenter?: ?string, production?: ?string, materials?: ?string, donationChallenge?: ?string, sortOrder?: ?int}  $data
     * @return array{event_date: string, time_range: string, title: string, description: string, responsible: ?string, presenter: ?string, production: ?string, materials: ?string, donation_challenge: ?string}
     */
    private function programItemAttributes(array $data): array
    {
        return [
            'event_date' => $data['date'],
            'time_range' => $data['time'],
            'title' => $data['title'],
            'description' => $data['description'],
            'responsible' => $data['responsible'] ?? null,
            'presenter' => $data['presenter'] ?? null,
            'production' => $data['production'] ?? null,
            'materials' => $data['materials'] ?? null,
            'donation_challenge' => $data['donationChallenge'] ?? null,
        ];
    }

    /** @return array{companyName: string, sponsorshipType: string, companyType?: ?string} */
    private function validateSponsor(Request $request, ?Sponsor $sponsor = null): array
    {
        return $request->validate([
            'companyName' => ['required', 'string', 'max:255', Rule::unique('sponsor', 'company_name')->ignore($sponsor?->id)],
            'sponsorshipType' => ['required', Rule::in(['gold', 'silver', 'bronze'])],
            'companyType' => ['nullable', Rule::in(['School', 'Supermarkt', 'Horeca', 'Bakkerij'])],
        ]);
    }

    /**
     * @param  array{companyName: string, sponsorshipType: string, companyType?: ?string}  $data
     * @return array{company_name: string, sponsorship_type: string, company_type: string}
     */
    private function sponsorAttributes(array $data): array
    {
        return [
            'company_name' => $data['companyName'],
            'sponsorship_type' => $data['sponsorshipType'],
            'company_type' => $data['companyType'] ?? '',
        ];
    }

    /** @return array{id: int, slug: string, date: string, time: string, title: string, description: string, responsible: ?string, presenter: ?string, production: ?string, materials: ?string, donationChallenge: ?string, sortOrder: int} */
    private function programItemData(ProgramItem $programItem): array
    {
        return [
            'id' => $programItem->id,
            'slug' => $programItem->slug,
            'date' => $programItem->event_date->format('Y-m-d'),
            'time' => $programItem->time_range,
            'title' => $programItem->title,
            'description' => $programItem->description,
            'responsible' => $programItem->responsible,
            'presenter' => $programItem->presenter,
            'production' => $programItem->production,
            'materials' => $programItem->materials,
            'donationChallenge' => $programItem->donation_challenge,
            'sortOrder' => $programItem->sort_order,
        ];
    }

    /** @return array{id: int, amountCents: int, name: string, description: string} */
    private function challengeData(FundraisingChallenge $challenge): array
    {
        return [
            'id' => $challenge->id,
            'amountCents' => $challenge->amount_cents,
            'name' => $challenge->name,
            'description' => $challenge->description,
        ];
    }

    /** @return array{id: int, companyName: string, sponsorshipType: string, companyType: string} */
    private function sponsorData(Sponsor $sponsor): array
    {
        return [
            'id' => $sponsor->id,
            'companyName' => $sponsor->company_name,
            'sponsorshipType' => $sponsor->sponsorship_type,
            'companyType' => $sponsor->company_type,
        ];
    }
}
