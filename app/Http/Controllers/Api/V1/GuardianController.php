<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GuardianResource;
use App\Models\Guardian;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Guardian::query()->whereHas('children', fn ($children) => $this->scopeChildren($children, $request));
        $search = trim($data['search'] ?? '');
        if ($search !== '') {
            $query->where(fn ($guardians) => $guardians
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('contact_number', 'like', "%{$search}%"));
        }

        return GuardianResource::collection($query->orderBy('last_name')->paginate($data['per_page'] ?? 15));
    }

    public function store(Request $request)
    {
        $guardian = Guardian::create($request->validate($this->rules()));

        return (new GuardianResource($guardian))->response()->setStatusCode(201);
    }

    public function show(Request $request, Guardian $guardian)
    {
        $this->ensureInScope($guardian, $request);

        return new GuardianResource($guardian);
    }

    public function update(Request $request, Guardian $guardian)
    {
        $this->ensureInScope($guardian, $request);
        $data = $request->validate($this->rules(true));
        $stationId = $request->user()->health_station_id;
        $outsideStationChildren = $guardian->children()
            ->where('health_station_id', '!=', $stationId)
            ->exists();

        if ($outsideStationChildren) {
            $replacement = $guardian->replicate();
            $replacement->fill($data);
            $replacement->save();
            $guardian->children()->where('health_station_id', $stationId)->update(['guardian_id' => $replacement->id]);
            $guardian = $replacement;
        } else {
            $guardian->update($data);
        }

        return new GuardianResource($guardian);
    }

    private function rules(bool $partial = false): array
    {
        $presence = $partial ? 'sometimes|required' : 'required';

        return [
            'first_name' => [$presence, 'string', 'max:100'],
            'last_name' => [$presence, 'string', 'max:100'],
            'relationship' => [$presence, 'in:Mother,Father,Grandparent,Legal Guardian,Other'],
            'contact_number' => [$presence, 'regex:/^(09|\+639)\d{9}$/'],
            'occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    private function ensureInScope(Guardian $guardian, Request $request): void
    {
        abort_unless(
            $guardian->children()->where('health_station_id', $request->user()->health_station_id)->exists(),
            404
        );
    }

    private function scopeChildren($query, Request $request): void
    {
        $query->where('health_station_id', $request->user()->health_station_id ?: -1);
    }
}
