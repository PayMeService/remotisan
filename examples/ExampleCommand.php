<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PayMe\Remotisan\Attributes\RemotisanRoles;

/**
 * Example command showing how to use RemotisanRoles attribute
 * 
 * Usage in your main project:
 * 1. Add the attribute to your command class
 * 2. Specify which roles can execute the command
 * 3. The remotisan package will automatically respect these permissions
 */
#[RemotisanRoles(['admin', 'super-admin'])]
class ExampleCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'example:restricted';

    /**
     * The console command description.
     */
    protected $description = 'Example command that only admins can execute';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('This command can only be executed by admin or super-admin roles');
        return 0;
    }
}

/**
 * Command accessible to all users
 */
#[RemotisanRoles(['*'])]
class PublicExampleCommand extends Command
{
    protected $signature = 'example:public';
    protected $description = 'Example command accessible to all roles';

    public function handle()
    {
        $this->info('This command can be executed by any role');
        return 0;
    }
}

/**
 * Command with multiple specific roles
 */
#[RemotisanRoles(['user', 'moderator', 'admin'])]
class MultiRoleExampleCommand extends Command
{
    protected $signature = 'example:multi-role';
    protected $description = 'Example command for specific roles';

    public function handle()
    {
        $this->info('This command can be executed by user, moderator, or admin roles');
        return 0;
    }
}

/**
 * Command without attributes - will be DENIED by default (security-first)
 * To make this accessible, you must add a RemotisanRoles attribute
 */
class RestrictedByDefaultCommand extends Command
{
    protected $signature = 'example:restricted-by-default';
    protected $description = 'Example command that will be denied without attributes';

    public function handle()
    {
        $this->info('This command will be denied for all roles without RemotisanRoles attribute');
        return 0;
    }
}