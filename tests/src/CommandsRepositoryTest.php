<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Support\Str;
use PayMe\Remotisan\CommandData;
use PayMe\Remotisan\CommandsRepository;

class CommandsRepositoryTest extends TestCase
{

    protected CommandsRepository $commandsRepository;
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandsRepository = new CommandsRepository();
    }

    public function testLoadsArtisanCommands()
    {
        $this->assertGreaterThan(10, $this->commandsRepository->all()->count());

        $migrationsCommand = $this->commandsRepository
            ->all()
            ->filter(fn(CommandData $command) => Str::startsWith($command->getName(), "migrat"))
            ->keys()
            ->sort();

        $expected = ["migrate","migrate:fresh","migrate:install","migrate:refresh","migrate:reset","migrate:rollback","migrate:status"];

        $this->assertTrue($migrationsCommand->diff(
            $expected
        )->isEmpty());
    }

    public function testAllByRole()
    {
        // With the new attribute-based system, commands are denied by default
        // unless they have RemotisanRoles attributes that allow them
        
        $userCommands = $this->commandsRepository
            ->allByRole("user")
            ->count();

        // Should get no commands since no RemotisanRoles attributes are present (security-first)
        $this->assertEquals(0, $userCommands);

        $adminCommands = $this->commandsRepository
            ->allByRole("admin")
            ->count();

        // Admin should also get no commands without attributes
        $this->assertEquals(0, $adminCommands);
        
        // Both should have zero commands since no attributes allow access
        $this->assertEquals($userCommands, $adminCommands);
    }

    public function testAllByRoleWithConfigFallback()
    {
        // Test that config-based filtering still works as fallback
        config()->set("remotisan.commands.allowed", [
            "migrate:status" => ["roles" => ["*"]],
            "migrate" => ["roles" => ["user", "admin"]],
            "migrate:install" => ["roles" => ["admin"]],
        ]);

        $userCommands = $this->commandsRepository
            ->allByRole("user")
            ->keys()->sort()->toArray();

        // Should include migrate and migrate:status
        $this->assertContains("migrate", $userCommands);
        $this->assertContains("migrate:status", $userCommands);

        $adminCommands = $this->commandsRepository
            ->allByRole("admin")
            ->keys()->sort()->toArray();

        // Admin should have all three
        $this->assertContains("migrate", $adminCommands);
        $this->assertContains("migrate:install", $adminCommands);
        $this->assertContains("migrate:status", $adminCommands);

        $viewerCommands = $this->commandsRepository
            ->allByRole("viewer")
            ->keys()->sort()->toArray();

        // Viewer should only have migrate:status
        $this->assertContains("migrate:status", $viewerCommands);
    }

    public function testFindMethod()
    {
        $cmd = $this->commandsRepository->find("migrate:status");
        $this->assertEquals("migrate:status", $cmd->getName());
    }

    public function testLoadFromValidCache()
    {
        // Create a mock cache file
        $cachePath = base_path("bootstrap/cache/commands.php");
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheContent = <<<'PHP'
<?php
return [
    [
        'class' => 'Illuminate\Database\Console\Migrations\MigrateCommand',
        'roles' => ['admin', 'user']
    ],
    [
        'class' => 'Illuminate\Database\Console\Migrations\StatusCommand',
        'roles' => ['*']
    ],
];
PHP;

        file_put_contents($cachePath, $cacheContent);

        // Create fresh repository instance
        $repo = new CommandsRepository();
        $commands = $repo->all();

        // Should load from cache
        $this->assertGreaterThanOrEqual(2, $commands->count());

        // Verify commands are loaded
        $migrateCommand = $commands->first(fn($cmd) => $cmd->getName() === 'migrate');
        $this->assertNotNull($migrateCommand);

        // Clean up
        unlink($cachePath);
    }

    public function testInvalidCacheFormatFallsBackToArtisan()
    {
        // Create invalid cache file (not an array)
        $cachePath = base_path("bootstrap/cache/commands.php");
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cachePath, '<?php return "invalid";');

        // Create fresh repository instance
        $repo = new CommandsRepository();
        $commands = $repo->all();

        // Should fallback to Artisan::all()
        $this->assertGreaterThan(10, $commands->count());

        // Clean up
        unlink($cachePath);
    }

    public function testEmptyCacheFallsBackToArtisan()
    {
        // Create empty cache file
        $cachePath = base_path("bootstrap/cache/commands.php");
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cachePath, '<?php return [];');

        // Create fresh repository instance
        $repo = new CommandsRepository();
        $commands = $repo->all();

        // Should fallback to Artisan::all()
        $this->assertGreaterThan(10, $commands->count());

        // Clean up
        unlink($cachePath);
    }

    public function testCacheWithInvalidEntriesSkipsThem()
    {
        // Create cache with some invalid entries
        $cachePath = base_path("bootstrap/cache/commands.php");
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheContent = <<<'PHP'
<?php
return [
    [
        'class' => 'Illuminate\Database\Console\Migrations\MigrateCommand',
        'roles' => ['admin']
    ],
    'invalid_entry',  // Invalid: not an array
    [
        // Invalid: missing 'class' key
        'roles' => ['user']
    ],
    [
        'class' => 'NonExistentCommandClass',  // Invalid: class doesn't exist
        'roles' => ['admin']
    ],
    [
        'class' => 'Illuminate\Database\Console\Migrations\StatusCommand',
        'roles' => ['*']
    ],
];
PHP;

        file_put_contents($cachePath, $cacheContent);

        // Create fresh repository instance
        $repo = new CommandsRepository();
        $commands = $repo->all();

        // Should have 2 valid commands (invalid entries skipped)
        $this->assertGreaterThanOrEqual(2, $commands->count());

        // Valid commands should be present
        $migrateCommand = $commands->first(fn($cmd) => $cmd->getName() === 'migrate');
        $this->assertNotNull($migrateCommand);

        // Clean up
        unlink($cachePath);
    }

    public function testInMemoryCaching()
    {
        // First call loads commands
        $commands1 = $this->commandsRepository->all();
        $count1 = $commands1->count();

        // Second call should return same cached instance
        $commands2 = $this->commandsRepository->all();
        $count2 = $commands2->count();

        // Counts should be identical
        $this->assertEquals($count1, $count2);

        // Should be the same object reference (in-memory cache)
        $this->assertSame($commands1, $commands2);
    }

    public function testClearCacheMethod()
    {
        // Load commands
        $commands1 = $this->commandsRepository->all();
        $this->assertNotNull($commands1);

        // Clear cache
        $this->commandsRepository->clearCache();

        // Load again - should re-fetch
        $commands2 = $this->commandsRepository->all();
        $this->assertNotNull($commands2);

        // Should not be the same object reference after clear
        $this->assertNotSame($commands1, $commands2);

        // But should have same commands
        $this->assertEquals($commands1->count(), $commands2->count());
    }

    public function testCachedRolesAreUsedInCanExecute()
    {
        // Create cache with predefined roles
        $cachePath = base_path("bootstrap/cache/commands.php");
        $cacheDir = dirname($cachePath);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheContent = <<<'PHP'
<?php
return [
    [
        'class' => 'Illuminate\Database\Console\Migrations\MigrateCommand',
        'roles' => ['admin', 'devops']
    ],
];
PHP;

        file_put_contents($cachePath, $cacheContent);

        // Create fresh repository
        $repo = new CommandsRepository();
        $commands = $repo->all();

        // Get the migrate command
        $migrateCommand = $commands->first(fn($cmd) => $cmd->getName() === 'migrate');
        $this->assertNotNull($migrateCommand);

        // Verify cached roles are used (no reflection needed)
        $this->assertTrue($migrateCommand->canExecute('admin'));
        $this->assertTrue($migrateCommand->canExecute('devops'));
        $this->assertFalse($migrateCommand->canExecute('user'));

        // Clean up
        unlink($cachePath);
    }
}
