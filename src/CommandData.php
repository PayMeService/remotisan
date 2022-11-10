<?php
/**
 * Created by PhpStorm.
 * User: omer
 * Date: 05/11/2022
 * Time: 13:22
 */

namespace PayMe\Remotisan;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Symfony\Component\Console\Input\InputDefinition;

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
            "definition"  => $this->getDefinition(),
            "help"        => $this->getHelp(),
            "description" => $this->getDescription()
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
