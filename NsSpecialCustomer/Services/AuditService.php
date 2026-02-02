<?php

namespace Modules\NsSpecialCustomer\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log configuration changes
     */
    public function logConfigChange(array $oldConfig, array $newConfig, string $reason = null): void
    {
        $user = Auth::user();
        
        Log::channel('audit')->warning('Special Customer Configuration Changed', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => request()->ip(),
            'old_config' => $oldConfig,
            'new_config' => $newConfig,
            'changes' => $this->getConfigChanges($oldConfig, $newConfig),
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log cashback processing
     */
    public function logCashbackProcessing(int $customerId, float $amount, string $status, array $metadata = []): void
    {
        $user = Auth::user();
        
        Log::channel('audit')->info('Special Customer Cashback Processed', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'customer_id' => $customerId,
            'amount' => $amount,
            'status' => $status,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log top-up operations
     */
    public function logTopupOperation(int $customerId, float $amount, string $operation, array $metadata = []): void
    {
        $user = Auth::user();
        
        Log::channel('audit')->info('Special Customer Top-up Operation', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'customer_id' => $customerId,
            'amount' => $amount,
            'operation' => $operation,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log permission changes
     */
    public function logPermissionChange(int $userId, array $permissions, string $action): void
    {
        $currentUser = Auth::user();
        
        Log::channel('audit')->warning('Special Customer Permission Changed', [
            'admin_user_id' => $currentUser?->id,
            'admin_user_email' => $currentUser?->email,
            'target_user_id' => $userId,
            'permissions' => $permissions,
            'action' => $action, // 'granted' or 'revoked'
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get configuration changes for audit
     */
    private function getConfigChanges(array $oldConfig, array $newConfig): array
    {
        $changes = [];
        
        foreach ($newConfig as $key => $newValue) {
            $oldValue = $oldConfig[$key] ?? null;
            
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $user = Auth::user();
        
        Log::channel('audit')->critical('Special Customer Security Event', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'event' => $event,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log data access
     */
    public function logDataAccess(string $resource, int $resourceId, string $action): void
    {
        $user = Auth::user();
        
        Log::channel('audit')->info('Special Customer Data Access', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'action' => $action, // 'view', 'export', etc.
            'timestamp' => now()->toISOString(),
        ]);
    }
}
