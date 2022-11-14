<?php
namespace PayMe\Remotisan;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use Psy\Util\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class CommandData  implements Arrayable, JsonSerializable
{
    protected string $name;
    protected InputDefinition $definition;
    protected ?string $help;
    protected ?string $description;

    public function __construct(
        string $name,
        InputDefinition $definition,
        ?string $help,
        ?string $description
    ) {
        $this->name        = $name;
        $this->definition  = $definition;
        $this->help        = $help;
        $this->description = $description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function toArray(): array
    {
        return [
            "name"        => $this->getName(),
            "definition"  => [
                "args" => $this->ArgsToArray(),
                "ops" => $this->optionsToArray()
            ],
            "help"        => $this->getHelp(),
            "description" => $this->getDescription()
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function ArgsToArray(): Collection
    {
        return collect($this->getDefinition()->getArguments())
            ->map(fn(InputArgument $arg) => [
                "description" => $arg->getDescription(),
                "default" => $arg->getDefault(),
                "is_required" => $arg->isRequired(),
                "is_array" => $arg->isArray(),
            ]);
    }

    public function optionsToArray(): Collection
    {
        return collect($this->getDefinition()->getOptions())
            ->map(fn(InputOption $arg) => [
                "description" => $arg->getDescription(),
                "accept_Value" => $arg->acceptValue(),
                "default" => $arg->getDefault(),
                "is_required" => $arg->isValueRequired(),
                "is_array" => $arg->isArray(),
            ]);
    }

    public function canExecute(string $role)
    {
        $roles = config("remotisan.commands.allowed.{$this->getName()}.roles", []);
        return $roles != "*" && !in_array($role, $roles);
    }

    public function checkExecute(string $role)
    {
        if (!$this->canExecute($role)) {
            throw new (config('remotisan.authentication_exception_class', UnauthenticatedException::class))();
        }
    }
}
