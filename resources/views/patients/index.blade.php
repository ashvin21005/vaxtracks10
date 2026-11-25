@extends('layouts.app')
@section('title', 'Patients')
@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Patients</h1>
            <p class="page-subtitle">Registered children and guardian contacts.</p>
        </div>
        <button type="button" class="btn btn-primary" data-dialog-open="#patient-registration-dialog">+ Register New Child</button>
    </div>

    <div class="panel">
        <form method="GET" action="{{ route('patients.index') }}" class="operations-filters patient-filters">
            <input type="search" name="search" value="{{ $search }}" placeholder="Search name, guardian, or contact">
            <select name="barangay" aria-label="Filter by barangay"><option value="">All barangays</option>@foreach ($barangays as $option)<option value="{{ $option }}" @selected($barangay === $option)>{{ $option }}</option>@endforeach</select>
            <select name="status" aria-label="Filter by patient status"><option value="">All statuses</option><option value="ACTIVE" @selected($status === 'ACTIVE')>Active</option><option value="INACTIVE" @selected($status === 'INACTIVE')>Inactive</option></select>
            <button type="submit" class="btn btn-primary">Filter</button>
            @if ($search || $status || $barangay)
                <a href="{{ route('patients.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>

        @if ($children->count())
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Child</th>
                            <th>Sex</th>
                            <th>Age</th>
                            <th>Barangay</th>
                            <th>Status</th>
                            <th>Guardian</th>
                            <th>Contact</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($children as $child)
                            <tr>
                                <td>{{ $child->baby_book_id }}</td>
                                <td><a href="{{ route('patients.show', $child) }}" class="link-strong vt-person-link"><span class="vt-table-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($child->first_name, 0, 1).\Illuminate\Support\Str::substr($child->last_name, 0, 1)) }}</span>{{ $child->full_name }}</a></td>
                                <td>{{ $child->sex }}</td>
                                <td>{{ $child->age }}</td>
                                <td>{{ $child->barangay }}</td>
                                <td><span class="status-pill status-{{ $child->status === 'ACTIVE' ? 'good' : 'neutral' }}">{{ $child->status }}</span></td>
                                <td>{{ $child->guardian->full_name }}</td>
                                <td>{{ $child->guardian->contact_number }}</td>
                                <td class="row-actions">
                                    <details class="vt-row-menu">
                                        <summary aria-label="Actions for {{ $child->full_name }}"><i data-lucide="ellipsis" aria-hidden="true"></i></summary>
                                        <div class="vt-row-menu__panel">
                                            <a href="{{ route('patients.show', $child) }}">View</a>
                                            <a href="{{ route('patients.edit', $child) }}">Edit</a>
                                            <form method="POST" action="{{ route('patients.destroy', $child) }}" data-confirm-message="Archive this patient? Immunization and schedule history will be retained.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit">Archive</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>Showing {{ $children->firstItem() }}&ndash;{{ $children->lastItem() }} of {{ $children->total() }}</span>
                {{ $children->links('pagination::bootstrap-4') }}
            </div>
        @else
            <x-vt-empty-state :title="$search ? 'No patients match your search.' : 'No children registered yet.'" icon="baby">
                <button type="button" class="btn btn-primary" data-dialog-open="#patient-registration-dialog">+ Register New Child</button>
            </x-vt-empty-state>
        @endif
    </div>

    <dialog class="workflow-dialog patient-registration-dialog" id="patient-registration-dialog" aria-labelledby="patient-registration-title" data-registration-dialog>
        <div class="workflow-dialog__header">
            <div>
                <p class="eyebrow">Patient records</p>
                <h2 class="panel-title" id="patient-registration-title">Register New Child</h2>
                <p class="page-subtitle">Enter the child and guardian details. Fields marked * are required.</p>
            </div>
            <button type="button" class="vt-icon-button" data-dialog-close aria-label="Close registration dialog">&times;</button>
        </div>
        <div data-modal-form-content>
            <form method="POST" action="{{ route('patients.store') }}" class="form-card" data-api-form data-api-resource="patient" data-api-method="POST" data-api-url="{{ route('api.v1.patients.store') }}" data-success-url="{{ route('patients.show', ['child' => '__patient_id__']) }}" data-modal-success="patient">
                @csrf
                <ol class="registration-stepper" aria-label="Registration steps">
                    <li data-step-marker="0" aria-current="step">Child &amp; Guardian</li>
                    <li data-step-marker="1">Vaccination History</li>
                    <li data-step-marker="2">Review &amp; Schedule</li>
                </ol>
                <section data-registration-step="0">
                    @include('patients._form', $registrationForm)
                </section>
                <section data-registration-step="1" hidden>
                    @include('patients._historical-immunizations', ['mode' => 'collection', 'historicalVaccines' => $registrationForm['historicalVaccines']])
                </section>
                <section data-registration-step="2" hidden>
                    <h2 class="form-section-title">Review registration</h2>
                    <dl class="registration-review-list">
                        <div><dt>Child Full Name</dt><dd data-review-child>Name not entered</dd></div>
                        <div><dt>Guardian Full Name</dt><dd data-review-guardian>Guardian details not entered</dd></div>
                        <div><dt>Contact Number</dt><dd data-review-contact>Contact not entered</dd></div>
                        <div><dt>Address</dt><dd data-review-address>Address not entered</dd></div>
                        <div><dt>History Records</dt><dd data-review-history>No vaccination history entered</dd></div>
                    </dl>
                    <label class="registration-duplicate-confirm"><input type="checkbox" name="duplicate_confirmation" value="1">I checked existing patients and confirm this is a different child.</label>
                    @error('duplicate_confirmation')<p class="field-error">{{ $message }}</p>@enderror
                    <div class="registration-preview" data-schedule-preview data-epi-protocols="{{ json_encode(config('immunization.epi')) }}">
                        <div class="panel-header"><h3 class="panel-title">Remaining vaccination schedule</h3></div>
                        <p class="page-subtitle">Preview based on current configured targets and entered history. Dates require healthcare-worker verification.</p>
                        <div class="table-scroll"><table class="data-table"><thead><tr><th>Vaccine</th><th>Dose</th><th>Target age</th><th>Proposed date</th><th>Status</th></tr></thead><tbody data-schedule-preview-rows><tr><td colspan="5">Enter a date of birth to preview the schedule.</td></tr></tbody></table></div>
                    </div>
                </section>
                <div class="form-actions registration-step-actions">
                    <button type="button" class="btn btn-secondary" data-registration-previous hidden>Previous</button>
                    <span data-registration-step-label>Step 1 of 3</span>
                    <div class="registration-step-actions__right">
                        <button type="button" class="btn btn-primary" data-registration-next>Next</button>
                        <button type="submit" class="btn btn-primary" data-registration-submit hidden>Review and Register</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="workflow-dialog__success" data-modal-success-panel hidden>
            <i data-lucide="circle-check" aria-hidden="true"></i>
            <h3>Patient registered</h3>
            <p><strong data-registered-child-name></strong> has been added with Baby Book ID <strong data-registered-baby-book-id></strong>.</p>
            <div class="workflow-dialog__actions">
                <a class="btn btn-secondary" data-registered-child-link href="#">View patient</a>
                <button class="btn btn-primary" type="button" data-modal-done>Done</button>
            </div>
        </div>
    </dialog>
    <script src="{{ asset('js/patient-registration.js') }}" defer></script>
@endsection