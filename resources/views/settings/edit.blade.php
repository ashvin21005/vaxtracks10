@extends('layouts.app')
@section('title', 'Account Settings')
@section('content')
    <div class="page-header"><div><p class="eyebrow">Your account</p><h1 class="page-title">Account Settings</h1><p class="page-subtitle">Update your contact and sign-in details.</p></div></div>
    <section class="panel record-form-panel"><form method="POST" action="{{ route('settings.update') }}">@csrf @method('PUT')<div class="record-form-grid">
        <label>Name<input name="name" value="{{ old('name', auth()->user()->name) }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Username<input name="username" value="{{ old('username', auth()->user()->username) }}" required>@error('username')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Email<input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
        <label>Role<input value="{{ auth()->user()->role }}" disabled></label>
        <label>Health station<input value="{{ auth()->user()->healthStation?->name ?? 'City Health Office' }}" disabled></label>
    </div><div class="form-actions"><button class="btn btn-primary" type="submit">Save account</button></div></form></section>
@endsection