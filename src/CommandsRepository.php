<?php

namespace PayMe\Remotisan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

class CommandsRepository
{
    /**
     * @return Collection
     */
    public function all(): Collection
    {
        return collect(Artisan::all())
            ->map(fn(Command $command) => new CommandData(
                $command->getName(),
                $command->getDefinition(),
                $command->getHelp(),
                $command->getDescription(),
                $this->extractUsageManual($command)
            ));
    }

    /**
     * @param string $role
     *
     * @return Collection
     */
    public function allByRole($role): Collection
    {
        return $this->all()
            ->intersectByKeys(config('remotisan.commands.allowed'))
            ->filter(fn(CommandData $command) => $command->canExecute($role));
    }

    /**
     * @param string $name
     *
     * @return CommandData|null
     */
    public function find(string $name): ?CommandData
    {
        return $this->all()->first(fn(CommandData $cd) => $cd->getName() == $name);
    }

    /**
     * Extract usage information from a command
     *
     * @param Command $command
     * @return string|null
     */
    private function extractUsageManual(Command $command): ?string
    {
        try {
            // Check if the command has a usageManual property (for PMCommand and subclasses)
            if (property_exists($command, 'usageManual')) {
                // Use reflection to access protected property
                $reflection = new \ReflectionClass($command);
                if ($reflection->hasProperty('usageManual')) {
                    $property = $reflection->getProperty('usageManual');
                    $property->setAccessible(true);
                    $usageManual = $property->getValue($command);

                    if (!empty($usageManual)) {
                        return $usageManual;
                    }
                }
            }

            // Fallback: try to get the command synopsis for standard Laravel commands
            $synopsis = $command->getSynopsis();
            if (!empty($synopsis)) {
                return $synopsis;
            }

            // Final fallback: try to get usage examples if available
            $usages = $command->getUsages();
            if (!empty($usages)) {
                return $usages[0]; // Return the first usage example
            }

            return null;
        } catch (\Exception $e) {
            // If anything fails, return null to gracefully handle errors
            return null;
        }
    }
}
