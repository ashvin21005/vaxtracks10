@extends('layouts.app')
@section('title', 'Immunization Records')
@section('content')
    <div class="page-header operations-heading">
        <div><p class="eyebrow">Patient care</p><h1 class="page-title">Immunization Records</h1><p class="page-subtitle">Completed doses and administration history.</p></div>
        <button type="button" class="btn btn-primary" data-dialog-open="#immunization-record-dialog">Record immunization</button>
    </div>

    <section class="operations-summary">
        <div class="operations-metric"><span>Records this month</span><strong>{{ $summary['this_month'] }}</strong></div>
        <div class="operations-metric"><span>Children recorded</span><strong>{{ $summary['children'] }}</strong></div>
        <div class="operations-metric"><span>Latest record</span><strong>{{ $summary['latest'] ? \Carbon\Carbon::parse($summary['latest'])->format('M d') : '—' }}</strong></div>
    </section>

    <section class="panel operations-panel">
        <div class="operations-toolbar">
            <div><h2 class="panel-title">Immunization history</h2><span class="operations-count">{{ $records->total() }} records</span></div>
            <form method="GET" class="operations-search">
                <label for="record-search" class="sr-only">Search records</label>
                <input id="record-search" type="search" name="search" value="{{ $search }}" placeholder="Search child or vaccine">
                <label for="record-per-page" class="sr-only">Records per page</label>
                <select id="record-per-page" name="per_page" aria-label="Records per page">
                    @foreach ([10, 15, 25, 50] as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} per page</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary" type="submit">Search</button>
            </form>
        </div>
        @if ($records->count())
            <x-vt-table><table class="data-table operations-table">
                <thead><tr><th>Child</th><th>Guardian</th><th>Vaccine</th><th>Dose</th><th>Administration date</th><th>Recorded by</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>@foreach ($records as $record)
                    <tr>
                        <td><a class="link-strong vt-person-link" href="{{ route('patients.show', $record->child) }}"><span class="vt-table-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($record->child->first_name, 0, 1).\Illuminate\Support\Str::substr($record->child->last_name, 0, 1)) }}</span>{{ $record->child->full_name }}</a></td>
                        <td>{{ $record->child->guardian->full_name }}</td><td>{{ $record->vaccine }}</td><td>Dose {{ $record->dose_number }}</td>
                        <td>{{ $record->administration_date_label }}</td><td>{{ $record->recorder?->name ?? '—' }}</td>
                        <td><span class="status-pill status-neutral">{{ $record->status }}</span></td>
                        <td class="row-actions">
                            <details class="vt-row-menu">
                                <summary aria-label="Actions for {{ $record->child->full_name }}"><i data-lucide="ellipsis" aria-hidden="true"></i></summary>
                                <div class="vt-row-menu__panel">
                                    <a href="{{ route('immunizations.edit', $record) }}">Edit</a>
                                    <form method="POST" action="{{ route('immunizations.destroy', $record) }}" data-confirm-message="Delete this immunization record? The matching vaccination schedule will be reopened.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></x-vt-table>
            <div class="table-footer"><span>Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }}</span>{{ $records->links('pagination::bootstrap-4') }}</div>
        @else
            <x-vt-empty-state title="No immunization records yet" description="Record a dose to begin the child’s immunization history." icon="syringe">
                <button type="button" class="btn btn-primary" data-dialog-open="#immunization-record-dialog">Record immunization</button>
            </x-vt-empty-state>
        @endif
    </section>

    <dialog class="workflow-dialog" id="immunization-record-dialog" aria-labelledby="immunization-record-title" data-record-dialog>
        <div class="workflow-dialog__header">
            <div>
                <p class="eyebrow">Patient care</p>
                <h2 class="panel-title" id="immunization-record-title">Record immunization</h2>
                <p class="page-subtitle">Confirm the administered dose before saving. One dose will be deducted from station inventory.</p>
            </div>
            <button type="button" class="vt-icon-button" data-dialog-close aria-label="Close record dialog">&times;</button>
        </div>
        <div data-modal-form-content>
            <form method="POST" action="{{ route('immunizations.store') }}"
                data-api-form data-api-resource="immunization" data-api-method="POST"
                data-api-url="{{ route('api.v1.patients.immunizations.store', ['child' => '__child_id__']) }}"
                data-success-url="{{ route('immunizations.index') }}" data-modal-success="record"
                data-confirm-message="Confirm that this vaccine dose was administered. Saving this record will deduct one dose from your station inventory.">
                @csrf
                <div class="record-form-grid">
                    <label>Child
                        <select name="child_id" required><option value="">Select a child</option>@foreach ($children as $child)<option value="{{ $child->id }}">{{ $child->full_name }} · {{ $child->barangay }}</option>@endforeach</select>
                    </label>
                    <label>Vaccine
                        <select name="vaccine" required><option value="">Select a vaccine</option>@foreach ($vaccines as $vaccine)<option value="{{ $vaccine }}" data-vaccine-id="{{ $vaccineIds[$vaccine] ?? '' }}">{{ $vaccine }}</option>@endforeach</select>
                    </label>
                    <label>Dose number<input type="number" name="dose_number" min="1" max="10" value="1" required></label>
                    <label>Administration date<input type="date" name="administered_at" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></label>
                    <label>Recorded by<input value="{{ auth()->user()->name }}" disabled></label>
                </div>
                <div class="workflow-dialog__actions">
                    <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Review and save</button>
                </div>
            </form>
        </div>
        <div class="workflow-dialog__success" data-modal-success-panel hidden>
            <i data-lucide="circle-check" aria-hidden="true"></i>
            <h3>Immunization recorded</h3>
            <p>The dose has been saved and station inventory updated.</p>
            <div class="workflow-dialog__actions">
                <button class="btn btn-primary" type="button" data-modal-done>Done</button>
            </div>
        </div>
    </dialog>
@endsection