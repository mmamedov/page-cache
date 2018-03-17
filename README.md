[![Build Status](https://travis-ci.org/mmamedov/page-cache.svg?branch=master)](https://travis-ci.org/mmamedov/page-cache) [![Latest Stable Version](http://img.shields.io/packagist/v/mmamedov/page-cache.svg)](https://packagist.org/packages/mmamedov/page-cache) [![License](https://img.shields.io/packagist/l/mmamedov/page-cache.svg)](https://packagist.org/packages/mmamedov/page-cache) 

Full-page PHP Caching library
----
PageCache is a lightweight PHP library for full page cache, works out of the box with zero configuration. 
Use it when you need a simple yet powerful file based PHP caching solution. Page caching for mobile devices is built-in.

Install PHP PageCache and start caching your PHP's browser output code using Composer:
```
composer require mmamedov/page-cache
```
Or manually add to your composer.json file:
```json
{
  "require": {
      "mmamedov/page-cache": "^2.0"
  }
}
```
Once PageCache is installed, include Composer's autoload.php file, or implement your own autoloader. 
Composer autoloader is recommended.

Do not use `master` branch, as it may contain unstable code, use versioned branches instead.

#### Upgrading to to v2.*
Version 2.0 is not backwards compatible with versions starting with v1.0. Version 2.0 introduces new features and code
was refactored to enable us deliver more features.

When upgrading to version 2.0, please note the followings:
- PHP requirements >= 5.6.
- Your config file must be like this `return [...]` and not `$config = array(...);` like in previous version.
- Config `expiration` setting was renamed to `cache_expiration_in_seconds`
- Use `try/catch` to ensure proper page load in case of PageCache error.

If you find any other notable incompatibilities please let us know we will include them here.

No Database calls
----
Once page is cached, there are no more database calls needed! Even if your page contains many database calls and complex logic, 
it will be executed once and cached for period you specify. No more overload!

This is a very efficient and simple method, to cache your most visited dynamic pages. 
[Tmawto.com](https://www.tmawto.com) website is built on PageCache, and is very fast.

Why another PHP Caching class?
----
Short answer - simplicity. If you want to include a couple lines of code on top of your dynamic PHP pages and be able 
to cache them fully, then PageCache is for you. No worrying about cache file name setup for each URL, no worries 
about your dynamically generated URL parameters and changing URLs. PageCache detects those changed and caches accordingly.

PageCache also detects $_SESSION changes and caches those pages correctly. This is useful if you have user 
authentication enabled on your site, and page contents change per user login while URL remains the same.

Lots of caching solutions focus on keyword-based approach, where you need to setup a keyword for your 
content (be it a full page cache, or a variable, etc.). There are great packages for keyword based approach. 
One could also use a more complex solution like a cache proxy, Varnish. 
PageCache on the other hand is a simple full page only caching solution, that does exactly what its name says - 
generates page cache in PHP.   

How PageCache works
----
PageCache doesn't ask you for a keyword, it automatically generates them based on Strategies implementing StrategyInterface. 
You can define your own naming strategy, based on your application needs.
Strategy class is responsible for generating a unique key for current request, key becomes file name for the 
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

PageCache is built on top of [PSR-16 SimpleCache](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md) "key"=>"value" storage and has default file-based cache adapter in class `FileSystemPsrCacheAdapter`. This implementation is fast and uses `var_export` + `include` internally so every cache file is also automatically cached in OpCache or APC (if you have configured opcode caching for your project). This is perfect choice for single-server applications but if you have multi-server application you should want to share page cache content between servers. In this case you may use any PSR-16 compatible cache adapter for network-based "key"=>"value" storage like Memcached or Redis:

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
PageCache also has "Dry Run mode" option, is is turned off by default. 
Dry Run mode enables all functionality except that users won't be getting the cached content, they will be getting live content. 
No cache headers and no cached content will be send, if Dry Run mode is enabled. 
But cache will be stored in its as if it would run with Dry mode disabled. 
This mode lets PageCache dry run, useful if you want to test, debug or to see how your cache content populates.  

For more examples see code inside [PageCache examples](examples/) directory.

For those who wonder, cache is saved into path specified in config file or using API, inside directories based on file hash. 
Based on the hash of the filename, 2 subdirectories will be created (if not created already), 
this is to avoid numerous files in a single cache directory. 

Caching Strategies
------------------
PageCache uses various strategies to differentiate among separate versions of the same page. 

All PageCache Strategies support sessions. See PageCache [cache page with sessions](examples/demo-session-support.php) example.

`DefaultStrategy()` is the default behaviour of PageCache. It caches pages and generated cache filenames using this PHP code: 
```php
md5($_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING'] . $session_str)`
```
String `$session_str` is a serialized $_SESSION variable, with some keys ignored/or not based on whether session 
support is enabled or if sessionExclude() was called. 

You could create your own naming strategy and pass it to PageCache:
```php
$cache = new PageCache\PageCache();
$cache->setStrategy(new MyOwnStrategy());
```

Included with the PageCache is the `MobileStrategy()` based on [Mobile_Detect](https://github.com/serbanghita/Mobile-Detect). 
It is useful if you are serving the same URL differently across devices. 
See [cache_mobiledetect.php PageCache example](examples/cache_mobiledetect.php) file for demo using MobileDetect._

You can define your own naming strategy based on the needs of your application.

Config file
----
Although not required, configuration file can be specified during PageCache initialization for system wide caching properties

```php
// Optional system-wide cache config
use PageCache\PageCache;
$config_file_ = __DIR__.'/config.php';
$cache = new PageCache($config_file_);
// You can overwrite or get configuration parameters like this:
$cache->config()->getFileLock();
$cache->config()->setUseSession(true);
```

All available configuration options are documented in [config](examples/config.php) file. Be sure to check it.

API - PageCache access methods
------------------------------------
The following are public methods of PageCache class that you could call from your application. 
This is not a complete list. Check out examples and source code for more.

| Method | Description |
| --- | --- |
| init():void | initiate cache, this should be your last method to call on PageCache object.|
| setStrategy(\PageCache\StrategyInterface):void | set cache file strategy. Built-in strategies are DefaultStrategy() and MobileStrategy(). Define your own if needed.|
| setCacheAdapter(CacheInterface) | Set cache adapter. |
| getCurrentKey() : string | Get cache key value for this page.|
| getStrategy() : Strategy | Get set Strategy object. |
| setStrategy(Strategy) : void | Set Strategy object. |
| clearPageCache():void | Clear cache for current page, if this page was cached before. |
| getPageCache():string | Return current page cache as a string or false on error, if this page was cached before.|
| isCached():bool | Checks if current page is in cache, true if exists false if not cached yet.|
| setLogger(\Psr\Log\LoggerInterface):void | Set PSR-3 compliant logger.|
| clearAllCache() | Removes all content from cache storage.|
| destroy() : void | Destroy PageCache instance, reset SessionHandler | 
| config() : Config | Get Config element. Setting and getting configuration values is done via this method. |

Check source code for more available methods. 

Caching pages using Sessions (i.e. User Login enabled applications)
-------------------------------------------------------------------
PageCache makes it simple to maintain a full page cache in PHP while using sessions.

One example for using session feature could be when you need to  incorporate logged in users into your applications.
In that case URL might remain same (if you rely on sessions to log users in), while content of the page will be different for each logged in user.

For PageCache to be aware of your $_SESSION, in config file or in your PHP file you must enable session support.
In your PHP file, before calling `init()` call `$cache->config()->setUseSession(true)`. That's it! 
Now your session pages will be cached seperately for your different session values. 

Another handy method is `config()->setSessionExcludeKeys()`. Check out [Session exclude keys](examples/demo-session-exclude-keys.php) 
example for code.

When to use `config()->setSessionExcludeKeys()`: For example let's assume that your application changes $_SESSION['count'] variable, 
but that doesn't reflect on the page content.
Exclude this variable, otherwise PageCache will generate seperate cache files for each value of $_SESSION['count] 
session variable. To exclude 'count' session variable:
```php
    // ...
    $cache->config()->setUseSession(true);
    $cache->config()->setSessionExcludeKeys(array('count'));
    // ...
    $cache->init();
```

HTTP Headers
----------------------------------
PageCache can send cache related HTTP Headers. This helps browsers to load your pages faster and makes your
application SEO aware. Search engines will read this headers and know whether your page was modified or expired.

By default, HTTP headers are disabled. You can enable appropriate headers to be sent with the response to the client.
This is done by calling  `config()->setSendHeaders(true)`, prior to `init()`, or `send_headers = true` in config file.
Although disabled by default, we encourage you to use this feature.
Test on your local application before deploying it to your live version.

When HTTP headers are enabled, PageCache will attempt to send the following HTTP headers automatically with each response:
- `Last-Modified`
- `Expires`
- `ETag`
- `HTTP/1.1 304 Not Modified`

PageCache will attempt to send `HTTP/1.1 304 Not Modified` header along with cached content. When this header is sent, content
is omitted from the response. This makes your application super fast. Browser is responsible for fetching a locally
cached version when this header is present.

There is also `forward_headers` option in config, or `config()->setForwardHeaders(true)` which allows PageCache to fetch
values of these HTTP headers from the app response and store them into cache item so headers would be cached too.
This approach is useful if your app has fine-grained control.

Check out [HTTP Headers demo](examples/demo-headers.php) for code.

Cache Stampede (dog-piling) protection
-----------------------
Under a heavy load or when multiple calls to the web server are made to the same URL when it has been expired, 
there might occur a condition where system might become unresponsive or when all clients will try to regenerate 
cache of the same page. This effect is called [cache stampede](https://en.wikipedia.org/wiki/Cache_stampede).

PageCache uses 2 strategies to defeat cache stampede, and even when thousands request are made to the same page system
continues to function normally.

###### File Lock mechanism
By default PageCache sets file lock to `LOCK_EX | LOCK_NB`, which means that only 1 process will be able to write into
a cache file. During that write, all other processes will read old version of the file. This ensures that no user is 
presented with a blank page, and that all users receive a cached version of the URL - without exceptions.

This eliminates possibility of cache stampede, since there can only be a single write to a file, even though thousands 
of clients have reached a page when it has expired (one client generates new cache and sees the new cached version, 
the rest will receive old version). Number of users hitting page when it has expired is also reduced by random 
early and late expiration.

###### Random early and late expiration
Using random logarithmic calculations, producing sometimes negative and at times positive results, cache expiration
for each client hitting the same URL at the same time is going to be different. While some clients might still get
the same cache expiration value as others, overall distribution of cache expiration value among clients is random.

Making pages expire randomly at most 6 seconds before or after actual cache expiration of the page, ensures that we have
less clients trying to regenerate cache content. File locking already takes care of simultaneous writes of cache page, 
random expiration takes it a step further minimizing the number of such required attempts. 

To give an example, consider you have set expiration to 10 minutes `config()->setCacheExpirationInSeconds(600)`. 
Page will expire for some clients in 594 seconds, for some in 606 seconds, and for some in 600 seconds. 
Actual page expiration is going to be anywhere  in between 594 and 606 seconds inclusive, this is randomly calculated. 
Expiration value is not an integer internally, so there are a lot more of random expiration values than you can think of. 


That's it!

Check out [PageCache examples](examples/) folder for sample code.

