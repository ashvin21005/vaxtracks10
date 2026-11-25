<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VaxTrack - Change Password</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="form-panel">
        <section class="form-inner">
            <p class="form-eyebrow">ACCOUNT SECURITY</p>
            <h2>Set a new password</h2>
            <p class="form-subtext">Choose a new password before continuing to VaxTrack.</p>
            @if ($errors->any())
                <div class="error-box"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="current_password">Temporary password</label>
                    <input type="password" name="current_password" id="current_password" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label for="password">New password</label>
                    <input type="password" name="password" id="password" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-login">Update password</button>
            </form>
            <form method="POST" action="{{ route('logout') }}" class="password-logout">@csrf<button type="submit">Sign out</button></form>
        </section>
    </main>
    <script src="{{ asset('js/password-visibility.js') }}"></script>
</body>
</html>