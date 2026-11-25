<?php

namespace App\Http\Controllers;

use App\Models\Vaccine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VaccineManagementController extends Controller
{
    public function index()
    {
        return view('cho.vaccines.index', [
            'vaccines' => Vaccine::query()->orderBy('name')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('cho.vaccines.form', ['vaccine' => new Vaccine]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        Vaccine::create($data);

        return redirect()->route('cho.vaccines.index')->with('success', 'Vaccine added to the master list.');
    }

    public function edit(Vaccine $vaccine)
    {
        return view('cho.vaccines.form', compact('vaccine'));
    }

    public function update(Request $request, Vaccine $vaccine)
    {
        $vaccine->updateCatalog($request->validate($this->rules($vaccine)));

        return redirect()->route('cho.vaccines.index')->with('success', 'Vaccine master list entry updated.');
    }

    public function destroy(Vaccine $vaccine)
    {
        $isInUse = DB::table('immunization_records')->where('vaccine_id', $vaccine->id)->orWhere('vaccine', $vaccine->name)->exists()
            || DB::table('vaccine_inventory')->where('vaccine_id', $vaccine->id)->orWhere('vaccine', $vaccine->name)->exists()
            || DB::table('vaccination_schedules')->where('vaccine', $vaccine->name)->exists()
            || DB::table('historical_indicators')->where('vaccine_id', $vaccine->id)->exists();

        if ($isInUse) {
            return redirect()->route('cho.vaccines.index')
                ->with('error', 'This vaccine has inventory or health records and cannot be deleted. Edit it and set its status to inactive instead.');
        }

        $vaccine->delete();

        return redirect()->route('cho.vaccines.index')->with('success', 'Vaccine deleted from the master list.');
    }

    private function rules(?Vaccine $vaccine = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('vaccines', 'name')->ignore($vaccine?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
