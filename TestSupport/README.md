# TestSupport Module

Utilities for module test bootstrapping. The main entry point is `ModuleTestDatabaseBootstrap`, which prepares an isolated SQLite database and registers required NexoPOS services for module tests.

**Purpose**
- Create or ensure an SQLite database file for tests.
- Run core and module migrations.
- Seed required roles and options.
- Patch legacy test schema and factory defaults.
- Register known module middleware aliases.
- Refresh core-installed caches and register gate permissions.

**Usage**
Example inside a module test case:
```php
use Modules\TestSupport\Testing\ModuleTestDatabaseBootstrap;

public function setUp(): void
{
    parent::setUp();
    ModuleTestDatabaseBootstrap::prepare(
        testCase: $this,
        moduleMigrationPath: 'modules/NsContainerManagement/Migrations'
    );
}
```

**Notes**
- `moduleMigrationPath` is resolved from the NexoPOS base path.
- Known module providers are auto-registered for container management, manufacturing, and special customer migrations.
