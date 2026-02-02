<?php

namespace Modules\NsSpecialCustomer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSpecialCustomerPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The permission to check (can be short form like 'cashback' or full form like 'special.customer.cashback')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // If permission doesn't start with 'special.customer.', prefix it
        $fullPermission = $permission;
        if (!str_starts_with($permission, 'special.customer.')) {
            $fullPermission = 'special.customer.' . $permission;
        }

        if (!ns()->allowedTo($fullPermission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('You do not have permission to perform this action.')
                ], 403);
            }

            return redirect()
                ->route('ns.dashboard.home')
                ->with('error', __('You do not have permission to perform this action.'));
        }

        return $next($request);
    }
}

