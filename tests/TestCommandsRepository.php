<?php

namespace PayMe\Remotisan\Tests;

use Illuminate\Support\Str;
use PayMe\Remotisan\CommandData;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\ProcessExecutor;

class TestCommandsRepository extends TestCase
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
            ->sortKeys();

        $expected = ["migrate","migrate:fresh","migrate:install","migrate:reset","migrate:rollback","migrate:status"];

        $this->assertEmpty($migrationsCommand->keys()->diff(
            $expected
        ));
    }

    public function testAllByRole()
    {
        config()->set("remotisan.commands.allowed", [
            "migrate:status" => ["roles" => ["*"]],
            "migrate" => ["roles" => ["user", "admin"]],
            "migrate:install" => ["roles" => ["admin"]],
        ]);

        $userCommands = $this->commandsRepository
            ->allByRole("user")
            ->keys()->sort()->toArray();

        $this->assertEquals(["migrate", "migrate:status"], $userCommands);

        $userCommands = $this->commandsRepository
            ->allByRole("admin")
            ->keys()->sort()->toArray();

        $this->assertEquals(["migrate", "migrate:install", "migrate:status"], $userCommands);

        $userCommands = $this->commandsRepository
            ->allByRole("viewer")
            ->keys()->sort()->toArray();

        $this->assertEquals(["migrate:status"], $userCommands);
    }
}
