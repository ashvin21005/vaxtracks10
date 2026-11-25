<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $matches = User::query()
            ->where('email', $data['login'])
            ->orWhere('username', $data['login'])
            ->limit(2)
            ->get();
        $user = $matches->count() === 1 ? $matches->first() : null;

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->isActive()) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 422);
        }

        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Change your password through the web application before requesting an API token.',
                'code' => 'password_change_required',
            ], 403);
        }

        $token = $user->createToken($data['device_name']);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'health_station_id' => $user->health_station_id,
            ],
        ], 201);
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked.']);
    }
}
