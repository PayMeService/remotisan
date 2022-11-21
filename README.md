# Remote Execution of Artisan commands

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/run-tests?label=tests)](https://github.com/paymeservice/remotisan/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/paymeservice/remotisan/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)

The package allows you to execute artisan commands remotely, using HTTP, and receiving propagating output on the page.

**Your command execution won't run into server's MAX_EXECUTION_TIME**, allowing you to preserve original server configuration.

In general, the package could very well assist transitioning your project to CI/CD with auto-scaling, when supporters have no direct access to the server terminal.

## Installation

Use composer to install *Remotisan* to your Laravel project. php7.4+ is required.

```bash
composer require paymeservice/remotisan
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="remotisan-config"
```

Optionally, you can publish the views using. The views will be published into _**/resources/views/vendor/remotisan/**_ directory for your further adjustments.

```bash
php artisan vendor:publish --tag="remotisan-views"
```

## Configuration

- Remotisan allows you to customize default routes prefix, by adjusting **base_url_prefix** setting, do not forget to clear cached routes afterwards. 

- Add any command you wish to be exposed to *Remotisan* in config, by adjusting the following part.

Note: UserRoles class is NOT provided, for demonstration purpose only!
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

Callable receive a `\Illuminate\Http\Request` instance and should return true if the request (probably by the user) matches the given role.

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

- [kima](https://github.com/PayMeService)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
