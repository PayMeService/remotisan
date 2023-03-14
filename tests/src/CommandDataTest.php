<?php

namespace PayMe\Remotisan\Tests\src;

use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;
use PayMe\Remotisan\CommandData;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use PayMe\Remotisan\RemotisanServiceProvider;
use Symfony\Component\Console\Command\Command;

class CommandDataTest extends Orchestra
{
    protected $command_data;

    protected $original_command;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Command $migrate_status_command */
        $migrate_status_command = collect(Artisan::all())->filter(function(Command $command) {
            return $command->getName() == "migrate:status";
        })->first();;
        $this->original_command = $migrate_status_command;

        $this->command_data = new CommandData(
            $migrate_status_command->getName(),
            $migrate_status_command->getDefinition(),
            $migrate_status_command->getHelp(),
            $migrate_status_command->getDescription()
        );
    }

    public function testCommandAttributesNotSkewedInternally()
    {
        $this->assertEquals($this->original_command->getName(), $this->command_data->getName());
        $this->assertEquals($this->original_command->getDefinition(), $this->command_data->getDefinition());
        $this->assertEquals($this->original_command->getHelp(), $this->command_data->getHelp());
        $this->assertEquals($this->original_command->getDescription(), $this->command_data->getDescription());
    }

    public function testToArray()
    {
        $arrayData = $this->command_data->toArray();
        $this->assertIsArray($arrayData);
        $this->assertEquals($this->original_command->getName(), $arrayData["name"]);
        $this->assertEquals($this->original_command->getHelp(), $arrayData["help"]);
        $this->assertEquals($this->original_command->getDescription(), $arrayData["description"]);

        $args = $this->original_command->getDefinition()->getArguments();
        $options = $this->original_command->getDefinition()->getOptions();

        $this->assertIsArray($arrayData["definition"]);
        $this->assertIsArray($arrayData["definition"]["args"]->all());

        foreach ($arrayData["definition"]["args"]->all() as $argument) {
            $arg_desc = $argument["description"];
            $original_arg = collect($args)->filter(function($a) use ($arg_desc) {
                return $a->getDescription() == $arg_desc;
            });
            $this->assertEquals($original_arg->getDescription(), $argument["description"]);
            $this->assertEquals($original_arg->getDefault(), $argument["default"]);
            $this->assertEquals($original_arg->isRequired(), $argument["is_required"]);
            $this->assertEquals($original_arg->isArray(), $argument["is_array"]);
        }

        $this->assertIsArray($arrayData["definition"]["ops"]->all());

        foreach ($arrayData["definition"]["ops"] as $opt) {
            $opt_desc = $opt["description"];
            $original_opt = collect($options)->filter(function($a) use ($opt_desc) {
                return $a->getDescription() == $opt_desc;
            })->first();
            $this->assertEquals($original_opt->getDescription(), $opt["description"]);
            $this->assertEquals($original_opt->getDefault(), $opt["default"]);
            $this->assertEquals($original_opt->acceptValue(), $opt["accept_Value"]);
            $this->assertEquals($original_opt->isValueRequired(), $opt["is_required"]);
            $this->assertEquals($original_opt->isArray(), $opt["is_array"]);
        }
    }

    public function testArgToArray()
    {
        $arguments = $this->command_data->ArgsToArray()->all();
        $args = $this->original_command->getDefinition()->getArguments();

        $this->assertIsArray($arguments);

        foreach ($arguments as $argument) {
            $arg_desc = $argument["description"];
            $original_arg = collect($args)->filter(function($a) use ($arg_desc) {
                return $a->getDescription() == $arg_desc;
            })->first();
            $this->assertEquals($original_arg->getDescription(), $argument["description"]);
            $this->assertEquals($original_arg->getDefault(), $argument["default"]);
            $this->assertEquals($original_arg->isRequired(), $argument["is_required"]);
            $this->assertEquals($original_arg->isArray(), $argument["is_array"]);
        }
    }

    public function testOptionsToArray()
    {
        $options = $this->command_data->optionsToArray()->all();
        $original_options = $this->original_command->getDefinition()->getOptions();

        $this->assertIsArray($options);

        foreach ($options as $opt) {
            $opt_desc = $opt["description"];
            $original_opt = collect($original_options)->filter(function($a) use ($opt_desc) {
                return $a->getDescription() == $opt_desc;
            })->first();
            $this->assertEquals($original_opt->getDescription(), $opt["description"]);
            $this->assertEquals($original_opt->getDefault(), $opt["default"]);
            $this->assertEquals($original_opt->acceptValue(), $opt["accept_Value"]);
            $this->assertEquals($original_opt->isValueRequired(), $opt["is_required"]);
            $this->assertEquals($original_opt->isArray(), $opt["is_array"]);
        }
    }

    public function testCanExecute()
    {
        $this->assertFalse($this->command_data->canExecute("anyRole"));

        Config::set("remotisan.commands.allowed.{$this->command_data->getName()}.roles", ["anyRole", "admin"]);
        $this->assertTrue($this->command_data->canExecute("anyRole"));
        $this->assertTrue($this->command_data->canExecute("admin"));

        Config::set("remotisan.commands.allowed.{$this->original_command->getName()}.roles", ["*"]);
        $this->assertTrue($this->command_data->canExecute("anyRole888"));
    }

    public function testCheckExecuteOnNoRoles()
    {
        Config::set("remotisan.commands.allowed.{$this->command_data->getName()}.roles", []);
        $this->expectException(UnauthenticatedException::class);
        $this->command_data->checkExecute("anyRole");
    }

    public function testCheckExecuteOnImproperRole()
    {
        Config::set("remotisan.commands.allowed.{$this->command_data->getName()}.roles", ["anyRole", "admin"]);
        $this->expectException(UnauthenticatedException::class);
        $this->command_data->checkExecute("anyRole888");
    }

    public function testCheckExecuteOnWildcard()
    {   // should not throw any exception. it is intact.
        Config::set("remotisan.commands.allowed.{$this->command_data->getName()}.roles", ["*"]);
        $this->command_data->checkExecute("anyRole888");
        $this->assertTrue($this->command_data->canExecute("anyRole888")); // this ultimately responds whether allowed or not.
    }
}
