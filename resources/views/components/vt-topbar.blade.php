@props(['currentUser'])
<header class="vt-topbar">
    <div class="vt-topbar__heading">
        <button class="vt-icon-button vt-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="vt-sidebar">
            <i data-lucide="menu" aria-hidden="true"></i>
        </button>
        <div>
            <div class="vt-topbar__title">{{ $slot }}</div>
            <div class="vt-topbar__subtitle">{{ $currentUser->healthStation?->name ?? ($currentUser->barangay ? 'Barangay ' . $currentUser->barangay : 'City Health Office') }}</div>
        </div>
    </div>
    <div class="vt-topbar__actions">
        <button class="vt-search-trigger" type="button" data-command-open aria-haspopup="dialog">
            <i data-lucide="search" aria-hidden="true"></i><span>Search</span><kbd>Ctrl K</kbd>
        </button>
        {{-- This view has no unread-count value; do not show a fabricated notification badge. --}}
        @if ($currentUser->isBhw())
            <a class="vt-icon-button" href="{{ route('sms.index') }}" aria-label="Notifications and SMS monitoring">
                <i data-lucide="bell" aria-hidden="true"></i>
            </a>
        @endif
        <button class="vt-icon-button" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
            <i data-lucide="moon" aria-hidden="true" data-theme-icon></i>
        </button>
        <details class="vt-user-menu">
            <summary aria-label="Open account menu">
                <span class="vt-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($currentUser->name, 0, 1)) }}</span>
                <span class="vt-user-menu__name">{{ $currentUser->name }}</span>
                <i data-lucide="chevron-down" aria-hidden="true"></i>
            </summary>
            <div class="vt-user-menu__panel">
                <strong>{{ $currentUser->name }}</strong>
                <span>{{ $currentUser->role }}</span>
                <a href="{{ route('settings.edit') }}"><i data-lucide="settings-2" aria-hidden="true"></i>Account settings</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><i data-lucide="log-out" aria-hidden="true"></i>Logout</button>
                </form>
            </div>
        </details>
    </div>
</header>
<dialog class="vt-command" data-command-palette aria-label="Quick navigation">
    <form method="dialog" class="vt-command__search">
        <i data-lucide="search" aria-hidden="true"></i>
        <label class="sr-only" for="vt-command-input">Search navigation</label>
        <input id="vt-command-input" type="search" placeholder="Search pages..." autocomplete="off" data-command-input>
        <button type="submit" aria-label="Close search"><kbd>ESC</kbd></button>
    </form>
    <nav aria-label="Quick navigation" class="vt-command__links">
        <a href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard" aria-hidden="true"></i>Dashboard</a>
        @if ($currentUser->isBhw())
            <a href="{{ route('patients.index') }}"><i data-lucide="baby" aria-hidden="true"></i>Patients</a>
            <a href="{{ route('immunizations.index') }}"><i data-lucide="syringe" aria-hidden="true"></i>Immunization Records</a>
            <a href="{{ route('schedule.index') }}"><i data-lucide="calendar-days" aria-hidden="true"></i>Vaccination Schedule</a>
        @endif
        <a href="{{ route('inventory.index') }}"><i data-lucide="boxes" aria-hidden="true"></i>Vaccine Inventory</a>
        @if ($currentUser->isCho())
            <a href="{{ route('cho.forecast') }}"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i>Demand Forecast</a>
            <a href="{{ route('cho.stations.index') }}"><i data-lucide="building-2" aria-hidden="true"></i>Health Stations</a>
            <a href="{{ route('cho.bhws.index') }}"><i data-lucide="users" aria-hidden="true"></i>BHW Accounts</a>
            <a href="{{ route('cho.vaccines.index') }}"><i data-lucide="list-checks" aria-hidden="true"></i>Vaccine Master List</a>
        @endif
        @if ($currentUser->isBhw())
            <a href="{{ route('sms.index') }}"><i data-lucide="message-square" aria-hidden="true"></i>SMS Monitoring</a>
        @endif
        <a href="{{ route('settings.edit') }}"><i data-lucide="settings" aria-hidden="true"></i>Settings</a>
    </nav>
</dialog>
