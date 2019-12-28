[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](license.md)
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]

# Laravel Indexer

Laravel Indexer monitors `SELECT` queries running on a page and allows to add database indexes to `SELECT` queries on the fly. It then presents results of `EXPLAIN` or MySQL's execution plan right on the page. The results presents by Indexer will help you see which indexes work best for different queries running on a page. 

Indexes *added by Indexer* are automatically removed after results are collected while keeping your existing indexes intact.

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

For Laravel < 5.5:

Add Service Provider to `config/app.php` in `providers` section
```php
Sarfraznawaz2005\Indexer\ServiceProvider::class,
```

---

Publish package's config file by running below command:

```bash
php artisan vendor:publish --provider="Sarfraznawaz2005\Indexer\ServiceProvider"
```
It should publish `config/indexer.php` config file.

---

## Screenshot ##

When enabled, you will see yellow/green box on bottom right. Click it to toggle results. Box will turn green if it finds there is `key` present in any of queries' execution plan.

![Main Window](https://github.com/sarfraznawaz2005/indexer/blob/master/screenshot.jpg?raw=true)

## Config ##

`enabled` : Enable or disable Indexer. By default it is disabled.

`check_ajax_requests` : Specify whether to check queries in ajax requests.

`ignore_tables` : When you don't use "watched_tables" option, Indexer watches all tables. Using this option, you can ignore specified tables to be watched.

`ignore_paths` : These paths/patterns will NOT be handled by Indexer.

`output_to` : Outputs results to given classes. By default `Web` class is included.

`watched_tables` : DB tables to be watched by Indexer. Here is example:

````php
'watched_tables' => [
    'users' => [
        // list of already existing indexes to try
        'try_table_indexes' => ['email'],
        // new indexes indexes to try
        'try_indexes' => ['name'],
        // new composite indexes to try
        'try_composite_indexes' => [
            ['name', 'email'],
        ],
    ],
],
````

 - Here queries involving `users` DB table will be watched by Indexer.
     - `try_table_indexes` contains index names that you have already applied to your DB table. Indexer will simply try out your existing indexes to show `EXPLAIN` results. In this case, `email` index already exists in `users` table.
     - `try_indexes` can be used to add new indexes on the fly to DB table. In this case, `name` index will be added on the fly by Indexer and results will be shown of how that index performed.
     - Like `try_indexes` the `try_composite_indexes` can also be used to add composite indexes on the fly to DB table. In this case, composite index consisting of `name` and `email` will be added on the fly by Indexer and results will be shown of how that index performed.


## Modes ##

Indexer can be used in following ways:

**All Indexes Added By Indexer**

Don't put any indexes manually on your tables instead let Indexer add indexes on the fly via `try_indexes` and/or `try_composite_indexes` options. Indexes added via these two options are automatically removed.

In this mode, you can actually see which indexes work best without actually applying on your tables. You can skip using `try_table_indexes` option in this case.

**Already Present Indexes + Indexes Added By Indexer**

You might have some indexes already present on your tables but you want to try out more indexes on the fly without actually adding those to the table. To specify table's existing indexes, use `try_table_indexes` option as mentioned earlier. And to try out new indexes on the fly, use `try_indexes` and/or `try_composite_indexes` options. Table's existing indexes (specified in `try_table_indexes`) will remain intact but indexes added via `try_indexes` and `try_composite_indexes` will be automatically removed.

**Already Present Indexes**

When you don't want Indexer to add any indexes on the fly and you have already specified indexes on your tables and you just want to see `EXPLAIN` results for specific tables for your indexes, in this case simply use `try_table_indexes` option only. Example:

````php
'watched_tables' => [
    'users' => [
        'try_table_indexes' => ['email'],
    ],
    'posts' => [
        'try_table_indexes' => ['title'],
    ]
],
````

In this case, both `email` and `title` indexes are supposed to be already added to table manually.

**No Indexes, Just Show EXPLAIN results for all SELECT queries**

While previous three modes allow you to work with *specific tables and indexes*, you can use this mode to just show EXPLAIN results for all SELECT queries running on a page without adding any indexes on the fly. To use this mode, simply don't specify any tables in `watched_tables` option. If you don't want to include some tables in this mode, use `ignore_tables` option.

## Misc ##

 - Color of Indexer box on bottom right or query results changes to green if it finds query's `EXPLAIN` result has `key` present eg query actually used a key. This can be changed by creating your own function in your codebase called `indexerOptimizedKeyCustom(array $queries)` instead of default one `indexerOptimizedKey` which is present in file `src/Helpers.php`. Similarly, for ajax requests, you should define your own function called `indexerOptimizedKeyCustom(explain_result)`. Here is example of each:
 
 ````php
// php
function indexerOptimizedKey(array $query): string
{
    return trim($query['explain_result']['key']);
}
 ````

````javascript
// javascript
function indexerOptimizedKey(explain_result) {
    return explain_result['key'] && explain_result['key'].trim();
}
````

## Limitations ##

* Indexer tries to find out tables names after `FROM` keyword in queries, therefore it cannot work with complex queries or ones that don't have table name after `FROM` keyword.

## Security

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
