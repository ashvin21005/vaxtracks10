@extends('layouts.app')
@section('title', 'SMS Monitoring')
@section('content')
    <div class="page-header operations-heading"><div><p class="eyebrow">Guardian outreach</p><h1 class="page-title">SMS Monitoring</h1><p class="page-subtitle">Reminder queue and delivery status for scheduled vaccinations.</p></div></div>
    @if ($errors->has('sms'))
        <div class="alert-error" role="alert">{{ $errors->first('sms') }}</div>
    @endif
    <section class="operations-summary">
        <a class="operations-metric" href="{{ route('sms.index', ['tab' => 'sent', 'search' => $search !== '' ? $search : null, 'status' => 'SENT', 'reminder' => $reminder]) }}" aria-label="Filter reminders: Sent">
            <span>Sent</span><strong data-sms-count="sent">{{ $summary['sent'] }}</strong>
        </a>
        <a class="operations-metric" href="{{ route('sms.index', ['tab' => 'all', 'search' => $search !== '' ? $search : null, 'status' => 'PENDING', 'reminder' => $reminder]) }}" aria-label="Filter reminders: Pending">
            <span>Pending</span><strong data-sms-count="pending">{{ $summary['pending'] }}</strong>
        </a>
        <a class="operations-metric" href="{{ route('sms.index', ['tab' => 'failed', 'search' => $search !== '' ? $search : null, 'status' => 'FAILED', 'reminder' => $reminder]) }}" aria-label="Filter reminders: Failed">
            <span>Failed</span><strong data-sms-count="failed">{{ $summary['failed'] }}</strong>
        </a>
    </section>
    <section class="panel operations-panel">
        <div class="operations-toolbar">
            <div>
                <h2 class="panel-title">Reminder queue</h2>
                <span class="operations-count">{{ $groupedReminders->total() }} scheduled doses</span>
            </div>
            <div>
                <nav class="operations-filters" aria-label="Reminder workflow">
                    @foreach (['due_soon' => 'Due Soon — Next 7 Days', 'all' => 'All Scheduled', 'sent' => 'Sent', 'failed' => 'Failed'] as $tabName => $label)
                        <a class="btn btn-sm {{ $tab === $tabName ? 'btn-primary' : 'btn-secondary' }}"
                            href="{{ route('sms.index', ['tab' => $tabName, 'search' => $search !== '' ? $search : null, 'status' => $status ?: null, 'reminder' => $reminder]) }}"
                            @if ($tab === $tabName) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <form method="GET" class="operations-filters">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Child, guardian, contact, or vaccine" aria-label="Search SMS reminders">
                    <select name="status" aria-label="Filter SMS status">
                        <option value="">All statuses</option>
                        @foreach (['PENDING', 'SENT', 'FAILED'] as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(strtolower($option)) }}</option>
                        @endforeach
                    </select>
                    <select name="reminder" aria-label="Filter reminder stage">
                        <option value="">All stages</option>
                        @foreach ([7, 3, 1] as $days)
                            <option value="{{ $days }}" @selected($reminder === $days)>{{ $days }}-day reminder</option>
                        @endforeach
                    </select>
                    <button class="btn btn-secondary" type="submit">Search</button>
                </form>
            </div>
        </div>
        <div class="operations-toolbar">
            <p class="operations-count" data-sms-feedback role="status" aria-live="polite"></p>
            <form id="sms-bulk-send-form" method="POST" action="{{ route('sms.bulk-send') }}" data-sms-bulk-send-form>
                @csrf
                <button class="btn btn-secondary" type="submit" data-sms-bulk-send-button>Send Selected Next SMS</button>
            </form>
        </div>
        @if ($groupedReminders->count())
            <div class="table-scroll">
                <table class="data-table operations-table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">Select</span></th>
                            <th scope="col">Patient &amp; Guardian</th>
                            <th scope="col">Vaccine &amp; Dose</th>
                            <th scope="col">Scheduled Date</th>
                            <th scope="col">Reminder Stages</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupedReminders as $group)
                            @php($daysUntil = (int) today()->diffInDays($group->scheduled_date, false))
                            @php($actionNotification = $group->next_send_notification ?? $group->reminders->first())
                            <tr data-sms-row="{{ $group->key }}">
                                <td>
                                    <input type="checkbox" name="notification_ids[]" form="sms-bulk-send-form"
                                        value="{{ $group->next_send_notification?->id }}"
                                        aria-label="Select {{ $group->child->full_name }} reminder"
                                        data-sms-bulk-checkbox
                                        @disabled(! $group->next_send_notification)>
                                </td>
                                <td>
                                    <a href="{{ route('patients.show', $group->child) }}">{{ $group->child->full_name }}</a>
                                    <small>{{ $group->child->baby_book_id }}</small>
                                    <div>{{ $group->child->guardian?->full_name ?? '—' }}</div>
                                    <small>{{ $group->child->guardian?->contact_number ?? '—' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $group->schedule?->vaccine ?? '—' }}</strong>
                                    <div>{{ $group->schedule ? 'Dose '.$group->schedule->dose_number : 'Dose —' }}</div>
                                </td>
                                <td>
                                    {{ $group->scheduled_date->format('M d, Y') }}
                                    <small>
                                        @if ($daysUntil < 0)
                                            Overdue
                                        @elseif ($daysUntil === 0)
                                            Today
                                        @else
                                            In {{ $daysUntil }} {{ $daysUntil === 1 ? 'day' : 'days' }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    <div class="admin-row-actions">
                                        @foreach ([7, 3, 1] as $days)
                                            @php($stageNotification = $group->reminders->firstWhere('reminder_days', $days))
                                            @php($stageStatus = $stageNotification?->status ?? 'MISSING')
                                            <span data-sms-stage>
                                                <span class="status-pill status-{{ $stageStatus === 'SENT' ? 'good' : ($stageStatus === 'PENDING' ? 'warning' : ($stageStatus === 'FAILED' ? 'danger' : 'neutral')) }}"
                                                    data-status-pill
                                                    data-status="{{ $stageStatus }}"
                                                    data-reminder-days="{{ $days }}"
                                                    data-notification-id="{{ $stageNotification?->id }}"
                                                    data-send-url="{{ $stageNotification ? route('sms.send', $stageNotification) : '' }}"
                                                    data-resend-url="{{ $stageNotification ? route('sms.resend', $stageNotification) : '' }}">
                                                    {{ $days }}d: {{ $stageStatus === 'MISSING' ? '—' : $stageStatus }}
                                                </span>
                                                <small class="field-error" data-failure-reason @if (! $stageNotification?->failure_reason) hidden @endif>{{ $stageNotification?->failure_reason }}</small>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <div class="schedule-actions">
                                        <form method="POST" action="{{ $group->next_send_notification?->status === 'FAILED' ? route('sms.resend', $actionNotification) : route('sms.send', $actionNotification) }}" data-sms-send-form data-sms-next-form>
                                            @csrf
                                            <button class="btn btn-sm btn-primary" type="submit" @disabled(! $group->next_send_notification)>
                                                {{ $group->next_send_notification?->status === 'FAILED' ? 'Retry Next SMS' : 'Mark Next Sent' }}
                                            </button>
                                        </form>
                                        <details class="schedule-date-edit">
                                            <summary>Stage overrides</summary>
                                            @foreach ($group->reminders as $reminder)
                                                <strong>{{ $reminder->reminder_days }}d · {{ $reminder->status }}</strong>
                                                <div class="admin-row-actions">
                                                    @if ($reminder->status === 'PENDING')
                                                        <form method="POST" action="{{ route('sms.send', $reminder) }}" data-sms-send-form>
                                                            @csrf
                                                            <button class="btn btn-sm btn-secondary" type="submit">Send {{ $reminder->reminder_days }}d SMS</button>
                                                        </form>
                                                    @endif
                                                    @if ($reminder->status === 'FAILED')
                                                        <form method="POST" action="{{ route('sms.resend', $reminder) }}" data-sms-send-form>
                                                            @csrf
                                                            <button class="btn btn-sm btn-secondary" type="submit">Resend {{ $reminder->reminder_days }}d SMS</button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('sms.update', $reminder) }}" data-sms-update-form>
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="btn btn-sm btn-secondary" type="submit" name="status" value="SENT">Mark Sent (No SMS)</button>
                                                        <button class="btn btn-sm btn-secondary" type="submit" name="status" value="FAILED">Mark Failed</button>
                                                        <button class="btn btn-sm btn-secondary" type="submit" name="status" value="PENDING">Reset to Pending</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Showing {{ $groupedReminders->firstItem() }}–{{ $groupedReminders->lastItem() }} of {{ $groupedReminders->total() }}</span>
                {{ $groupedReminders->links('pagination::bootstrap-4') }}
            </div>
        @else
            <div class="operations-empty"><strong>No reminders found</strong><span>Create a vaccination schedule to queue 7-, 3-, and 1-day reminders.</span></div>
        @endif
    </section>
    <p class="form-preview-note">SMS reminders are sent manually per stage via SkySMS when you click "Mark Next Sent" or "Retry Next SMS".</p>
    <script src="{{ asset('js/operations-sms.js') }}" defer></script>
@endsection
