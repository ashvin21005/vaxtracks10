@extends('layouts.app')
@section('title', $record->exists ? 'Edit Immunization' : 'Record Immunization')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Patient care</p><h1 class="page-title">{{ $record->exists ? 'Edit immunization record' : 'Record immunization' }}</h1><p class="page-subtitle">Save the administered dose to the child’s history.</p></div></div>
    <section class="panel record-form-panel">
        <form method="POST" action="{{ $record->exists ? route('immunizations.update', $record) : route('immunizations.store') }}" data-api-form data-api-resource="immunization" data-api-method="{{ $record->exists ? 'PUT' : 'POST' }}" data-api-url="{{ $record->exists ? route('api.v1.immunizations.update', $record) : route('api.v1.patients.immunizations.store', ['child' => '__child_id__']) }}" data-success-url="{{ route('immunizations.index') }}" @unless($record->exists) data-confirm-message="Confirm that this vaccine dose was administered. Saving this record will deduct one dose from your station inventory." @endunless>
            @csrf
            @if ($record->exists) @method('PUT') @endif
            <div class="record-form-grid">
                <label>Child <select name="child_id" required class="@error('child_id') is-invalid @enderror"><option value="">Select a child</option>@foreach ($children as $child)<option value="{{ $child->id }}" @selected(old('child_id', $record->child_id) == $child->id)>{{ $child->full_name }} · {{ $child->barangay }}</option>@endforeach</select>@error('child_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label>Vaccine <select name="vaccine" required>@foreach ($vaccines as $vaccine)<option value="{{ $vaccine }}" data-vaccine-id="{{ $vaccineIds[$vaccine] ?? '' }}" @selected(old('vaccine', $record->vaccine) === $vaccine)>{{ $vaccine }}</option>@endforeach</select>@error('vaccine')<small class="field-error">{{ $message }}</small>@enderror @error('inventory')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label>Dose number <input type="number" name="dose_number" min="1" max="10" value="{{ old('dose_number', $record->dose_number ?: 1) }}" required>@error('dose_number')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label>Administration date <input type="date" name="administered_at" max="{{ today()->toDateString() }}" value="{{ old('administered_at', $record->administered_at?->toDateString()) }}" required>@error('administered_at')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label>Recorded by <input value="{{ auth()->user()->name }}" disabled></label>
                <label>Status <input value="Completed" disabled></label>
            </div>
            <div class="form-actions"><a href="{{ route('immunizations.index') }}" class="btn btn-secondary">Cancel</a><button type="submit" class="btn btn-primary">{{ $record->exists ? 'Update record' : 'Save record' }}</button></div>
        </form>
    </section>
@endsection