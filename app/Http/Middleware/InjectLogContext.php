<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InjectLogContext
{
    /**
     * Add request-scoped context to every log entry and set X-Request-ID header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::uuid()->toString();

        $user = Auth::user();
        $userId = $user?->id ?? 'guest';
        $userRole = $user && isset($user->role)
            ? (is_object($user->role) ? $user->role->value : $user->role)
            : 'guest';

        Log::withContext([
            'request_id' => $requestId,
            'user_id' => $userId,
            'user_role' => $userRole,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }
}
