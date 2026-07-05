# Laravel Lang Checker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/syntaxas/laravel-lang-checker.svg?style=flat-square)](https://packagist.org/packages/syntaxas/laravel-lang-checker)
[![GitHub Tests Action Status](https://github.com/syntaxas/laravel-lang-checker/actions/workflows/run-tests.yml/badge.svg)](https://github.com/syntaxas/laravel-lang-checker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/syntaxas/laravel-lang-checker/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/syntaxas/laravel-lang-checker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/syntaxas/laravel-lang-checker.svg?style=flat-square)](https://packagist.org/packages/syntaxas/laravel-lang-checker)

Laravel Lang Checker is a package for checking language files and translations in Laravel applications. It helps ensure that your translations are complete and consistent across different languages.

## Requirements

- PHP **8.2**, **8.3**, **8.4**, or **8.5**
- Laravel **11**, **12**, or **13**

## Installation

You can install the package via composer:

```bash
composer require syntaxas/laravel-lang-checker
```

## Usage

```bash
php artisan laravel-language:check
```

## What It Checks

The command scans all locale directories under your `lang/` path and runs the following checks:

| Check                                                         | Outcome            |
| ------------------------------------------------------------- | ------------------ |
| `lang/` directory does not exist                              | ❌ Fails           |
| No locale subdirectories found                                | ⚠️ Warning, passes |
| A translation file exists in some locales but not all         | ❌ Fails           |
| A translation key exists in some locales but not all          | ❌ Fails           |
| A key is an array in one locale but a scalar value in another | ❌ Fails           |
| A translation file does not return an array                   | ❌ Fails           |
| A translation value is an empty string                        | ⚠️ Warning, passes |

Checks work recursively — files in subdirectories (e.g. `/lang/lt/admin/dashboard.php`) are included.

When the command fails, it reports the total number of issues found:

```
[ERROR] Language files check failed with 3 issue(s).
```

When everything is consistent:

```
[OK] Language files are consistent across all locales.
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
