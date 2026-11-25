<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    public function edit()
    {
        return view('auth.password-change');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
            'current_password' => ['required', 'current_password'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Your password has been updated.');
    }
}
