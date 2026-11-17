<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Console\Command;
use PayMe\Remotisan\Attributes\RemotisanRoles;
use PayMe\Remotisan\CommandData;
use PayMe\Remotisan\CommandsRepository;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\Remotisan;

/**
 * A user holds every role whose authWith() callable matches, and may run a command tagged with any
 * of them. This is what lets a host gate one command behind a narrow role (e.g. super admin)
 * without that user losing the commands of their broader role.
 */
class MultiRoleAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        (new \ReflectionClass(Remotisan::class))->setStaticPropertyValue("authWith", []);

        parent::tearDown();
    }

    public function testUserHoldsEveryMatchingRoleInRegistrationOrder()
    {
        $remotisan = app(Remotisan::class);
        $remotisan->authWith("operator", fn() => true);
        $remotisan->authWith("vault", fn() => false);
        $remotisan->authWith("superAdmin", fn() => true);
        $remotisan->authWith("operator", fn() => true);

        $this->assertSame(["operator", "superAdmin"], $remotisan->getUserRoles());
        // Backwards compatible: the first match
        $this->assertSame("operator", $remotisan->getUserGroup());
    }

    public function testNoMatchingRoleIsUnauthenticated()
    {
        $remotisan = app(Remotisan::class);
        $remotisan->authWith("operator", fn() => false);

        $this->assertSame([], $remotisan->getUserRoles());
        $this->assertNull($remotisan->getUserGroup());

        $this->expectException(UnauthenticatedException::class);
        $remotisan->requireAuthenticated();
    }

    public function testAttributeCommandIsAllowedWhenAnyRoleMatches()
    {
        $command = new CommandData(new SuperAdminOnlyCommand());

        $this->assertTrue($command->canExecute(["operator", "superAdmin"]));
        $this->assertFalse($command->canExecute(["operator"]));
        $this->assertFalse($command->canExecute([]));
        $this->assertTrue($command->canExecute("superAdmin"));
    }

    public function testIntegerPermissionRolesMatch()
    {
        // Hosts commonly register bit-flag permission constants as roles
        $command = new CommandData(new PermissionFlagCommand());

        $this->assertTrue($command->canExecute([4, 1 << 30]));
        $this->assertFalse($command->canExecute([4]));
    }

    public function testCheckExecuteRejectsWhenNoRoleMatches()
    {
        $command = new CommandData(new SuperAdminOnlyCommand());

        $this->expectException(UnauthenticatedException::class);
        $command->checkExecute(["operator"]);
    }

    public function testAllByRoleIsTheUnionOfTheRolesCommands()
    {
        config()->set("remotisan.commands.allowed", [
            "migrate"         => ["roles" => ["operator"]],
            "migrate:install" => ["roles" => ["superAdmin"]],
            "migrate:status"  => ["roles" => ["vault"]],
        ]);

        $commands = (new CommandsRepository())->allByRole(["operator", "superAdmin"])->keys()->all();

        $this->assertContains("migrate", $commands);
        $this->assertContains("migrate:install", $commands);
        $this->assertNotContains("migrate:status", $commands);
    }
}

#[RemotisanRoles(["superAdmin"])]
class SuperAdminOnlyCommand extends Command
{
    protected $signature = "test:super-admin-only";
}

#[RemotisanRoles([1 << 30])]
class PermissionFlagCommand extends Command
{
    protected $signature = "test:permission-flag";
}
