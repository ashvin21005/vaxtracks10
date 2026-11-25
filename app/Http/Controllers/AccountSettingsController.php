<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountSettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'regex:/^[A-Za-z0-9._-]+$/', 'max:60', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user->id)],
        ]);
        $user->update($data);

        return redirect()->route('settings.edit')->with('success', 'Account details updated.');
    }
}
