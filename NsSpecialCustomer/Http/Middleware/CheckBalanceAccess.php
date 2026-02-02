<?php

namespace Modules\NsSpecialCustomer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to control access to customer balance endpoint.
 *
 * This middleware ensures that:
 * 1. Users with 'special.customer.manage' permission can access any customer's balance
 * 2. Users with 'special.customer.pay-outstanding-tickets' permission can access any customer's balance
 * 3. Regular users can only access their own balance (with ownership check)
 */
class CheckBalanceAccess
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request The incoming request
     * @param Closure $next The next middleware handler
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow if user has manage or pay-outstanding-tickets permission
        if (ns()->allowedTo('special.customer.manage') || ns()->allowedTo('special.customer.pay-outstanding-tickets')) {
            return $next($request);
        }
        
        // Otherwise, check ownership using the EnsureCustomerOwnership middleware
        $ownershipMiddleware = app(EnsureCustomerOwnership::class);
        return $ownershipMiddleware->handle($request, $next, 'customerId');
    }
}
