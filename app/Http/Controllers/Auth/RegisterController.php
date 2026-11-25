<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'last_name'      => ['required', 'string', 'max:60'],
            'first_name'     => ['required', 'string', 'max:60'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'email'          => ['required', 'email', 'max:150', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'An account with this email already exists.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        $fullName = $validated['last_name'] . ', ' . $validated['first_name']
            . ($validated['middle_initial'] ? ' ' . rtrim($validated['middle_initial'], '.') . '.' : '');

        $user = User::create([
            'name'     => $fullName,
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Account created successfully.');
    }
}