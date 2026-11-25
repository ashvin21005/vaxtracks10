@extends('layouts.app')
@section('title', 'Vaccine Master List')
@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">CHO administration</p>
            <h1 class="page-title">Vaccine master list</h1>
            <p class="page-subtitle">Manage vaccine names and availability for inventory and immunization records.</p>
        </div>
        <a href="{{ route('cho.vaccines.create') }}" class="btn btn-primary">Add vaccine</a>
    </div>

    <section class="panel operations-panel">
        <div class="table-scroll">
            <table class="data-table operations-table">
                <thead>
                    <tr><th>Vaccine</th><th>Description</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($vaccines as $vaccine)
                        <tr>
                            <td>{{ $vaccine->name }}</td>
                            <td>{{ $vaccine->description ?: '—' }}</td>
                            <td><span class="status-pill status-{{ $vaccine->is_active ? 'good' : 'neutral' }}">{{ $vaccine->is_active ? 'ACTIVE' : 'INACTIVE' }}</span></td>
                            <td>
                                <div class="admin-row-actions">
                                    <a href="{{ route('cho.vaccines.edit', $vaccine) }}" class="btn btn-sm btn-secondary">Edit</a>
                                    <form method="POST" action="{{ route('cho.vaccines.destroy', $vaccine) }}" data-confirm-message="Delete this vaccine? Vaccines linked to inventory or health records cannot be deleted.">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No vaccines are in the master list.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>{{ $vaccines->total() }} vaccines</span>
            {{ $vaccines->links('pagination::bootstrap-4') }}
        </div>
    </section>
@endsection
