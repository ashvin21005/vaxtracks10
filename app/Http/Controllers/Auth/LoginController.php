<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = User::query()
            ->where('email', $credentials['login'])
            ->orWhere('username', $credentials['login'])
            ->orWhere('name', $credentials['login'])
            ->first();

        if ($user && $user->isActive() && Auth::attempt([
            'email' => $user->email,
            'password' => $credentials['password'],
            'account_status' => 'ACTIVE',
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->route($user->must_change_password ? 'password.change' : 'dashboard');
        }

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
