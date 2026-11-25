@extends('layouts.app')
@section('title', 'Edit Profile')
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle">Updating {{ $child->full_name }}</p>
        </div>
        <a href="{{ route('patients.show', $child) }}" class="btn btn-secondary">&larr; Back to profile</a>
    </div>

    @if ($errors->any())
        <div class="error-box">Please fix the highlighted fields below.</div>
    @endif

    <form method="POST" action="{{ route('patients.update', $child) }}" class="form-card" data-api-form data-api-resource="patient" data-api-method="PUT" data-api-url="{{ route('api.v1.patients.update', $child) }}" data-success-url="{{ route('patients.show', $child) }}">
        @csrf
        @method('PUT')
        @include('patients._form')

        <div class="form-actions">
            <a href="{{ route('patients.show', $child) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
@endsection