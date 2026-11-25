@extends('layouts.app')
@section('title', 'CHO Dashboard')
@section('content')
    <div class="page-header">
        <div>
            <p class="eyebrow">City-wide monitoring</p>
            <h1 class="page-title">CHO Administrative Dashboard</h1>
            <p class="page-subtitle">City-wide health station and immunization overview.</p>
        </div>
        <div class="header-actions"><a class="btn btn-secondary" href="{{ route('cho.stations.index') }}">Health stations</a><a class="btn btn-primary" href="{{ route('cho.bhws.create') }}">Add BHW</a></div>
    </div>
    {{-- Trend deltas are not present in the existing dashboard payload, so no trend values are fabricated. --}}
    <div class="stat-grid vt-dashboard-kpis">
        <x-vt-kpi-card label="Registered Children" :value="$stats['children']" icon="baby" tone="blue" />
        <x-vt-kpi-card label="Health Stations" :value="$stats['stations']" icon="building-2" tone="green" />
        <x-vt-kpi-card label="Barangays served" :value="$stats['barangays']" icon="map-pin" tone="amber" />
        <x-vt-kpi-card label="Active BHW Accounts" :value="$stats['bhws']" icon="users" tone="violet" />
    </div>
    @if ($notifications['delayed'] || $notifications['missed'] || $notifications['low_stock'])
        <div class="risk-alert vt-callout" role="status"><i data-lucide="triangle-alert" aria-hidden="true"></i><div><strong>City-wide follow-up</strong><span>{{ $notifications['delayed'] }} due doses · {{ $notifications['missed'] }} overdue doses · {{ $notifications['low_stock'] }} low-stock items</span></div><button class="vt-callout__dismiss" type="button" data-dismiss aria-label="Dismiss follow-up alert"><i data-lucide="x" aria-hidden="true"></i></button></div>
    @endif
    <div class="stat-grid vt-dashboard-kpis">
        <x-vt-kpi-card label="Pending vaccinations" :value="$stats['upcoming']" icon="calendar-check" tone="green" />
        <x-vt-kpi-card label="Due vaccinations" :value="$stats['delayed']" icon="clock-3" tone="amber" />
        <x-vt-kpi-card label="Overdue vaccinations" :value="$stats['missed']" icon="calendar-x-2" tone="red" />
        <x-vt-kpi-card label="Low-stock inventory entries" :value="$stats['low_stock']" icon="package-alert" tone="violet" :href="route('inventory.index')" />
    </div>
    <div class="vt-dashboard-charts">
        <x-vt-card class="vt-chart-card">
            <div class="panel-header"><div><h2 class="panel-title">Recorded dose coverage</h2><p class="vt-card__hint">Children with at least one recorded dose</p></div><span class="vt-chart-value">{{ $stats['coverage'] }}%</span></div>
            <div class="vt-chart-card__body">
                @if ($stats['children'] > 0)
                    <div class="vt-doughnut"><canvas id="coverage-chart" role="img" aria-label="Recorded dose coverage: {{ $stats['coverage'] }} percent"></canvas><span class="vt-chart-fallback" data-chart-fallback hidden>Chart unavailable. Please refresh the page.</span></div>
                    <div><strong class="vt-chart-legend__value">{{ $stats['coverage'] }}%</strong><span class="vt-chart-legend__label">Recorded dose coverage</span></div>
                @else
                    <div class="vt-chart-placeholder"><i data-lucide="chart-pie" aria-hidden="true"></i><span>No coverage data available yet.</span></div>
                @endif
            </div>
        </x-vt-card>
        <x-vt-card class="vt-chart-card">
            <div class="panel-header"><div><h2 class="panel-title">Upcoming vaccinations</h2><p class="vt-card__hint">Scheduled doses across all stations</p></div><span class="vt-chart-value">{{ $stats['upcoming'] }}</span></div>
            @if ($stats['upcoming'] > 0)
                <div class="vt-bar-chart"><canvas id="upcoming-chart" role="img" aria-label="Upcoming vaccinations: {{ $stats['upcoming'] }}"></canvas><span class="vt-chart-fallback" data-chart-fallback hidden>Chart unavailable. Please refresh the page.</span></div>
            @else
                <div class="vt-chart-placeholder"><i data-lucide="calendar-check-2" aria-hidden="true"></i><span>No upcoming vaccination data available yet.</span></div>
            @endif
        </x-vt-card>
    </div>
    <div class="panel-grid panel-grid-2">
        <section class="panel"><div class="panel-header"><h2 class="panel-title">Low-stock health stations</h2><a href="{{ route('inventory.index') }}">Inventory</a></div>
            @if ($lowStockStations->isNotEmpty())<x-vt-table><table class="data-table"><thead><tr><th>Station</th><th>Barangays</th><th>Low-stock items</th></tr></thead><tbody>@foreach ($lowStockStations as $station)<tr><td><a class="link-strong" href="{{ route('cho.stations.show', $station) }}">{{ $station->name }}</a></td><td>{{ $station->barangay_label }}</td><td><x-vt-badge tone="warning">{{ $station->low_stock_count }}</x-vt-badge></td></tr>@endforeach</tbody></table></x-vt-table>@else<x-vt-empty-state title="No low-stock items are recorded." icon="package-check" />@endif
        </section>
        <section class="panel"><div class="panel-header"><h2 class="panel-title">CHO monitoring</h2></div><div class="quick-link-list"><a href="{{ route('cho.forecast') }}">Vaccine demand forecast <span>Historical use, available stock, and planning estimates</span></a><a href="{{ route('cho.bhws.index') }}">BHW management <span>Accounts and station assignments</span></a></div></section>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            if (!window.Chart) {
                document.querySelectorAll('[data-chart-fallback]').forEach((fallback) => {
                    fallback.hidden = false;
                    const canvas = fallback.parentElement?.querySelector('canvas');
                    if (canvas) canvas.hidden = true;
                });
                return;
            }
            const primary = getComputedStyle(document.documentElement).getPropertyValue('--vt-primary').trim();
            const border = getComputedStyle(document.documentElement).getPropertyValue('--vt-border').trim();
            const muted = getComputedStyle(document.documentElement).getPropertyValue('--vt-text-muted').trim();
            const coverage = Number(@json($stats['coverage']));
            const upcoming = Number(@json($stats['upcoming']));
            const coverageCanvas = document.getElementById('coverage-chart');
            if (coverageCanvas) new Chart(coverageCanvas, {
                type: 'doughnut',
                data: { datasets: [{ data: [coverage, Math.max(0, 100 - coverage)], backgroundColor: [primary, border], borderWidth: 0, borderRadius: 5 }] },
                options: { cutout: '78%', plugins: { legend: { display: false }, tooltip: { enabled: false } }, maintainAspectRatio: false }
            });
            const upcomingCanvas = document.getElementById('upcoming-chart');
            if (upcomingCanvas) new Chart(upcomingCanvas, {
                type: 'bar',
                data: { labels: ['Upcoming'], datasets: [{ data: [upcoming], backgroundColor: [primary], borderRadius: 8, maxBarThickness: 56 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: muted } }, y: { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: border } } } }
            });
        })();
    </script>
@endpush