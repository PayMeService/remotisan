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
}
