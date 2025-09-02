<?php
namespace PayMe\Remotisan;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonSerializable;
use PayMe\Remotisan\Attributes\RemotisanRoles;
use PayMe\Remotisan\Exceptions\UnauthenticatedException;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class CommandData  implements Arrayable, JsonSerializable
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->command->getName();
    }

    /**
     * @return InputDefinition
     */
    public function getDefinition(): InputDefinition
    {
        return $this->command->getDefinition();
    }

    /**
     * @return string|null
     */
    public function getHelp(): ?string
    {
        return $this->command->getHelp();
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->command->getDescription();
    }

    /**
     * @return string|null
     */
    public function getUsageManual(): ?string
    {
        return $this->extractUsageManual();
    }

    /**
     * @return Command
     */
    public function getCommand(): Command
    {
        return $this->command;
    }

    /**
     * Extract usage information from the command
     *
     * @return string|null
     */
    private function extractUsageManual(): ?string
    {
        try {
            // Check if the command has a usageManual property (for PMCommand and subclasses)
            if (property_exists($this->command, 'usageManual')) {
                // Use reflection to access protected property
                $reflection = new \ReflectionClass($this->command);
                if ($reflection->hasProperty('usageManual')) {
                    $property = $reflection->getProperty('usageManual');
                    $property->setAccessible(true);
                    $usageManual = $property->getValue($this->command);

                    if (!empty($usageManual)) {
                        return $usageManual;
                    }
                }
            }

            // Fallback: try to get the command synopsis for standard Laravel commands
            $synopsis = $this->command->getSynopsis();
            if (!empty($synopsis)) {
                return $synopsis;
            }

            // Final fallback: try to get usage examples if available
            $usages = $this->command->getUsages();
            if (!empty($usages)) {
                return $usages[0]; // Return the first usage example
            }

            return null;
        } catch (\Exception $e) {
            // If anything fails, return null to gracefully handle errors
            return null;
        }
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
                "ops"  => $this->optionsToArray()
            ],
            "help"        => $this->getHelp(),
            "description" => $this->getDescription(),
            "usageManual" => $this->getUsageManual()
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
     * Check if user can execute based on role
     * 
     * @param string $role Role string (can be permission constant as string or role name)
     * @return bool
     */
    public function canExecute(string $role): bool
    {
        try {
            $reflection = new ReflectionClass($this->command);
            $attributes = $reflection->getAttributes(RemotisanRoles::class);
            
            if (!empty($attributes)) {
                $remotisanRoles = $attributes[0]->newInstance();
                return $remotisanRoles->hasRole($role);
            }
            
            // If command has no attributes, check config as fallback
            $roles = Arr::wrap(config("remotisan.commands.allowed.{$this->getName()}.roles", []));
            if (!empty($roles)) {
                return in_array("*", $roles) || in_array($role, $roles);
            }
            
            // If command exists but has no attributes and no config, deny access by default
            return false;
        } catch (\Exception $e) {
            // If reflection fails, fall back to config
            $roles = Arr::wrap(config("remotisan.commands.allowed.{$this->getName()}.roles", []));
            
            // If no config is found, deny access by default (security-first)
            if (empty($roles)) {
                return false;
            }
            
            return in_array("*", $roles) || in_array($role, $roles);
        }
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
