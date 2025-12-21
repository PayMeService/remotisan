<?php

namespace PayMe\Remotisan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use PayMe\Remotisan\Attributes\RemotisanRoles;
use ReflectionClass;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CacheCommandsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remotisan:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache all available commands with their roles for Remotisan';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning for commands...');

        $commandsData = $this->discoverCommands();

        if (empty($commandsData)) {
            $this->warn('No commands with RemotisanRoles attribute or config-based roles found.');
        }

        $cachePath = base_path('bootstrap/cache/commands.php');
        $cacheDir = dirname($cachePath);

        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Write cache file
        $exportedData = var_export($commandsData, true);
        $cacheContent = "<?php\n\nreturn {$exportedData};\n";
        file_put_contents($cachePath, $cacheContent);

        $this->info('Successfully cached ' . count($commandsData) . ' commands to: ' . $cachePath);

        return self::SUCCESS;
    }

    /**
     * Discover all commands with RemotisanRoles attributes or config-based permissions
     *
     * @return array
     */
    private function discoverCommands(): array
    {
        $commands = [];
        $allCommands = Artisan::all();

        foreach ($allCommands as $command) {
            $commandData = $this->extractCommandData($command);

            if ($commandData) {
                $commands[] = $commandData;
            }
        }

        return $commands;
    }

    /**
     * Extract command metadata (class, roles)
     *
     * @param SymfonyCommand $command
     * @return array|null
     */
    private function extractCommandData(SymfonyCommand $command): ?array
    {
        try {
            $class = get_class($command);

            // Skip internal/vendor commands without roles
            if ($this->shouldSkipCommand($class)) {
                return null;
            }

            $roles = $this->extractRoles($class, $command->getName());

            // If no roles found (no attribute, no config), skip this command
            // This maintains the security-first approach
            if (empty($roles) && !in_array('*', $roles)) {
                return null;
            }

            return [
                'class' => $class,
                'roles' => $roles,
            ];
        } catch (\Throwable $e) {
            $this->warn("Could not extract data for command: {$command->getName()}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract roles from RemotisanRoles attribute or config
     *
     * @param string $class
     * @param string $commandName
     * @return array
     */
    private function extractRoles(string $class, string $commandName): array
    {
        try {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(RemotisanRoles::class);

            // First priority: RemotisanRoles attribute
            if (!empty($attributes)) {
                $rolesAttribute = $attributes[0]->newInstance();
                return $rolesAttribute->getRoles();
            }

            // Second priority: Config-based roles
            $configRoles = config("remotisan.commands.allowed.{$commandName}.roles", []);
            if (!empty($configRoles)) {
                return is_array($configRoles) ? $configRoles : [$configRoles];
            }

            // Third priority: ENV-based configuration
            $envCommands = config('remotisan.commands.allowed', []);
            if (isset($envCommands[$commandName]['roles'])) {
                $envRoles = $envCommands[$commandName]['roles'];
                return is_array($envRoles) ? $envRoles : [$envRoles];
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Determine if a command should be skipped from caching
     *
     * @param string $class
     * @return bool
     */
    private function shouldSkipCommand(string $class): bool
    {
        // Skip anonymous commands
        if (strpos($class, 'class@anonymous') !== false) {
            return true;
        }

        return false;
    }
}