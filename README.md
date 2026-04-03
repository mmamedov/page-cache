[![PHP Composer](https://github.com/mmamedov/page-cache/actions/workflows/php.yml/badge.svg?branch=master)](https://github.com/mmamedov/page-cache/actions/workflows/php.yml) [![Latest Stable Version](http://img.shields.io/packagist/v/mmamedov/page-cache.svg)](https://packagist.org/packages/mmamedov/page-cache) [![License](https://img.shields.io/packagist/l/mmamedov/page-cache.svg)](https://packagist.org/packages/mmamedov/page-cache)

Full-page PHP Caching library
----
PageCache is a lightweight PHP library for full page cache, works out of the box with zero configuration.
Use it when you need a simple yet powerful file-based PHP caching solution. Page caching for mobile devices is built-in.

Install via Composer:
```
composer require mmamedov/page-cache
```
Or add to your `composer.json` manually:
```json
{
  "require": {
      "mmamedov/page-cache": "^3.0"
  }
}
```

#### Upgrading to v3.*
Version 3.0 requires **PHP 8.1+** — a breaking change from v2, which supported PHP 5.6+.

- `psr/log` upgraded to `^3.0` and `psr/simple-cache` upgraded to `^3.0`. Ensure your project's PSR dependencies are compatible.
- No public API changes — existing code should work without modification.

#### Upgrading to v2.*
Version 2.0 is not backwards compatible with v1.x.

- Your config file must use `return [...]` and not `$config = array(...);` like in the previous version.
- Config `expiration` setting was renamed to `cache_expiration_in_seconds`.
- Use `try/catch` to ensure proper page load in case of a PageCache error.

If you find any other notable incompatibilities please let us know and we will include them here.

No Database calls
----
Once a page is cached, there are no more database calls needed. Even if your page contains many database calls and complex logic,
it will be executed once and cached for the period you specify.

This is a very efficient and simple method to cache your most visited dynamic pages.
[Tmawto.com](https://www.tmawto.com) website is built on PageCache.

Why another PHP Caching class?
----
Short answer — simplicity. If you want to include a couple of lines of code on top of your dynamic PHP pages and cache them fully,
then PageCache is for you. No worrying about cache file name setup for each URL, no worries about your dynamically generated URL
parameters and changing URLs. PageCache detects those changes and caches accordingly.

PageCache also detects `$_SESSION` changes and caches those pages correctly. This is useful if you have user
authentication enabled on your site, and page contents change per user login while the URL remains the same.

Lots of caching solutions focus on a keyword-based approach, where you need to set up a keyword for your
content (be it a full page cache, a variable, etc.). There are great packages for that approach.
One could also use a more complex solution like a cache proxy (e.g. Varnish).
PageCache is a simple full-page caching solution that does exactly what its name says.

How PageCache works
----
PageCache doesn't ask you for a keyword — it automatically generates one based on Strategies implementing `StrategyInterface`.
You can define your own naming strategy based on your application's needs.
The Strategy class is responsible for generating a unique key for the current request; the key becomes the file name for the
cache file (if FileSystem storage is used).

```php
<?php
require_once __DIR__.'/../vendor/autoload.php';

try {
    $cache = new PageCache\PageCache();
    $cache->config()
                    ->setCachePath('/your/path/')
                    ->setEnableLog(true);
    $cache->init();
} catch (\Exception $e) {
    // Log PageCache error or simply do nothing.
    // In case of PageCache error, page will load normally, without cache.
}

//rest of your PHP page code, everything below will be cached
```

Using PSR-16 compatible cache adapter
----

PageCache is built on top of [PSR-16 SimpleCache](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md) key/value storage and has a default file-based cache adapter in class `FileSystemCacheAdapter`. This implementation is fast and uses `var_export` + `include` internally, so every cache file is also automatically cached in OPcache (if you have opcode caching configured). This is the perfect choice for single-server applications, but if you have a multi-server setup you may want to share page cache content between servers. In that case you can use any PSR-16 compatible cache adapter for network-based storage like Memcached or Redis:

```php
<?php
require_once __DIR__.'/../vendor/autoload.php';

use Naroga\RedisCache\Redis;
use Predis\Client;

$config = array(
    'scheme' => 'tcp',
    'host'   => 'localhost',
    'port'   => 6379
);

$redis = new Redis(new Client($config));

$cache = new PageCache\PageCache();
$cache->setCacheAdapter($redis);
$cache->init();

//rest of your PHP page code, everything below will be cached
```

PageCache also has a "Dry Run" mode, which is off by default.
Dry Run mode enables all functionality except that users receive live content instead of cached content —
no cache headers or cached content will be sent. The cache is still written to storage as normal.
This is useful for testing, debugging, or observing how cache content populates.

For more examples see [PageCache examples](examples/) directory.

Cache is saved into the path specified in the config file or via the API, inside subdirectories based on file hash.
Two subdirectories are created per entry to avoid storing too many files in a single directory.

Caching Strategies
------------------
PageCache uses various strategies to differentiate among separate versions of the same page.

All PageCache Strategies support sessions. See the [cache page with sessions](examples/demo-session-support.php) example.

`DefaultStrategy` is the default behaviour of PageCache. It generates cache keys using:
```php
md5($_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING'] . $session_str)
```
`$session_str` is a serialized `$_SESSION`, with some keys excluded based on whether session support is enabled or `sessionExclude()` was called.

You can create your own naming strategy and pass it to PageCache:
```php
$cache = new PageCache\PageCache();
$cache->setStrategy(new MyOwnStrategy());
```

Included with PageCache is `MobileStrategy`, based on [Mobile_Detect](https://github.com/serbanghita/Mobile-Detect).
It is useful if you are serving the same URL differently across devices.
See the [cache_mobiledetect.php](examples/cache_mobiledetect.php) example.

Config file
----
A configuration file is optional but can be specified during PageCache initialization for system-wide caching properties.

```php
// Optional system-wide cache config
use PageCache\PageCache;
$config_file = __DIR__.'/config.php';
$cache = new PageCache($config_file);
// You can get or set configuration parameters like this:
$cache->config()->getFileLock();
$cache->config()->setUseSession(true);
```

All available configuration options are documented in the [config](examples/config.php) file.

API - PageCache access methods
------------------------------------
The following are public methods of the PageCache class. This is not a complete list — check the source code and examples for more.

| Method | Description |
| --- | --- |
| `init(): void` | Initiate cache. This should be the last method called on the PageCache object. |
| `setStrategy(\PageCache\StrategyInterface): void` | Set cache strategy. Built-in: `DefaultStrategy`, `MobileStrategy`. |
| `setCacheAdapter(CacheInterface): void` | Set a PSR-16 cache adapter. |
| `getCurrentKey(): string` | Get the cache key for the current page. |
| `getStrategy(): Strategy` | Get the current Strategy object. |
| `clearPageCache(): void` | Clear cache for the current page. |
| `getPageCache(): string\|false` | Return the current page cache as a string, or false on error. |
| `isCached(): bool` | Check if the current page is cached. |
| `setLogger(\Psr\Log\LoggerInterface): void` | Set a PSR-3 logger. |
| `clearAllCache(): void` | Remove all content from cache storage. |
| `destroy(): void` | Destroy the PageCache instance and reset SessionHandler. |
| `config(): Config` | Get the Config object for reading or writing configuration. |

Caching pages using Sessions (i.e. User Login enabled applications)
-------------------------------------------------------------------
PageCache makes it simple to maintain a full page cache while using sessions.

A common use case is user authentication — the URL stays the same but page content differs per logged-in user.

To make PageCache session-aware, enable session support before calling `init()`:
```php
$cache->config()->setUseSession(true);
```
Session pages will now be cached separately for each unique session state.

Use `config()->setSessionExcludeKeys()` to exclude session variables that don't affect page content.
For example, if `$_SESSION['count']` changes but doesn't affect what the page renders, exclude it —
otherwise PageCache generates a separate cache file for each value of that variable:

```php
    // ...
    $cache->config()->setUseSession(true);
    $cache->config()->setSessionExcludeKeys(array('count'));
    // ...
    $cache->init();
```

Check out the [Session exclude keys](examples/demo-session-exclude-keys.php) example for more.

HTTP Headers
----------------------------------
PageCache can send cache-related HTTP headers, which helps browsers load pages faster and signals expiration
and modification status to search engines.

HTTP headers are disabled by default. Enable them with `config()->setSendHeaders(true)` before `init()`,
or set `send_headers = true` in the config file. Test on your local application before deploying.

When enabled, PageCache will automatically send:
- `Last-Modified`
- `Expires`
- `ETag`
- `HTTP/1.1 304 Not Modified`

When a `304 Not Modified` response is sent, the content body is omitted and the browser serves its local copy,
making responses very fast.

There is also a `forward_headers` option (`config()->setForwardHeaders(true)`) which reads these headers
from the application's response and stores them in the cache item, so headers are served consistently from cache.

Check out the [HTTP Headers demo](examples/demo-headers.php) for code.

Cache Stampede (dog-piling) protection
-----------------------
Under heavy load, multiple requests hitting the same expired URL can cause all clients to try to regenerate
cache simultaneously. This is called a [cache stampede](https://en.wikipedia.org/wiki/Cache_stampede).

PageCache uses two strategies to prevent this.

###### File Lock mechanism
By default PageCache sets file lock to `LOCK_EX | LOCK_NB`, meaning only one process can write to a cache file at a time.
During that write, all other processes read the old version. No user sees a blank page, and all users receive a cached response.

###### Random early and late expiration
Using random logarithmic calculations, cache expiration is varied slightly per client — up to 6 seconds before or after
the actual expiration time. This reduces the number of clients simultaneously hitting an expired page.

For example, with `config()->setCacheExpirationInSeconds(600)`, some clients will expire at 594s, some at 606s,
and everything in between. Since expiration values are not integers internally, the distribution is broad.
File locking handles simultaneous writes; random expiration reduces how often they occur.

Check out [PageCache examples](examples/) folder for sample code.
