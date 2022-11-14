# Remote Execution of Artisan commands

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/run-tests?label=tests)](https://github.com/paymeservice/remotisan/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/paymeservice/remotisan/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/paymeservice/remotisan/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/paymeservice/remotisan.svg?style=flat-square)](https://packagist.org/packages/paymeservice/remotisan)

The package allows you to execute artisan commands remotely, using HTTP, and receiving propagating output on the page.

The basic view is included in the package, as well as the basic config (make sure you publish config).

In general, the package could very well assist transitioning your project to CI/CD with auto-scaling, whenever you don't really know which boxes you have to connect to in order to perform specific command you want. 

## Installation

You can install the package via composer:

```bash
composer require paymeservice/remotisan
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="remotisan-config"
```

Optionally, you can publish the views using

@todo - add publish views.
```bash
php artisan vendor:publish --tag="remotisan-views"
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
