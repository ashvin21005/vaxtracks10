@extends('layouts.app')
@section('title', 'Register Child')
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Register New Child</h1>
            <p class="page-subtitle">Fill in the child's and guardian's details. Fields marked * are required.</p>
        </div>
        <a href="{{ route('patients.index') }}" class="btn btn-secondary">&larr; Back to list</a>
    </div>

    @if ($errors->any())
        <div class="error-box">Please fix the highlighted fields below.</div>
    @endif

    <form method="POST" action="{{ route('patients.store') }}" class="form-card" data-api-form data-api-resource="patient" data-api-method="POST" data-api-url="{{ route('api.v1.patients.store') }}" data-success-url="{{ route('patients.show', ['child' => '__patient_id__']) }}">
        @csrf
        @include('patients._form')
        @include('patients._historical-immunizations', ['mode' => 'collection'])

        <div class="form-actions">
            <a href="{{ route('patients.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Register Child</button>
        </div>
    </form>
@endsection