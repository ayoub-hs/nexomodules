<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Role;

/**
 * Create Special Customer Permissions Migration
 * 
 * This migration creates the necessary permissions for the Special Customer module
 * and assigns them to the Admin and Store Admin roles.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if permissions table exists (for fresh installs)
        if (!Schema::hasTable('nexopos_permissions')) {
            return;
        }

        try {
            $permissions = $this->getPermissions();

            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['namespace' => $perm['namespace']], $perm);
            }

            // Assign to roles
            $this->assignPermissionsToRoles();
        } catch (\Exception $e) {
            // Silently fail if there's an issue - permissions can be created later
            \Log::warning('NsSpecialCustomer: Failed to create permissions: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('nexopos_permissions')) {
            return;
        }

        try {
            $namespaces = array_column($this->getPermissions(), 'namespace');
            Permission::whereIn('namespace', $namespaces)->delete();
        } catch (\Exception $e) {
            // Silently fail
            \Log::warning('NsSpecialCustomer: Failed to delete permissions: ' . $e->getMessage());
        }
    }

    /**
     * Get the list of permissions to create.
     */
    private function getPermissions(): array
    {
        return [
            [
                'namespace' => 'special.customer.manage',
                'name' => __('Manage Special Customers'),
                'description' => __('Full access to manage special customers, cashback, and settings.'),
            ],
            [
                'namespace' => 'special.customer.view',
                'name' => __('View Special Customers'),
                'description' => __('View special customer information and balances.'),
            ],
            [
                'namespace' => 'special.customer.cashback',
                'name' => __('Manage Cashback'),
                'description' => __('Process and manage cashback rewards.'),
            ],
            [
                'namespace' => 'special.customer.topup',
                'name' => __('Process Top-ups'),
                'description' => __('Add funds to special customer accounts.'),
            ],
            [
                'namespace' => 'special.customer.pay-outstanding-tickets',
                'name' => __('Pay Outstanding Tickets'),
                'description' => __('Pay outstanding orders from special customer wallets.'),
            ],
            [
                'namespace' => 'special.customer.settings',
                'name' => __('Configure Settings'),
                'description' => __('Configure special customer module settings.'),
            ],
        ];
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        $allPermissions = array_column($this->getPermissions(), 'namespace');
        $nonSettingsPermissions = array_filter($allPermissions, fn($p) => $p !== 'special.customer.settings');

        // Assign to admin role
        try {
            $adminRole = Role::namespace(Role::ADMIN);
            if ($adminRole) {
                $adminRole->addPermissions($allPermissions);
            }
        } catch (\Exception $e) {
            // Role might not exist yet
        }

        // Assign to store admin role (without settings permission)
        try {
            $storeAdminRole = Role::namespace(Role::STOREADMIN);
            if ($storeAdminRole) {
                $storeAdminRole->addPermissions(array_values($nonSettingsPermissions));
            }
        } catch (\Exception $e) {
            // Role might not exist yet
        }
    }
};

