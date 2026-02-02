<?php

use App\Classes\Schema;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Manufacturing Module Permissions Migration
 *
 * Creates all manufacturing-related permissions and assigns them
 * to appropriate roles (Admin, Store Admin).
 *
 * This migration is idempotent - it can be run multiple times safely.
 */
return new class extends Migration
{
    /**
     * Manufacturing permissions definitions
     */
    private array $permissions = [
        'nexopos.create.manufacturing-recipes' => [
            'name' => 'Create Manufacturing Recipes',
            'description' => 'Allow user to create Bill of Materials (BOM) recipes',
        ],
        'nexopos.read.manufacturing-recipes' => [
            'name' => 'Read Manufacturing Recipes',
            'description' => 'Allow user to view Bill of Materials (BOM) recipes',
        ],
        'nexopos.update.manufacturing-recipes' => [
            'name' => 'Update Manufacturing Recipes',
            'description' => 'Allow user to update Bill of Materials (BOM) recipes',
        ],
        'nexopos.delete.manufacturing-recipes' => [
            'name' => 'Delete Manufacturing Recipes',
            'description' => 'Allow user to delete Bill of Materials (BOM) recipes',
        ],
        'nexopos.create.manufacturing-orders' => [
            'name' => 'Create Manufacturing Orders',
            'description' => 'Allow user to create production/manufacturing orders',
        ],
        'nexopos.read.manufacturing-orders' => [
            'name' => 'Read Manufacturing Orders',
            'description' => 'Allow user to view production/manufacturing orders',
        ],
        'nexopos.update.manufacturing-orders' => [
            'name' => 'Update Manufacturing Orders',
            'description' => 'Allow user to update production/manufacturing orders',
        ],
        'nexopos.delete.manufacturing-orders' => [
            'name' => 'Delete Manufacturing Orders',
            'description' => 'Allow user to delete production/manufacturing orders',
        ],
        'nexopos.start.manufacturing-orders' => [
            'name' => 'Start Manufacturing Orders',
            'description' => 'Allow user to start production/manufacturing orders',
        ],
        'nexopos.complete.manufacturing-orders' => [
            'name' => 'Complete Manufacturing Orders',
            'description' => 'Allow user to complete production/manufacturing orders',
        ],
        'nexopos.cancel.manufacturing-orders' => [
            'name' => 'Cancel Manufacturing Orders',
            'description' => 'Allow user to cancel production/manufacturing orders',
        ],
        'nexopos.view.manufacturing-costs' => [
            'name' => 'View Manufacturing Costs',
            'description' => 'Allow user to view manufacturing costs and analytics reports',
        ],
        'nexopos.export.manufacturing-reports' => [
            'name' => 'Export Manufacturing Reports',
            'description' => 'Allow user to export manufacturing reports and data',
        ],
    ];

    /**
     * Run the migrations.
     *
     * Creates all manufacturing permissions and assigns them to:
     * - Admin role (all permissions)
     * - Store Admin role (all permissions)
     */
    public function up(): void
    {
        if ( ! Schema::hasTable( 'nexopos_permissions' ) ) {
            return;
        }

        $adminRole = Role::namespace( Role::ADMIN );
        $storeAdminRole = Role::namespace( Role::STOREADMIN );

        foreach ( $this->permissions as $namespace => $config ) {
            // Create or update permission
            $permission = Permission::firstOrNew( ['namespace' => $namespace] );
            $permission->name = __( $config['name'] );
            $permission->description = __( $config['description'] );
            $permission->save();

            // Assign to Admin role
            if ( $adminRole instanceof Role ) {
                $adminRole->addPermissions( $namespace, true );
            }

            // Assign to Store Admin role
            if ( $storeAdminRole instanceof Role ) {
                $storeAdminRole->addPermissions( $namespace, true );
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Removes all manufacturing permissions and their role assignments.
     */
    public function down(): void
    {
        if ( ! Schema::hasTable( 'nexopos_permissions' ) ) {
            return;
        }

        foreach ( array_keys( $this->permissions ) as $namespace ) {
            $permission = Permission::namespace( $namespace );

            if ( $permission instanceof Permission ) {
                // Remove from all roles
                $permission->removeFromRoles();
                // Delete permission
                $permission->delete();
            }
        }
    }
};
