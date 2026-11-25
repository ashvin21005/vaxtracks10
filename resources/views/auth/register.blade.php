<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VaxTrack - Create Account</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="split-container">
        <!-- LEFT: Brand panel -->
        <div class="brand-panel">
            <div class="brand-top">
                <div class="brand-logo">V</div>
                <div>
                    <div class="brand-name">VaxTrack</div>
                    <div class="brand-sub">Tacloban System</div>
                </div>
            </div>

            <div class="brand-middle">
                <h1>Immunization<br>Monitoring System</h1>
                <p>Manage child immunization records, vaccination schedules, vaccine inventory, and notifications in one centralized system.</p>
            </div>

            <div class="brand-bottom">
                Barangay Health Worker &amp; City Health Office
            </div>
        </div>

        <!-- RIGHT: Register form panel -->
        <div class="form-panel">
            <div class="form-inner">
                <div class="form-eyebrow">GET STARTED</div>
                <h2>Create your account</h2>
                <p class="form-subtext">Register to access VaxTrack.</p>

                @if ($errors->any())
                    <div class="error-box">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.submit') }}">
                    @csrf

                  <div class="form-group">
                   <label for="last_name">Last Name</label>
                   <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}"
                         class="@error('last_name') is-invalid @enderror"
                          placeholder="Enter your last name"
                           required autofocus>
                         @error('last_name')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label for="first_name">First Name</label>
    <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}"
        class="@error('first_name') is-invalid @enderror"
        placeholder="Enter your first name"
        required>
    @error('first_name')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-group">
    <label for="middle_initial">M.I. (Optional)</label>
    <input type="text" name="middle_initial" id="middle_initial" value="{{ old('middle_initial') }}"
        class="@error('middle_initial') is-invalid @enderror"
        placeholder="e.g. D."
        maxlength="5">
    @error('middle_initial')
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}"
                            class="@error('email') is-invalid @enderror"
                            placeholder="Enter your email"
                            required>
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password"
                            class="@error('password') is-invalid @enderror"
                            placeholder="At least 8 characters"
                            required>
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            placeholder="Re-enter your password"
                            required>
                    </div>

                    <button type="submit" class="btn-login">Create Account</button>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="{{ route('login') }}" class="forgot-link">Sign in</a>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
</body>
</html>