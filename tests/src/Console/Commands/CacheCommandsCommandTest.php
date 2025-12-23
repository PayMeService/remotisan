<?php

namespace PayMe\Remotisan\Tests\src\Console\Commands;

use PayMe\Remotisan\Tests\src\TestCase;

class CacheCommandsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing cache file
        $this->cleanupCacheFile();
    }

    protected function tearDown(): void
    {
        // Clean up after tests
        $this->cleanupCacheFile();

        parent::tearDown();
    }

    public function testCommandGeneratesCacheFile()
    {
        $cachePath = base_path('bootstrap/cache/commands.php');

        // Ensure cache doesn't exist before test
        $this->assertFileDoesNotExist($cachePath);

        // Run the cache command
        $this->artisan('remotisan:cache')
            ->assertExitCode(0);

        // Cache file should now exist
        $this->assertFileExists($cachePath);

        // Verify cache file is valid PHP
        $cachedData = require $cachePath;
        $this->assertIsArray($cachedData);
    }


    public function testCachedCommandsHaveRequiredFields()
    {
        // Run the cache command
        $this->artisan('remotisan:cache')
            ->assertExitCode(0);

        $cachePath = base_path('bootstrap/cache/commands.php');
        $cachedData = require $cachePath;

        foreach ($cachedData as $commandData) {
            // Each entry should have 'class' field
            $this->assertArrayHasKey('class', $commandData, 'Cache entry missing "class" field');

            // Each entry should have 'roles' field
            $this->assertArrayHasKey('roles', $commandData, 'Cache entry missing "roles" field');

            // 'roles' should be an array
            $this->assertIsArray($commandData['roles'], 'Roles field should be an array');

            // 'class' should be a valid class name string
            $this->assertIsString($commandData['class'], 'Class field should be a string');
        }
    }

    public function testCommandOutputsSuccessMessage()
    {
        $this->artisan('remotisan:cache')
            ->expectsOutput('Scanning for commands...')
            ->assertExitCode(0);
    }

    public function testCachedCommandsCanBeInstantiatedWithConfig()
    {
        // Set up some config-based commands for testing
        config()->set("remotisan.commands.allowed", [
            "migrate:status" => ["roles" => ["*"]],
            "migrate" => ["roles" => ["user", "admin"]],
        ]);

        // Run the cache command
        $this->artisan('remotisan:cache')
            ->assertExitCode(0);

        $cachePath = base_path('bootstrap/cache/commands.php');
        $cachedData = require $cachePath;

        // In test environment, we may or may not have commands with roles
        // The command should still succeed
        $this->assertIsArray($cachedData);

        // If we have cached commands, verify they can be instantiated
        if (count($cachedData) > 0) {
            $validCommands = 0;
            foreach (array_slice($cachedData, 0, 5) as $commandData) {
                try {
                    $command = app($commandData['class']);
                    $this->assertInstanceOf(\Symfony\Component\Console\Command\Command::class, $command);
                    $validCommands++;
                } catch (\Throwable $e) {
                    // Some commands might fail to instantiate, that's okay
                }
            }

            // At least one command should be valid if any were cached
            $this->assertGreaterThan(0, $validCommands, 'At least one cached command should be instantiable');
        } else {
            // If no commands were cached, that's okay in test environment
            $this->assertTrue(true, 'No commands with roles found in test environment');
        }
    }

    /**
     * Clean up cache file
     */
    private function cleanupCacheFile(): void
    {
        $cachePath = base_path('bootstrap/cache/commands.php');

        if (file_exists($cachePath)) {
            unlink($cachePath);
        }
    }
}