<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VaxTrack - @yield('title')</title>
    <script>
        (function () {
            const saved = localStorage.getItem('vaxtrack-theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/patients.css') }}">
    <link rel="stylesheet" href="{{ asset('css/operations.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vt-ui.css') }}">
</head>
<body class="dashboard-body">
    @php($currentUser = auth()->user())
    <div class="vt-sidebar-overlay" data-sidebar-overlay hidden></div>
    <div class="app-shell vt-app-shell">
        <aside class="sidebar vt-sidebar" id="vt-sidebar" aria-label="Main navigation">
            <div class="sidebar-brand vt-sidebar__brand">
                <div class="brand-pill" aria-hidden="true">V</div>
                <div class="vt-sidebar__brand-copy">
                    <div class="brand-name">VaxTrack</div>
                    <small>Immunization System</small>
                </div>
                <button class="vt-icon-button vt-sidebar__collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" title="Collapse sidebar">
                    <i data-lucide="panel-left-close" aria-hidden="true"></i>
                </button>
            </div>

            <nav class="vt-navigation" aria-label="Workspace navigation">
                <section class="nav-section vt-navigation__section">
                    <p class="nav-label">Main</p>
                    <div class="sidebar-nav">
                        <x-vt-sidebar-link :href="route('dashboard')" icon="layout-dashboard" label="Dashboard" :active="request()->routeIs('dashboard', 'bhw.dashboard', 'cho.dashboard')" />
                        @if ($currentUser->isBhw())
                            <x-vt-sidebar-link :href="route('patients.index')" icon="baby" label="Patients" :active="request()->routeIs('patients.*')" />
                            <x-vt-sidebar-link :href="route('immunizations.index')" icon="syringe" label="Immunization Records" :active="request()->routeIs('immunizations.*')" />
                        @endif
                    </div>
                </section>

                <section class="nav-section vt-navigation__section">
                    <p class="nav-label">Monitoring</p>
                    <div class="sidebar-nav">
                        @if ($currentUser->isBhw())
                            <x-vt-sidebar-link :href="route('schedule.index')" icon="calendar-days" label="Vaccination Schedule" :active="request()->routeIs('schedule.*')" />
                            <x-vt-sidebar-link :href="route('sms.index')" icon="message-square" label="SMS Monitoring" :active="request()->routeIs('sms.*')" />
                        @endif
                        <x-vt-sidebar-link :href="route('inventory.index')" icon="boxes" label="Vaccine Inventory" :active="request()->routeIs('inventory.*')" />
                        @if ($currentUser->isCho())
                            <x-vt-sidebar-link :href="route('cho.forecast')" icon="chart-no-axes-combined" label="Demand Forecast" :active="request()->routeIs('cho.forecast')" />
                        @endif
                    </div>
                </section>

                @if ($currentUser->isCho())
                    <section class="nav-section vt-navigation__section">
                        <p class="nav-label">Administration</p>
                        <div class="sidebar-nav">
                            <x-vt-sidebar-link :href="route('cho.stations.index')" icon="building-2" label="Health Stations" :active="request()->routeIs('cho.stations.*')" />
                            <x-vt-sidebar-link :href="route('cho.bhws.index')" icon="users" label="BHW Accounts" :active="request()->routeIs('cho.bhws.*')" />
                            <x-vt-sidebar-link :href="route('cho.vaccines.index')" icon="list-checks" label="Vaccine Master List" :active="request()->routeIs('cho.vaccines.*')" />
                        </div>
                    </section>
                @endif

                <section class="nav-section vt-navigation__section">
                    <p class="nav-label">Account</p>
                    <div class="sidebar-nav">
                        <x-vt-sidebar-link :href="route('settings.edit')" icon="settings" label="Settings" :active="request()->routeIs('settings.*')" />
                    </div>
                </section>
            </nav>

            <div class="sidebar-footer vt-sidebar__footer">
                <div class="vt-sidebar-user">
                    <span class="vt-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($currentUser->name, 0, 1)) }}</span>
                    <span class="vt-sidebar-user__copy"><strong>{{ $currentUser->name }}</strong><small>{{ $currentUser->role }} workspace</small></span>
                </div>
            </div>
        </aside>

        <div class="content-panel">
            <x-vt-topbar :current-user="$currentUser">@yield('title')</x-vt-topbar>
            <main class="dashboard-main">
                @if (session('success'))
                    <div class="alert-success" role="status"><i data-lucide="circle-check" aria-hidden="true"></i>{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert-error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i>{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
    <dialog class="workflow-dialog workflow-confirmation-dialog" data-confirmation-dialog aria-labelledby="workflow-confirmation-title">
        <div class="workflow-dialog__header">
            <div>
                <p class="eyebrow">Please confirm</p>
                <h2 class="panel-title" id="workflow-confirmation-title">Confirm this action</h2>
            </div>
        </div>
        <p data-confirmation-message></p>
        <div class="workflow-dialog__actions">
            <button type="button" class="btn btn-secondary" data-dialog-close>Cancel</button>
            <button type="button" class="btn btn-primary" data-confirmation-submit>Confirm</button>
        </div>
    </dialog>
    <dialog class="workflow-dialog workflow-confirmation-dialog" data-workflow-result-dialog aria-labelledby="workflow-result-title">
        <div class="workflow-dialog__success" data-modal-success-panel hidden>
            <i data-lucide="circle-check" aria-hidden="true"></i>
            <h3 data-modal-success-title>Action completed</h3>
            <p data-modal-success-message>The requested action completed successfully.</p>
            <div class="workflow-dialog__actions">
                <button class="btn btn-primary" type="button" data-modal-done>Done</button>
            </div>
        </div>
    </dialog>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        (() => {
            const root = document.documentElement;
            const sidebar = document.querySelector('.vt-sidebar');
            const overlay = document.querySelector('[data-sidebar-overlay]');
            const palette = document.querySelector('[data-command-palette]');
            const commandInput = document.querySelector('[data-command-input]');
            const closeSidebar = () => {
                sidebar.classList.remove('is-open');
                overlay.hidden = true;
                document.querySelector('.vt-menu-toggle')?.setAttribute('aria-expanded', 'false');
            };

            if (localStorage.getItem('vaxtrack-sidebar') === 'collapsed') {
                root.classList.add('vt-sidebar-collapsed');
            }
            document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
                const collapsed = root.classList.toggle('vt-sidebar-collapsed');
                localStorage.setItem('vaxtrack-sidebar', collapsed ? 'collapsed' : 'expanded');
                const collapseButton = document.querySelector('[data-sidebar-collapse]');
                collapseButton?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                collapseButton?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                const icon = collapseButton?.querySelector('[data-lucide]');
                if (icon) icon.setAttribute('data-lucide', collapsed ? 'panel-left-open' : 'panel-left-close');
                window.lucide?.createIcons();
            });
            document.querySelector('.vt-menu-toggle')?.addEventListener('click', (event) => {
                const open = sidebar.classList.toggle('is-open');
                overlay.hidden = !open;
                event.currentTarget.setAttribute('aria-expanded', String(open));
            });
            overlay?.addEventListener('click', closeSidebar);
            document.querySelectorAll('[data-dismiss]').forEach((button) => {
                button.addEventListener('click', () => button.closest('.vt-callout')?.remove());
            });

            document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
                const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
                root.dataset.theme = theme;
                localStorage.setItem('vaxtrack-theme', theme);
                const icon = document.querySelector('[data-theme-icon]');
                if (icon) icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
                window.lucide?.createIcons();
            });

            const openPalette = () => {
                if (!palette.open) palette.showModal();
                commandInput?.focus();
            };
            document.querySelector('[data-command-open]')?.addEventListener('click', openPalette);
            document.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    openPalette();
                }
                if (event.key === 'Escape') closeSidebar();
            });
            commandInput?.addEventListener('input', () => {
                const query = commandInput.value.trim().toLowerCase();
                document.querySelectorAll('.vt-command__links a').forEach((link) => {
                    link.hidden = query !== '' && !link.textContent.toLowerCase().includes(query);
                });
            });
            window.lucide?.createIcons();
        })();
    </script>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
    <script src="{{ asset('js/auto-submit-filters.js') }}"></script>
    <script src="{{ asset('js/api-workflows.js') }}"></script>
    <script src="{{ asset('js/workflow-modals.js') }}"></script>
    @stack('scripts')
</body>
</html>
