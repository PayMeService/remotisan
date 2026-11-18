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

class CommandData implements Arrayable, JsonSerializable
{
    protected Command $command;
    protected ?array $cachedRoles = null;

    public function __construct(Command $command, ?array $cachedRoles = null)
    {
        $this->command = $command;
        $this->cachedRoles = $cachedRoles;
    }

    /**v
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
                "default"     => $arg->getDefault(),
                "is_required" => $arg->isRequired(),
                "is_array"    => $arg->isArray(),
            ]);
    }

    /**
     * @return Collection
     */
    public function optionsToArray(): Collection
    {
        return collect($this->getDefinition()->getOptions())
            ->map(fn(InputOption $arg) => [
                "description"  => $arg->getDescription(),
                "accept_Value" => $arg->acceptValue(),
                "default"      => $arg->getDefault(),
                "is_required"  => $arg->isValueRequired(),
                "is_array"     => $arg->isArray(),
            ]);
    }

    /**
     * Check if a user holding any of the given roles can execute this command.
     *
     * A user may hold several roles at once (one per matching {@see \PayMe\Remotisan\Remotisan::authWith()}
     * registration), so a command tagged with a narrow role - e.g. a super admin one - is reachable by
     * those users without them losing the commands of their broader roles.
     *
     * @param string|int|array<int, string|int> $roles Role(s) of the user (permission constants or role names)
     * @return bool
     */
    public function canExecute(string|int|array $roles): bool
    {
        $userRoles = Arr::wrap($roles);

        if (empty($userRoles)) {
            return false;
        }

        try {
            // Use cached roles if available (from cache file)
            if ($this->cachedRoles !== null) {
                return $this->matchesAny($this->cachedRoles, $userRoles);
            }

            // Otherwise use reflection (fallback for uncached commands)
            $reflection = new ReflectionClass($this->command);
            $attributes = $reflection->getAttributes(RemotisanRoles::class);

            if (!empty($attributes)) {
                $remotisanRoles = $attributes[0]->newInstance();

                return $remotisanRoles->hasAnyRole($userRoles);
            }

            // If command has no attributes, check config as fallback.
            // If command exists but has no attributes and no config, deny access by default.
            return $this->matchesAny($this->configRoles(), $userRoles);
        } catch (\Exception $e) {
            // If reflection fails, fall back to config. No config denies access (security-first).
            return $this->matchesAny($this->configRoles(), $userRoles);
        }
    }

    /**
     * @param string|int|array<int, string|int> $roles
     *
     * @return void
     * @throws UnauthenticatedException
     */
    public function checkExecute(string|int|array $roles): void
    {
        if (!$this->canExecute($roles)) {
            throw new UnauthenticatedException();
        }
    }

    /**
     * @return array<int, string|int>
     */
    private function configRoles(): array
    {
        return Arr::wrap(config("remotisan.commands.allowed.{$this->getName()}.roles", []));
    }

    /**
     * @param array<int, string|int> $commandRoles
     * @param array<int, string|int> $userRoles
     *
     * @return bool
     */
    private function matchesAny(array $commandRoles, array $userRoles): bool
    {
        if (in_array("*", $commandRoles)) {
            return true;
        }

        foreach ($userRoles as $role) {
            if (in_array($role, $commandRoles)) {
                return true;
            }
        }

        return false;
    }
}
