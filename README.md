[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](license.md)
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

# Laravel Indexer

Laravel Indexer monitors `SELECT` queries running on a page and allows to add database indexes to `SELECT` queries on the fly. It then presents results of `EXPLAIN` or MySQL's execution plan right on the page.   The resutls presents by Indexer will help you see which index works best.

This package is supposed to be run only in `local` environment so it won't run on any non-local environment.

> **CAUTION: PLEASE DO NOT USE THIS PACKAGE ON PRODUCTION!** 
Since this package adds indexes to database on the fly, it is strongly recommended NOT to use this package in your production environment. 

> **Note** Since indexes are added and then removed dynamically to generate results, pages will load slow.


## Requirements ##

 - PHP >= 7
 - Laravel 5.3+ | 6

## Installation ##

Install via composer

```
composer require sarfraznawaz2005/indexer
```


That's it.

---

## Screenshot ##

## Config ##

## How It Works ##

## Modes ##

## Tips ##

### Security

If you discover any security related issues, please email sarfraznawaz2005@gmail.com instead of using the issue tracker.

## Credits

- [Sarfraz Ahmed][link-author]
- [All Contributors][link-contributors]

## License

Please see the [license file](license.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/sarfraznawaz2005/indexer.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/sarfraznawaz2005/indexer.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/sarfraznawaz2005/indexer
[link-downloads]: https://packagist.org/packages/sarfraznawaz2005/indexer
[link-author]: https://github.com/sarfraznawaz2005
[link-contributors]: https://github.com/sarfraznawaz2005/indexer/graphs/contributors
