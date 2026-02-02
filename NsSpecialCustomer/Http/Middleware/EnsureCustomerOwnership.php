<?php

namespace Modules\NsSpecialCustomer\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to prevent IDOR (Insecure Direct Object Reference) attacks
 * on customer-related endpoints.
 *
 * This middleware ensures that:
 * 1. Users with 'special.customer.manage' permission can access any customer's data
 * 2. Regular users can only access their own customer data
 * 3. API requests return appropriate JSON responses
 */
class EnsureCustomerOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request The incoming request
     * @param Closure $next The next middleware handler
     * @param string $parameterName The route parameter name for customer ID (default: 'customerId')
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $parameterName = 'customerId'): Response
    {
        // Admins and managers can access any customer's data
        if (ns()->allowedTo('special.customer.manage')) {
            return $next($request);
        }

        // Get the customer ID from the route parameter
        $requestedCustomerId = $request->route($parameterName);

        if (!$requestedCustomerId) {
            return $this->forbiddenResponse($request, 'Customer ID is required');
        }

        // Get the authenticated user's customer ID
        $authenticatedUser = $request->user();
        $userCustomerId = $this->getUserCustomerId($authenticatedUser);

        // If user doesn't have a customer profile, they can't access any customer data
        if (!$userCustomerId) {
            return $this->forbiddenResponse($request, 'You do not have a customer profile associated with your account');
        }

        // Check if the user is accessing their own data
        if ((int) $requestedCustomerId !== (int) $userCustomerId) {
            return $this->forbiddenResponse(
                $request,
                'You do not have permission to access this customer\'s data'
            );
        }

        return $next($request);
    }

    /**
     * Get the customer ID associated with a user.
     *
     * @param mixed $user The authenticated user
     * @return int|null The customer ID or null if not found
     */
    private function getUserCustomerId($user): ?int
    {
        if (!$user) {
            return null;
        }

        // Check if user has a direct customer_id attribute
        if (isset($user->customer_id) && $user->customer_id) {
            return (int) $user->customer_id;
        }

        // Check if there's a customer relationship
        if (method_exists($user, 'customer')) {
            $customer = $user->customer;
            if ($customer) {
                return (int) $customer->id;
            }
        }

        // Look up customer by email
        if (isset($user->email)) {
            $customer = Customer::where('email', $user->email)->first();
            if ($customer) {
                return (int) $customer->id;
            }
        }

        return null;
    }

    /**
     * Return a forbidden response based on request type.
     *
     * @param Request $request The incoming request
     * @param string $message The error message
     * @return Response
     */
    private function forbiddenResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'message' => __($message),
            ], 403);
        }

        return redirect()
            ->route('ns.dashboard.home')
            ->with('error', __($message));
    }
}
