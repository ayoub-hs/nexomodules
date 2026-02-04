<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Container Types Permissions
        $this->createPermission(
            'nexopos.create.container-types',
            __('Create Container Types'),
            __('Let the user create container types')
        );

        $this->createPermission(
            'nexopos.read.container-types',
            __('Read Container Types'),
            __('Let the user read container types')
        );

        $this->createPermission(
            'nexopos.update.container-types',
            __('Update Container Types'),
            __('Let the user update container types')
        );

        $this->createPermission(
            'nexopos.delete.container-types',
            __('Delete Container Types'),
            __('Let the user delete container types')
        );

        // Container Inventory Permissions
        $this->createPermission(
            'nexopos.create.containers',
            __('Create Containers'),
            __('Let the user create container inventory entries')
        );

        $this->createPermission(
            'nexopos.read.containers',
            __('Read Containers'),
            __('Let the user read container inventory')
        );

        $this->createPermission(
            'nexopos.update.containers',
            __('Update Containers'),
            __('Let the user update container inventory')
        );

        $this->createPermission(
            'nexopos.delete.containers',
            __('Delete Containers'),
            __('Let the user delete container inventory entries')
        );

        // Container Management Permissions
        $this->createPermission(
            'nexopos.manage.container-inventory',
            __('Manage Container Inventory'),
            __('Let the user manage container inventory operations')
        );

        $this->createPermission(
            'nexopos.adjust.container-stock',
            __('Adjust Container Stock'),
            __('Let the user adjust container stock levels')
        );

        $this->createPermission(
            'nexopos.receive.containers',
            __('Receive Containers'),
            __('Let the user receive returned containers from customers')
        );

        // Customer Container Permissions
        $this->createPermission(
            'nexopos.view.container-customers',
            __('View Container Customers'),
            __('Let the user view customer container balances')
        );

        // Container Charging Permissions
        $this->createPermission(
            'nexopos.charge.containers',
            __('Charge Containers'),
            __('Let the user charge customers for container deposits')
        );

        // Container Report Permissions
        $this->createPermission(
            'nexopos.view.container-reports',
            __('View Container Reports'),
            __('Let the user view container reports')
        );

        $this->createPermission(
            'nexopos.export.container-reports',
            __('Export Container Reports'),
            __('Let the user export container reports')
        );

        // Assign all container permissions to Admin and Store Admin roles
        try {
            $namespaces = [
                'nexopos.create.container-types',
                'nexopos.read.container-types',
                'nexopos.update.container-types',
                'nexopos.delete.container-types',
                'nexopos.create.containers',
                'nexopos.read.containers',
                'nexopos.update.containers',
                'nexopos.delete.containers',
                'nexopos.manage.container-inventory',
                'nexopos.adjust.container-stock',
                'nexopos.receive.containers',
                'nexopos.view.container-customers',
                'nexopos.charge.containers',
                'nexopos.view.container-reports',
                'nexopos.export.container-reports',
            ];

            $admin = Role::namespace(Role::ADMIN);
            if ($admin) {
                $admin->addPermissions($namespaces);
            }

            $storeAdmin = Role::namespace(Role::STOREADMIN);
            if ($storeAdmin) {
                $storeAdmin->addPermissions($namespaces);
            }
        } catch (\Throwable $e) {
            // roles may not exist yet during fresh install
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'nexopos.create.container-types',
            'nexopos.read.container-types',
            'nexopos.update.container-types',
            'nexopos.delete.container-types',
            'nexopos.create.containers',
            'nexopos.read.containers',
            'nexopos.update.containers',
            'nexopos.delete.containers',
            'nexopos.manage.container-inventory',
            'nexopos.adjust.container-stock',
            'nexopos.receive.containers',
            'nexopos.view.container-customers',
            'nexopos.charge.containers',
            'nexopos.view.container-reports',
            'nexopos.export.container-reports',
        ];

        foreach ($permissions as $namespace) {
            Permission::where('namespace', $namespace)->delete();
        }
    }

    /**
     * Helper method to create a permission.
     */
    private function createPermission(string $namespace, string $name, string $description): void
    {
        $permission = Permission::firstOrNew(['namespace' => $namespace]);
        $permission->name = $name;
        $permission->namespace = $namespace;
        $permission->description = $description;
        $permission->save();
    }
};