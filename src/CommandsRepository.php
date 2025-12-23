<?php

namespace PayMe\Remotisan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use PayMe\Remotisan\Exceptions\CommandCacheException;
use Symfony\Component\Console\Command\Command;

class CommandsRepository
{
    /**
     * In-memory cache to avoid re-reading and re-instantiating commands
     *
     * @var Collection|null
     */
    protected ?Collection $cachedCommands = null;

    /**
     * Get all available commands
     *
     * @return Collection
     */
    public function all(): Collection
    {
        // Return cached collection if already loaded
        if ($this->cachedCommands !== null) {
            return $this->cachedCommands;
        }

        $cachePath = base_path("bootstrap/cache/commands.php");

        if (file_exists($cachePath)) {
            try {
                $commands = $this->loadFromCache($cachePath);
                $this->cachedCommands = $commands;
                return $commands;
            } catch (CommandCacheException $e) {
                // Fallback to Artisan::all() on cache error
            }
        }

        // Fallback to Artisan::all() if cache doesn't exist or failed to load
        $commands = $this->loadFromArtisan();
        $this->cachedCommands = $commands;
        return $commands;
    }

    /**
     * Load commands from cache file with validation
     *
     * Validates:
     * - Cache file returns an array
     * - Cache is not empty
     * - Each entry has a 'class' key
     * - Each class can be instantiated
     * - Each instantiated object is a Command instance
     *
     * @param string $cachePath
     * @return Collection
     * @throws CommandCacheException
     */
    protected function loadFromCache(string $cachePath): Collection
    {
        // Load cache file
        $commandsData = require $cachePath;

        // Validate cache structure
        if (!is_array($commandsData)) {
            throw new CommandCacheException(
                "Invalid commands cache format: expected array, got " . gettype($commandsData)
            );
        }

        if (empty($commandsData)) {
            throw new CommandCacheException("Commands cache is empty");
        }

        // Validate and instantiate commands
        return collect($commandsData)
            ->map(function ($data, $key) {
                // Validate entry structure
                if (!is_array($data)) {
                    return null;
                }

                if (!isset($data['class'])) {
                    return null;
                }

                try {
                    // Instantiate the command from cached class name
                    $command = app($data['class']);

                    // Validate that it's actually a Command instance
                    if (!$command instanceof Command) {
                        return null;
                    }

                    // Pass cached roles to avoid reflection overhead
                    return new CommandData($command, $data['roles'] ?? []);
                } catch (\Throwable $e) {
                    // Skip commands that fail to instantiate
                    return null;
                }
            })
            ->filter(); // Remove null entries (failed instantiations)
    }

    /**
     * Load commands from Artisan facade (fallback)
     *
     * @return Collection
     */
    protected function loadFromArtisan(): Collection
    {
        return collect(Artisan::all())
            ->map(fn(Command $command) => new CommandData($command));
    }

    /**
     * Clear in-memory cache (useful for testing)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cachedCommands = null;
    }

    /**
     * @param string $role
     *
     * @return Collection
     */
    public function allByRole($role): Collection
    {
        return $this->all()
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

}
