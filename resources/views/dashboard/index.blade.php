@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
    <div class="dashboard-hero">
        <div>
            <p class="eyebrow">Operational view</p>
            <h1 class="page-title">Immunization dashboard</h1>
        </div>
        <div class="hero-actions">
            <a href="{{ route('patients.create') }}" class="qa-btn qa-primary">+ Register Child</a>
            <a href="{{ route('inventory.index') }}" class="qa-btn">Vaccine Inventory</a>
        </div>
    </div>

    <div class="stat-grid vt-dashboard-kpis">
        <x-vt-kpi-card label="Total Registered Children" :value="$stats['total_registered']" icon="baby" tone="blue" :href="route('patients.index')" />
        <x-vt-kpi-card label="Pending Vaccinations" :value="$stats['upcoming']" icon="calendar-check" tone="green" :href="route('schedule.index', ['status' => 'PENDING'])" />
        <x-vt-kpi-card label="Due Vaccinations" :value="$stats['delayed']" icon="clock-3" tone="amber" :href="route('schedule.index', ['status' => 'DUE'])" />
        <x-vt-kpi-card label="Overdue Vaccinations" :value="$stats['missed']" icon="calendar-x-2" tone="red" :href="route('schedule.index', ['status' => 'OVERDUE'])" />
    </div>

    <div class="panel-grid panel-grid-2">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Upcoming Vaccinations</h2>
                <span class="mini-tag success">Live</span>
            </div>
            @if (count($upcomingVaccinations))
                <x-vt-table><table class="data-table">
                    <thead>
                        <tr><th>Child</th><th>Vaccine</th><th>Due Date</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($upcomingVaccinations as $row)
                            <tr>
                                <td><span class="vt-person-link"><span class="vt-table-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row->child->first_name, 0, 1).\Illuminate\Support\Str::substr($row->child->last_name, 0, 1)) }}</span>{{ $row->child->full_name }}</span></td>
                                <td>{{ $row->vaccine }} · Dose {{ $row->dose_number }}</td>
                                <td>{{ $row->scheduled_date->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></x-vt-table>
            @else
                <x-vt-empty-state title="No upcoming vaccinations." icon="calendar-check-2" />
            @endif
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Follow-up notifications</h2>
                <span class="mini-tag neutral">This station</span>
            </div>
            <div class="spark-grid">
                <div>
                    <span class="spark-label">Due doses</span>
                    <strong>{{ $notifications['delayed'] }}</strong>
                </div>
                <div>
                    <span class="spark-label">Overdue doses</span>
                    <strong>{{ $notifications['missed'] }}</strong>
                </div>
                <div>
                    <span class="spark-label">Pending reminders</span>
                    <strong>{{ $notifications['reminders'] }}</strong>
                </div>
                <div>
                    <span class="spark-label">Registered children</span>
                    <strong>{{ $stats['total_registered'] }}</strong>
                </div>
            </div>
            <a href="{{ route('sms.index') }}" class="btn btn-secondary">View SMS monitoring</a>
        </div>
    </div>

    <div class="panel-grid panel-grid-3">
        <div class="panel">
            <h2 class="panel-title">Due Vaccinations</h2>
            @if (count($delayedVaccinations))
                <x-vt-table><table class="data-table">
                    <thead>
                        <tr><th>Child</th><th>Vaccine</th><th>Due Date</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($delayedVaccinations as $row)
                            <tr>
                                <td><span class="vt-person-link"><span class="vt-table-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row->child->first_name, 0, 1).\Illuminate\Support\Str::substr($row->child->last_name, 0, 1)) }}</span>{{ $row->child->full_name }}</span></td>
                                <td>{{ $row->vaccine }} · Dose {{ $row->dose_number }}</td>
                                <td><x-vt-badge tone="warning">{{ ($row->due_date ?? $row->scheduled_date)->format('M d, Y') }}</x-vt-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></x-vt-table>
            @else
                <x-vt-empty-state title="No doses are due." icon="circle-check" />
            @endif
        </div>

        <div class="panel">
            <h2 class="panel-title">Overdue Vaccinations</h2>
            @if (count($missedVaccinations))
                <x-vt-table><table class="data-table">
                    <thead>
                        <tr><th>Child</th><th>Vaccine</th><th>Due Since</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($missedVaccinations as $row)
                            <tr>
                                <td><span class="vt-person-link"><span class="vt-table-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row->child->first_name, 0, 1).\Illuminate\Support\Str::substr($row->child->last_name, 0, 1)) }}</span>{{ $row->child->full_name }}</span></td>
                                <td>{{ $row->vaccine }} · Dose {{ $row->dose_number }}</td>
                                <td><x-vt-badge tone="danger">{{ ($row->due_date ?? $row->scheduled_date)->format('M d, Y') }}</x-vt-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></x-vt-table>
            @else
                <x-vt-empty-state title="No overdue vaccinations." icon="calendar-check-2" />
            @endif
        </div>

        <div class="panel panel-highlight">
            <h2 class="panel-title">Map + Heat Layer</h2>
            <div class="quick-link-list"><a href="{{ route('immunizations.index') }}">Immunization history <span>Review recorded doses</span></a><a href="{{ route('schedule.index') }}">Vaccination schedule <span>See upcoming and overdue doses</span></a><a href="{{ route('inventory.index') }}">Vaccine inventory <span>Check available stock</span></a></div>
        </div>
    </div>

    <div class="panel panel-full">
        <h2 class="panel-title">Recent Immunization Records</h2>
        @if (count($recentRecords))
            <x-vt-table><table class="data-table">
                <thead>
                    <tr><th>Child</th><th>Vaccine</th><th>Date Administered</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($recentRecords as $row)
                        <tr>
                            <td><span class="vt-person-link"><span class="vt-table-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($row->child->first_name, 0, 1).\Illuminate\Support\Str::substr($row->child->last_name, 0, 1)) }}</span>{{ $row->child->full_name }}</span></td>
                            <td>{{ $row->vaccine }} · Dose {{ $row->dose_number }}</td>
                            <td>{{ $row->administration_date_label }}</td>
                            <td><span class="badge badge-green">{{ $row->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></x-vt-table>
        @else
            <x-vt-empty-state title="No recent records." icon="clipboard-list" />
        @endif
    </div>
@endsection
