@extends('layouts.app')
@section('title', $vaccine->exists ? 'Edit Vaccine' : 'Add Vaccine')
@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CHO administration</p>
            <h1 class="page-title">{{ $vaccine->exists ? 'Edit vaccine' : 'Add vaccine' }}</h1>
            <p class="page-subtitle">Vaccine catalog changes are reflected in linked inventory and vaccination records.</p>
        </div>
    </div>

    <section class="panel record-form-panel">
        <form method="POST" action="{{ $vaccine->exists ? route('cho.vaccines.update', $vaccine) : route('cho.vaccines.store') }}">
            @csrf
            @if ($vaccine->exists)
                @method('PUT')
            @endif
            <div class="record-form-grid">
                <label>Vaccine name
                    <input name="name" value="{{ old('name', $vaccine->name) }}" maxlength="150" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Description
                    <textarea name="description" rows="3" maxlength="2000">{{ old('description', $vaccine->description) }}</textarea>
                    @error('description')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Status
                    <select name="is_active" required>
                        <option value="1" @selected((string) old('is_active', $vaccine->exists ? (int) $vaccine->is_active : 1) === '1')>Active</option>
                        <option value="0" @selected((string) old('is_active', $vaccine->exists ? (int) $vaccine->is_active : 1) === '0')>Inactive</option>
                    </select>
                    @error('is_active')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>
            <div class="form-actions">
                <a href="{{ route('cho.vaccines.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary" type="submit">{{ $vaccine->exists ? 'Update vaccine' : 'Add vaccine' }}</button>
            </div>
        </form>
    </section>
@endsection
