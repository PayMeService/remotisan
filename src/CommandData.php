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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return InputDefinition
     */
    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    /**
     * @return string|null
     */
    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array
     */
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

    /**
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @return Collection
     */
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

    /**
     * @return Collection
     */
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

    /**
     * @param string $role
     *
     * @return bool
     */
    public function canExecute(string $role): bool
    {
        $roles = config("remotisan.commands.allowed.{$this->getName()}.roles", []);

        return in_array("*", $roles) || in_array($role, $roles);
    }

    /**
     * @param string $role
     *
     * @return void
     * @throws UnauthenticatedException
     */
    public function checkExecute(string $role): void
    {
        if (!$this->canExecute($role)) {
            throw new UnauthenticatedException();
        }
    }
}
