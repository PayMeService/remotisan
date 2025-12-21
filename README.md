# Remote Execution of Artisan commands

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/run-tests?label=tests)](https://github.com/paymeservice/remotisan/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/paymeservice/remotisan/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)

The package allows you to execute artisan commands remotely, using HTTP, and receiving propagating output on the page.

**Your command execution won't run into server's MAX_EXECUTION_TIME**, allowing you to preserve original server configuration.

In general, the package could very well assist transitioning your project to CI/CD with auto-scaling, when support/devops/developers have no direct access to the server terminal.

## Installation

Use composer to install *Remotisan* to your Laravel project. **PHP 8.0+ is required**.

```bash
composer require paymeservice/remotisan
```

You can ( and probably should;) ) publish the config file with:

```bash
php artisan vendor:publish --tag="remotisan-config"
```

Optionally, you can publish the views using. The views will be published into _**/resources/views/vendor/remotisan/**_ directory for your further adjustments.

```bash
php artisan vendor:publish --tag="remotisan-views"
```

## Assets Building

After making any changes to React components or frontend assets, rebuild the assets for production:

```bash
npm run build
```

This will generate optimized production assets in the `dist/` directory. The build process is required for any modifications to:
- React components in `resources/react/components/`
- JavaScript files
- CSS/styling changes

## Configuration

Remotisan supports **two approaches** for command permissions:

### 1. Attribute-Based Permissions (Recommended)

For your **custom commands**, use PHP 8.0+ attributes directly in your command classes:

```php
use PayMe\Remotisan\Attributes\RemotisanRoles;

#[RemotisanRoles(['admin', 'user'])]
class MyCustomCommand extends Command
{
    protected $signature = 'my:command';
    
    public function handle()
    {
        // Your command logic
    }
}
```

**Available permission patterns:**
```php
#[RemotisanRoles(['*'])]              // All roles can execute
#[RemotisanRoles(['admin'])]          // Admin role only
#[RemotisanRoles(['8'])]              // Permission constant as string
#[RemotisanRoles(['admin', 'user'])]  // Multiple specific roles
```

### 2. Config-Based Permissions

For **Laravel base commands** (migrate, cache:clear, etc.), use the traditional config approach:

```php
// config/remotisan.php
[
    "commands" =>   [
        "allowed" => [ // command level ACL.
            "COMMAND_NAME"            => ["roles" => [UserRoles::TECH_SUPPORT]],
            "COMMAND_FOR_DEVOPS_ONLY" => ["roles" => [UserRoles::DEV_OPS]],
            "COMMAND_SHARED"          => ["roles" => [UserRoles::TECH_SUPPORT, UserRoles::DEV_OPS]]
        ]
    ]
]
```

### Security-First Approach

- **Commands with attributes**: Only specified roles can execute
- **Commands with config**: Only configured roles can execute
- **Commands without either**: **DENIED by default** (secure by default)
- **Both approaches**: Can be used simultaneously - results are merged

### Commands Cache (Performance Optimization)

For improved performance in production, Remotisan supports loading commands from a cache file. This avoids expensive reflection operations and ensures all commands from your project and packages are available.

**Cache File Location**: `bootstrap/cache/commands.php`

**Cache File Format**:
```php
<?php
return [
    [
        'class' => 'App\\Console\\Commands\\MyCustomCommand',
        'roles' => ['admin', 'user']
    ],
    [
        'class' => 'Illuminate\\Database\\Console\\Migrations\\MigrateCommand',
        'roles' => ['*']
    ],
    // ... additional commands
];
```

**Each cache entry must include**:
- `class` (required): Fully qualified class name of the command
- `roles` (optional): Array of roles allowed to execute this command (defaults to `[]` if not provided)

**Cache Behavior**:
- If cache file exists and is valid: Commands loaded from cache with pre-resolved roles (faster)
- If cache file is missing or invalid: Automatically falls back to `Artisan::all()` (safe)
- Invalid entries in cache are skipped silently without breaking the application

**Generating the Cache**:
Use the included `remotisan:cache` command to generate the commands cache:

```bash
php artisan remotisan:cache
```

This command will:
- Scan all registered Artisan commands in your application
- Extract commands with `RemotisanRoles` attributes or config-based permissions
- Generate the cache file at `bootstrap/cache/commands.php`
- Only cache commands that have explicit role permissions (security-first approach)
- Include commands from app, packages, and vendor

**When to Regenerate**:
- After `composer update` (when packages change)
- After adding/removing custom commands
- As part of your deployment process (recommended)
- After modifying command permissions via attributes or config

**Production Deployment**:
```bash
php artisan remotisan:cache   # Generate commands cache
php artisan config:cache      # Cache configuration
php artisan route:cache       # Cache routes
php artisan view:cache        # Cache views
```

### Custom Routes Prefix

Customize the default routes prefix by adjusting **base_url_prefix** setting. Don't forget to clear cached routes afterwards.

### Setting ENV specific commands
You are able to configure environment specific commands by simply static json string in your .env file with name `REMOTISAN_ALLOWED_COMMANDS`.
```dotenv
REMOTISAN_ALLOWED_COMMANDS='{"artisanCommandName":{"roles":[]}, "artisanSecondCommand":{"roles":[]}}'
```

## Authentication

Inside your `AppServiceProvider::boot()` add calls to `Remotisan::authWith($role, $callable)`.

Callable receives `\Illuminate\Http\Request` instance and should return true if the request matches the given role.

The roles **MUST** be matching to the roles you've defined in _Remotisan_ config.
```php
\Remotisan::authWith(UserRoles::TECH_SUPPORT, function(\Illuminate\Http\Request $request) {
    $user = $request->user('web');
    return $user && $user->isAllowed(UserPermissions::TECH_SUPPORT);
});

\Remotisan::authWith(UserRoles::DEV_OPS, function(\Illuminate\Http\Request $request) {
    $user = $request->user('web');
    return $user && $user->isAllowed(UserPermissions::DEV_OPS);
});
```

**Usage Examples:**
- If `UserPermissions::CommandsExecution = 8`, use `#[RemotisanRoles(['8'])]` in your commands
- For string roles, use `#[RemotisanRoles(['vault'])]` directly

## User Identifier
User identifier is used for auditing job executions, implementer can use any parameter they confident with.
As well, have to implement you custom UserIdentifierGetter in AppServiceProvider

```php
        Remotisan::setUserIdentifierGetter(function (HttpRequest $request) {
            /** @var User|null $user */
            $user = $request->user("web");
            return $user->getDisplayName();
        });
```

## Audit
Implementer have to run migrate after package installation, thus package will create its own history/audit table.
The audit table logs executions and allows the user to see who executed and what, as well as killing running processes. 
Audit table is **_MUST_** for the killing mechanism to work, as well as instance identifier we will cover in next section.
The table named _**remotisan_executions**_, avoid dropping.

## Instance identifier
Since the application may run in a multi-instance environment with no direct ssh access to servers, we have to identify instance the remotisan installed at.
The way it is done is "automagically" from the code, on the access to remotisan we tag the server with GUID. 

*NOTE: If the server had remotisan deployed, it is already tagged and won't be re-tagged, to continue work on existing killing list (if such already exist).* 

The server GUID is written into local file within laravel's storage on specific instance and later on used for killing jobs.

## Multi-instance requirements
In a multi-instance environment you MUST implement Audit, User Identifier sections.
As well, you would like to use Redis (memcached, or other shared) cache for proper communication between instances and process killer task.

### Technical details
The package sends kill signals into redis (using cache() facade, in multi-server env have to use shared caching) with its server identifier and the job's guid, later on, the remotisan:broker accesses redis to check for kills, and in case it spots the job uuid within the killing list, it would send SIG_* to the process.

SIGNAL's send in well defined order, first trying to gracefully end the run, then sending more and more aggressive, up to SIG_KILL if the job is not managed to quit.

Before killing, the job will write to job's log "PROCESS KILLED BY {USER_IDENTIFIER} AT {DATETIME}".

## Super User
To allow super user or a supervisor to kill ANY running job, you would like to state user_identifiers you use within remotisan config's section `super_users` which is array/list of super users.
Any super user stated in the list would be able to kill ANY running job.
ONLY Running jobs are killable.

### TODO
1. Cover api's request-response to simplify life for developers wanting to implement their own view and frontend logics.

## Happy jobbing, happy killing! :)

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- PayMe Ltd. is the main contributor
- [kima](https://github.com/PayMeService)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
