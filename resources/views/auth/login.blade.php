<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>VaxTrack - Login</title>
    <script>
        (function () {
            const saved = localStorage.getItem('vaxtrack-theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vt-ui.css') }}">
</head>
<body>
    <div class="split-container vt-login">
        <section class="brand-panel vt-login__hero" aria-labelledby="login-hero-title">
            <div class="brand-top">
                <div class="brand-logo">V</div>
                <div>
                    <div class="brand-name">VaxTrack</div>
                    <div class="brand-sub">Tacloban System</div>
                </div>
            </div>

            <div class="brand-middle vt-login__intro">
                <span class="vt-login__eyebrow"><i data-lucide="heart-pulse" aria-hidden="true"></i> Care coordination, made clearer</span>
                <h1 id="login-hero-title">Immunization<br>Monitoring System</h1>
                <p>Manage child immunization records, vaccination schedules, vaccine inventory, and notifications in one centralized system.</p>
                <ul class="vt-login__features">
                    <li><i data-lucide="baby" aria-hidden="true"></i><span>Keep child and guardian records together</span></li>
                    <li><i data-lucide="calendar-check" aria-hidden="true"></i><span>Coordinate upcoming vaccination schedules</span></li>
                    <li><i data-lucide="boxes" aria-hidden="true"></i><span>Monitor vaccine stock by health station</span></li>
                </ul>
                <div class="vt-login__float-card">
                    <svg viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="48" cy="48" r="43" fill="rgba(255,255,255,.13)" stroke="rgba(255,255,255,.3)"/>
                        <path d="M48 24v48M24 48h48" stroke="#C7F9EF" stroke-width="8" stroke-linecap="round"/>
                        <circle cx="70" cy="27" r="12" fill="#F8C677" stroke="#fff" stroke-width="3"/>
                        <path d="m66 27 3 3 5-6" stroke="#173D58" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><strong>Every dose matters</strong><small>Supporting healthier communities</small></span>
                    <i data-lucide="sparkles" aria-hidden="true"></i>
                </div>
                <div class="vt-login__illustration" aria-hidden="true">
                    <div class="vt-login__illustration-orbit"></div>
                    <div class="vt-login__decor vt-login__decor--one"></div>
                    <div class="vt-login__decor vt-login__decor--two"></div>
                </div>
            </div>

            <div class="brand-bottom vt-login__trust"><i data-lucide="shield-check" aria-hidden="true"></i> Barangay Health Worker &amp; City Health Office</div>
        </section>

        <main class="form-panel vt-login__form-panel">
            <div class="form-inner">
                <button class="vt-icon-button vt-login__theme" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme"><i data-lucide="moon" aria-hidden="true" data-theme-icon></i></button>
                <div class="form-eyebrow">WELCOME BACK</div>
                <h2>Sign in to your account</h2>
                <p class="form-subtext">Enter your credentials to access VaxTrack.</p>

                @if ($errors->any())
                    <div class="error-box" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" id="login-form">
                    @csrf

                    <div class="form-group">
                        <label for="login">Username or Email</label>
                        <input type="text" name="login" id="login" value="{{ old('login') }}"
                            class="@error('login') is-invalid @enderror"
                            placeholder="Enter your username or email"
                            autocomplete="username" required autofocus>
                        @error('login')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <div class="vt-login__password-label"><label for="password">Password</label><details class="forgot-password-ui"><summary>Forgot password?</summary><p>Contact your CHO administrator to reset your VaxTrack account password.</p></details></div>
                        <input type="password" name="password" id="password"
                            class="@error('password') is-invalid @enderror"
                            placeholder="Enter your password"
                            autocomplete="current-password" required>
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="remember-group">
                        <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                        <label for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn-login" id="login-submit"><span class="login-submit-label">Sign In</span></button>
                </form>

                <div class="form-footer">
                    Need account access? Contact your CHO administrator.<br><br>
                    VaxTrack Tacloban System<br>
                    Immunization Monitoring &amp; Management
                </main>
            </div>
        </div>
    </div>
    <script>
        document.querySelector('#login-form')?.addEventListener('submit', function () {
            const button = document.querySelector('#login-submit');
            button.disabled = true;
            button.querySelector('.login-submit-label').textContent = 'Signing in…';
        });
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        window.lucide?.createIcons();
        document.querySelector('[data-theme-toggle]')?.addEventListener('click', function () {
            const root = document.documentElement;
            const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = theme;
            localStorage.setItem('vaxtrack-theme', theme);
            const icon = document.querySelector('[data-theme-icon]');
            if (icon) icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
            window.lucide?.createIcons();
        });
    </script>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
</body>
</html>