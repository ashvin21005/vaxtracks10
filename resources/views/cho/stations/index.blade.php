@extends('layouts.app')
@section('title', 'Health Stations')
@section('content')
    <div class="page-header"><div><p class="eyebrow">CHO administration</p><h1 class="page-title">Health Stations</h1><p class="page-subtitle">Stations, staffing, child enrollment, and follow-up status.</p></div><button type="button" class="btn btn-primary" data-dialog-open="#health-station-create-dialog">Add health station</button></div>
    <section class="panel operations-panel">
        <form method="GET" class="operations-filters">
            <input type="search" name="search" value="{{ $search }}" placeholder="Station or barangay" aria-label="Search health stations">
            <select name="barangay" aria-label="Filter by barangay"><option value="">All barangays</option>@foreach ($barangays as $option)<option value="{{ $option->official_name }}" @selected($barangay === $option->official_name)>{{ $option->official_name }}</option>@endforeach</select>
            <select name="status" aria-label="Filter by status"><option value="">All statuses</option><option value="ACTIVE" @selected($status === 'ACTIVE')>Active</option><option value="INACTIVE" @selected($status === 'INACTIVE')>Inactive</option></select>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
        <div class="table-scroll"><table class="data-table operations-table"><thead><tr><th>Health station</th><th>Barangays served</th><th>Assigned BHWs</th><th>Registered children</th><th>Inventory lines</th><th>Due</th><th>Overdue</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse ($stations as $station)
                <tr>
                    <td><a class="link-strong" href="{{ route('cho.stations.show', $station) }}">{{ $station->name }}</a></td>
                    <td>{{ $station->barangay_label }}</td><td>{{ $station->bhw_count }}</td><td>{{ $station->children_count }}</td>
                    <td>{{ $station->low_stock_count }} low / {{ $station->vaccineInventory()->count() }} total</td><td>{{ $station->due_count }}</td><td>{{ $station->overdue_count }}</td>
                    <td><span class="status-pill status-{{ $station->status === 'ACTIVE' ? 'good' : 'neutral' }}">{{ $station->status }}</span></td>
                    <td><div class="schedule-actions"><button type="button" class="btn btn-sm btn-secondary" data-dialog-open="#health-station-edit-{{ $station->id }}">Edit</button><form method="POST" action="{{ route('cho.stations.destroy', $station) }}" data-confirm-message="Delete this health station? Only stations without assigned BHWs, children, or inventory can be deleted.">@csrf @method('DELETE')<button class="btn btn-sm btn-danger" type="submit">Delete</button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="9">No health stations match these filters.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="table-footer"><span>{{ $stations->total() }} health stations</span>{{ $stations->links('pagination::bootstrap-4') }}</div>
    </section>
    <dialog class="workflow-dialog" id="health-station-create-dialog" aria-labelledby="health-station-create-title">
        <div class="workflow-dialog__header"><div><p class="eyebrow">CHO administration</p><h2 class="panel-title" id="health-station-create-title">Add health station</h2></div><button type="button" class="btn btn-sm btn-secondary" data-dialog-close>Close</button></div>
        <form method="POST" action="{{ route('cho.stations.store') }}">
            @csrf
            <input type="hidden" name="_station_form" value="create">
            @php($createSelectedBarangays = old('_station_form') === 'create' ? array_map('strval', old('barangays', [])) : [])
            <div class="record-form-grid">
                <label>Station name<input name="name" value="{{ old('_station_form') === 'create' ? old('name') : '' }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                <fieldset class="station-barangay-fieldset" aria-describedby="create-barangays-hint">
                    <legend>Barangays served</legend>
                    <label class="station-barangay-search"><i data-lucide="search" aria-hidden="true"></i><span class="sr-only">Search barangays</span><input type="search" placeholder="Find a barangay" data-barangay-search></label>
                    <div class="station-barangay-options">
                        @foreach ($barangays as $option)
                            <label class="station-barangay-option" data-barangay-option><input type="checkbox" name="barangays[]" value="{{ $option->id }}" @checked(in_array((string) $option->id, $createSelectedBarangays, true))><span>{{ $option->official_name }}@if ($option->name) — {{ $option->name }}@endif</span></label>
                        @endforeach
                    </div>
                    <small id="create-barangays-hint">Check every barangay served by this station.</small>
                    @if ($errors->has('barangays') || $errors->has('barangays.*'))<small class="field-error">{{ $errors->first('barangays') ?: $errors->first('barangays.*') }}</small>@endif
                </fieldset>
                <label>Address<input name="address" value="{{ old('_station_form') === 'create' ? old('address') : '' }}">@error('address')<small class="field-error">{{ $message }}</small>@enderror</label>
                <label>Status<select name="status" required><option value="ACTIVE" @selected(old('_station_form') === 'create' ? old('status', 'ACTIVE') === 'ACTIVE' : true)>Active</option><option value="INACTIVE" @selected(old('_station_form') === 'create' && old('status') === 'INACTIVE')>Inactive</option></select>@error('status')<small class="field-error">{{ $message }}</small>@enderror</label>
            </div>
            <div class="workflow-dialog__actions"><button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button><button type="submit" class="btn btn-primary">Create station</button></div>
        </form>
    </dialog>
    @foreach ($stations as $station)
        @php($editFormKey = 'edit:'.$station->id)
        @php($editSelectedBarangays = old('_station_form') === $editFormKey ? array_map('strval', old('barangays', [])) : array_map('strval', $station->barangays->modelKeys()))
        <dialog class="workflow-dialog" id="health-station-edit-{{ $station->id }}" aria-labelledby="health-station-edit-title-{{ $station->id }}">
            <div class="workflow-dialog__header"><div><p class="eyebrow">CHO administration</p><h2 class="panel-title" id="health-station-edit-title-{{ $station->id }}">Edit {{ $station->name }}</h2></div><button type="button" class="btn btn-sm btn-secondary" data-dialog-close>Close</button></div>
            <form method="POST" action="{{ route('cho.stations.update', $station) }}">
                @csrf @method('PUT')
                <input type="hidden" name="_station_form" value="{{ $editFormKey }}">
                <div class="record-form-grid">
                    <label>Station name<input name="name" value="{{ old('_station_form') === $editFormKey ? old('name', $station->name) : $station->name }}" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <fieldset class="station-barangay-fieldset" aria-describedby="edit-barangays-hint-{{ $station->id }}">
                        <legend>Barangays served</legend>
                        <label class="station-barangay-search"><i data-lucide="search" aria-hidden="true"></i><span class="sr-only">Search barangays</span><input type="search" placeholder="Find a barangay" data-barangay-search></label>
                        <div class="station-barangay-options">
                            @foreach ($barangays as $option)
                                <label class="station-barangay-option" data-barangay-option><input type="checkbox" name="barangays[]" value="{{ $option->id }}" @checked(in_array((string) $option->id, $editSelectedBarangays, true))><span>{{ $option->official_name }}@if ($option->name) — {{ $option->name }}@endif</span></label>
                            @endforeach
                        </div>
                        <small id="edit-barangays-hint-{{ $station->id }}">Check every barangay served by this station.</small>
                        @if ($errors->has('barangays') || $errors->has('barangays.*'))<small class="field-error">{{ $errors->first('barangays') ?: $errors->first('barangays.*') }}</small>@endif
                    </fieldset>
                    <label>Address<input name="address" value="{{ old('_station_form') === $editFormKey ? old('address', $station->address) : $station->address }}">@error('address')<small class="field-error">{{ $message }}</small>@enderror</label>
                    @php($editStatus = old('_station_form') === $editFormKey ? old('status', $station->status) : $station->status)
                    <label>Status<select name="status" required><option value="ACTIVE" @selected($editStatus === 'ACTIVE')>Active</option><option value="INACTIVE" @selected($editStatus === 'INACTIVE')>Inactive</option></select>@error('status')<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
                <div class="workflow-dialog__actions"><button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
            </form>
        </dialog>
    @endforeach
    @if ($errors->any() && old('_station_form'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = @json(old('_station_form'));
                const dialogId = form === 'create' ? 'health-station-create-dialog' : `health-station-${form.replace(':', '-')}`;
                document.getElementById(dialogId)?.showModal();
            });
        </script>
    @elseif (request()->boolean('create'))
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('health-station-create-dialog')?.showModal());</script>
    @elseif (request()->filled('edit'))
        <script>document.addEventListener('DOMContentLoaded', () => document.getElementById('health-station-edit-{{ request('edit') }}')?.showModal());</script>
    @endif
    <script>
        document.querySelectorAll('[data-barangay-search]').forEach((input) => {
            input.addEventListener('input', () => {
                const query = input.value.trim().toLocaleLowerCase();
                input.closest('fieldset').querySelectorAll('[data-barangay-option]').forEach((option) => {
                    option.hidden = !option.textContent.trim().toLocaleLowerCase().includes(query);
                });
            });
        });
    </script>
@endsection