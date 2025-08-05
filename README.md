# Remote Execution of Artisan commands

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/run-tests?label=tests)](https://github.com/paymeservice/remotisan/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/paymeservice/remotisan/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)

The package allows you to execute artisan commands remotely, using HTTP, and receiving propagating output on the page.

**Your command execution won't run into server's MAX_EXECUTION_TIME**, allowing you to preserve original server configuration.

In general, the package could very well assist transitioning your project to CI/CD with auto-scaling, when support/devops/developers have no direct access to the server terminal.

## Installation

Use composer to install *Remotisan* to your Laravel project. php7.4+ is required.

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

- Remotisan allows you to customize default routes prefix, by adjusting **base_url_prefix** setting, do not forget to clear cached routes afterwards. 

- Add any command you wish to be exposed to *Remotisan* in config, by adjusting the following part.

Note: UserRoles class is NOT provided, for demonstration purpose only!

Use your own model for Access control layer (ACL).
```php
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

Use roles to define who is allowed to execute the command.

### Setting ENV specific commands
You are able to configure environment specific commands by simply static json string in your .env file with name `REMOTISAN_ALLOWED_COMMANDS`.
```dotenv
REMOTISAN_ALLOWED_COMMANDS='{"artisanCommandName":{"roles":[]}, "artisanSecondCommand":{"roles":[]}}'
```

## Authentication
Inside your `AppServiceProvider::boot()` add calls to `\Remotisan::authWith($role, $callable)`.

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
