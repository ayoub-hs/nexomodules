<?php

namespace Modules\MobileApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class MobileAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * Authenticates requests using Laravel Sanctum bearer tokens.
     * Returns JSON error responses for API clients.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$guards
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $token = $request->bearerToken();
        
        Log::debug('[MobileAuth] Authentication attempt', [
            'path' => $request->path(),
            'has_token' => !empty($token),
            'token_prefix' => $token ? substr($token, 0, 10) . '...' : null,
        ]);
        
        if (!$token) {
            Log::warning('[MobileAuth] No bearer token provided', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid bearer token.'
            ], 401);
        }
        
        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            Log::warning('[MobileAuth] Invalid or expired token', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token. Please login again.'
            ], 401);
        }
        
        Log::debug('[MobileAuth] Authentication successful', [
            'user_id' => $accessToken->tokenable_id,
            'token_name' => $accessToken->name,
            'path' => $request->path(),
        ]);
        
        // Set the authenticated user on the request
        $request->setUserResolver(function () use ($accessToken) {
            return $accessToken->tokenable;
        });
        
        return $next($request);
    }
}
