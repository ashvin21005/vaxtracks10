<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isActive()) {
            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Change your password through the web application before using the API.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
