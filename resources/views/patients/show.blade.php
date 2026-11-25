@extends('layouts.app')
@section('title', $child->full_name)
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $child->full_name }}</h1>
            <p class="page-subtitle">Baby Book ID: <strong>{{ $child->baby_book_id }}</strong></p>
        </div>
        <div class="header-actions">
            <a href="{{ route('patients.index') }}" class="btn btn-secondary">&larr; Back to list</a>
            <a href="{{ route('patients.edit', $child) }}" class="btn btn-primary">Edit Profile</a>
            <form method="POST" action="{{ route('patients.destroy', $child) }}" data-confirm-message="Archive this child record? The immunization and schedule history will be retained.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-secondary">Archive patient</button>
            </form>
        </div>
    </div>

    <div class="profile-grid">
        <div class="panel">
            <h2 class="panel-title">Child Information</h2>
            <dl class="info-list">
                <div><dt>Full Name</dt><dd>{{ $child->full_name }}</dd></div>
                <div><dt>Sex</dt><dd>{{ $child->sex }}</dd></div>
                <div><dt>Date of Birth</dt><dd>{{ $child->date_of_birth->format('M d, Y') }}</dd></div>
                <div><dt>Age</dt><dd>{{ $child->age }}</dd></div>
                <div><dt>Place of Birth</dt><dd>{{ $child->place_of_birth ?? '—' }}</dd></div>
                <div><dt>Birth Facility</dt><dd>{{ $child->birth_facility ?? '—' }}</dd></div>
                <div><dt>Birth Weight</dt><dd>{{ $child->birth_weight ? $child->birth_weight . ' kg' : '—' }}</dd></div>
                <div><dt>Barangay</dt><dd>{{ $child->barangay }}</dd></div>
                <div><dt>Health Station</dt><dd>{{ $child->healthStation?->name ?? 'Not assigned' }}</dd></div>
                <div><dt>Status</dt><dd><span class="status-pill status-{{ $child->status === 'ACTIVE' ? 'good' : 'neutral' }}">{{ $child->status }}</span></dd></div>
                <div><dt>Address</dt><dd>{{ $child->address ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="panel">
            <h2 class="panel-title">Guardian Information</h2>
            <dl class="info-list">
                <div><dt>Full Name</dt><dd>{{ $child->guardian->full_name }}</dd></div>
                <div><dt>Relationship</dt><dd>{{ $child->guardian->relationship }}</dd></div>
                <div><dt>Contact Number</dt><dd>{{ $child->guardian->contact_number }}</dd></div>
                <div><dt>Alternative Contact</dt><dd>{{ $child->guardian->alternative_contact_number ?? '—' }}</dd></div>
                <div><dt>Occupation</dt><dd>{{ $child->guardian->occupation ?? '—' }}</dd></div>
                <div><dt>Guardian Address</dt><dd>{{ $child->guardian->address ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    <section class="panel">
        <h2 class="panel-title">EPI Immunization Schedule</h2>
        @if ($child->vaccinationSchedules->isNotEmpty())
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Vaccine</th><th>Dose</th><th>Due date</th><th>Catch-up target</th><th>Status</th></tr></thead><tbody>
                @foreach ($child->vaccinationSchedules->sortBy(fn ($schedule) => [$schedule->due_date?->toDateString(), $schedule->vaccine, $schedule->dose_number]) as $schedule)
                    @php($scheduleStatus = $schedule->display_status)
                    <tr>
                        <td>{{ $schedule->vaccine }}</td>
                        <td>Dose {{ $schedule->dose_number }}</td>
                        <td>{{ $schedule->due_date?->format('M d, Y') ?? '—' }}</td>
                        <td>{{ $schedule->scheduled_date->format('M d, Y') }}</td>
                        <td>
                            <span class="status-pill status-{{ in_array($scheduleStatus, ['OVERDUE', 'INVALID'], true) ? 'danger' : (in_array($scheduleStatus, ['DUE'], true) ? 'warning' : (in_array($scheduleStatus, ['FOR REVIEW'], true) ? 'neutral' : 'good')) }}">{{ $scheduleStatus }}</span>
                            @if ($schedule->review_required)<small class="field-error">Dose history requires healthcare-worker review.</small>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @else
            <p class="empty-text">No EPI schedule is available.</p>
        @endif
    </section>

    <div class="panel">
        <h2 class="panel-title">Immunization History</h2>
        @if ($child->immunizationRecords->isNotEmpty())
            @foreach ($child->immunizationRecords->groupBy(fn ($record) => $record->vaccine_id ?: $record->vaccine) as $records)
                @php($firstRecord = $records->first())
                @php($expectedDoses = count(config('immunization.epi.'.($firstRecord->vaccineDefinition?->epi_code ?? '').'.doses', [])))
                @php($administeredCount = $records->where('history_status', 'ADMINISTERED')->count())
                @php($needsReview = $records->contains(fn ($record) => $record->status === 'REVIEW' || $record->validation_status !== 'VALID' || ! $record->verified_at))
                <details class="history-vaccine-group">
                    <summary><strong>{{ $firstRecord->vaccine }}</strong><span>{{ $administeredCount }} of {{ $expectedDoses ?: '—' }} doses recorded</span><span class="status-pill status-{{ $needsReview ? 'neutral' : 'good' }}">{{ $needsReview ? 'FOR REVIEW' : 'RECORDED' }}</span></summary>
                    <div class="table-scroll"><table class="data-table"><thead><tr><th>Dose</th><th>History status</th><th>Administration date</th><th>Verification</th></tr></thead><tbody>
                        @foreach ($records->sortBy('dose_number') as $record)
                            <tr>
                                <td>Dose {{ $record->dose_number }}</td>
                                <td>{{ str_replace('_', ' ', $record->history_status ?? 'ADMINISTERED') }} · {{ $record->validation_status }}</td>
                                <td>{{ $record->administration_date_label }}</td>
                                <td>{{ $record->verified_at ? 'Verified' : 'Needs verification' }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                </details>
                @endforeach
        @else
            <p class="empty-text">No immunization records have been added for this child.</p>
        @endif
    </div>

    <form method="POST" action="{{ route('patients.historical-immunizations.store', $child) }}"
        data-api-form data-api-resource="historical-immunization" data-api-method="POST"
        data-api-url="{{ route('api.v1.patients.historical-immunizations.store', $child) }}"
        data-success-url="{{ route('patients.show', $child) }}">
        @csrf
        @include('patients._historical-immunizations', ['mode' => 'single'])
        <button type="submit" class="btn btn-primary">Save verified historical dose</button>
    </form>
@endsection