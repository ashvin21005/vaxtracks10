<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VaccineResource;
use App\Models\Vaccine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VaccineController extends Controller
{
    public function index(Request $request)
    {
        $query = Vaccine::query();
        if (! $request->user()->isCho() || ! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return VaccineResource::collection($query->orderBy('name')->paginate(100));
    }

    public function store(Request $request)
    {
        $vaccine = Vaccine::create($request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:vaccines,name'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return (new VaccineResource($vaccine))->response()->setStatusCode(201);
    }

    public function show(Request $request, Vaccine $vaccine)
    {
        abort_if(! $vaccine->is_active && ! $request->user()->isCho(), 404);

        return new VaccineResource($vaccine);
    }

    public function update(Request $request, Vaccine $vaccine)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('vaccines', 'name')->ignore($vaccine->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $vaccine->updateCatalog($data);

        return new VaccineResource($vaccine->fresh());
    }
}
