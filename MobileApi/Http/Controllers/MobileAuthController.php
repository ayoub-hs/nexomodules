<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Mobile Authentication Controller
 * 
 * Handles mobile app authentication including login, logout, and permissions.
 */
class MobileAuthController extends Controller
{
    /**
     * Login endpoint for mobile app
     * 
     * Validates credentials and returns a Sanctum token.
     * 
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        // Attempt to authenticate the user
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if (! $user instanceof User || ! $user->active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['The provided account is inactive.'],
            ]);
        }

        // Revoke existing tokens for this device to prevent token accumulation
        $user->tokens()->where('name', $request->device_name)->delete();

        // Create a new token
        $token = $user->createToken($request->device_name);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * Logout endpoint for mobile app
     * 
     * Revokes the current authentication token.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Get user permissions endpoint
     * 
     * Returns the authenticated user's permissions for UI gating.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->loadMissing('roles.permissions');

        $permissions = $this->resolvePermissionNamespaces($user);
        $roles = $this->resolveRoleNamespaces($user);

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissions,
                'roles' => $roles,
            ],
        ]);
    }

    /**
     * Get current user info
     *
     * Returns information about the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->loadMissing('roles.permissions');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user?->id,
                'name' => $this->formatUserName($user),
                'email' => $user?->email,
                'roles' => $this->resolveRoleNamespaces($user),
            ],
        ]);
    }

    private function formatUserName(?User $user): string
    {
        if (! $user instanceof User) {
            return '';
        }

        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->username;
    }

    private function resolvePermissionNamespaces(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('namespace'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    private function resolveRoleNamespaces(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $user->roles
            ->pluck('namespace')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
