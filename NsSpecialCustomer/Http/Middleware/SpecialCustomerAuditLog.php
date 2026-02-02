<?php

namespace Modules\NsSpecialCustomer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SpecialCustomerAuditLog
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Log financial operations
        $this->logFinancialOperation($request, $response);

        return $response;
    }

    /**
     * Log financial operations for audit trail
     */
    private function logFinancialOperation(Request $request, $response): void
    {
        $financialRoutes = [
            'special-customer/topup',
            'special-customer/cashback',
        ];

        $requestPath = $request->path();
        
        // Check if this is a financial operation
        $isFinancialOperation = collect($financialRoutes)->contains(function ($route) use ($requestPath) {
            return str_contains($requestPath, $route);
        });

        if ($isFinancialOperation && in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            $user = Auth::user();
            $responseData = json_decode($response->getContent(), true);

            Log::channel('audit')->info('Special Customer Financial Operation', [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'route' => $requestPath,
                'request_data' => $this->sanitizeRequestData($request->all()),
                'response_status' => $response->getStatusCode(),
                'response_success' => $responseData['status'] ?? 'unknown',
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Sanitize request data for logging (remove sensitive information)
     */
    private function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'api_key', 'secret'];
        
        return collect($data)->map(function ($value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return '***REDACTED***';
            }
            
            if (is_array($value)) {
                return $this->sanitizeRequestData($value);
            }
            
            return $value;
        })->toArray();
    }
}
