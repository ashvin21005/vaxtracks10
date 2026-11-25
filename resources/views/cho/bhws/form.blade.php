@extends('layouts.app')
@section('title', 'Add BHW')
@section('content')
    <div class="page-header"><div><p class="eyebrow">CHO administration</p><h1 class="page-title">Create BHW account</h1><p class="page-subtitle">The BHW will be required to change this temporary password at first login.</p></div></div>
    <section class="panel record-form-panel"><form method="POST" action="{{ route('cho.bhws.store') }}">@csrf<div class="record-form-grid">
        <label>Full name<input name="name" value="{{ old('name') }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Username<input name="username" value="{{ old('username') }}" autocomplete="username" required>@error('username')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Health station<select name="health_station_id" required><option value="">Select a station</option>@foreach ($stations as $station)<option value="{{ $station->id }}" @selected(old('health_station_id') == $station->id)>{{ $station->name }} · {{ $station->barangay_label }}</option>@endforeach</select>@error('health_station_id')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Temporary password<input type="password" name="password" minlength="12" autocomplete="new-password" required>@error('password')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Confirm temporary password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
    </div><div class="form-actions"><a href="{{ route('cho.bhws.index') }}" class="btn btn-secondary">Cancel</a><button type="submit" class="btn btn-primary">Create account</button></div></form></section>
@endsection