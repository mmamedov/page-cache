[![Build Status](https://travis-ci.org/mmamedov/page-cache.svg?branch=master)](https://travis-ci.org/mmamedov/page-cache) [![Latest Stable Version](http://img.shields.io/packagist/v/mmamedov/page-cache.svg)](https://packagist.org/packages/mmamedov/page-cache) [![License](https://img.shields.io/packagist/l/mmamedov/page-cache.svg)](https://packagist.org/packages/mmamedov/page-cache) 

Full-page PHP Caching library
----
PageCache is a lightweight PHP library for full page cache, works out of the box with zero configuration. Use it when you need a simple yet powerfull file based PHP caching solution. Page caching for mobile devices is built-in.

Install PHP PageCache and start caching your PHP's browser output code using Composer:
```
composer require mmamedov/page-cache
```
Or manually add to your composer.json file:
```json
"require": {
    "mmamedov/page-cache": "^1.3"
}
```
Once PageCache is installed, include Composer's autoload.php file, or implement your own autoloader. Composer autoloader is recommended.

No Database calls
----
Once page is cached, there are no more database calls needed! Even if your page contains many database calls and complex logic, it will be executed once and cached for period you specify. No more overload!

This is a very efficient and simple method, to cache your most visited dynamic pages. [Tmawto.com](https://www.tmawto.com) website is built on PageCache, and is very fast.

Why another PHP Caching class?
----
Short answer - simplicity. If you want to include a couple lines of code on top of your dynamic PHP pages and be able to cache them fully, then PageCache is for you. No worrying about cache file name setup for each URL, no worries about your dynamically generated URL parameters and changing URLs. PageCache detects those changed and caches accordingly.

PageCache also detects $_SESSION changes and caches those pages correctly. This is useful if you have user authentication enabled on your site, and page contents change per user login while URL remains the same.

Lots of caching solutions focus on keyword-based approach, where you need to setup a keyword for your content (be it a full page cache, or a variable, etc.). There are great packages for keyword based approach. One could also use a more complex solution like a cache proxy, Varnish. PageCache on the other hand is a simple full page only caching solution, that does exactly what its name says - generates page cache in PHP.   

How PageCache works
----
PageCache doesn't ask you for a keyword, it automatically generates them based on Strategies implementing StrategyInterface. You can define your own naming strategy, based on your application needs.
Strategy class is responsible for generating a unique key for current request, key becomes file name for the cache file (if FileSystem storage is used).

```php
<?php
require_once __DIR__.'/../vendor/autoload.php';

$cache = new PageCache\PageCache();
$cache->init();

//rest of your PHP page code, everything below will be cached
```
For more examples see code inside [PageCache examples](examples/) directory.

For those who wonder, cache is saved into path specified in config file or using API, inside directories based on file hash. Based on the hash of the filename, 2 subdirectories will be created (if not created already), this is to avoid numerous files in a single cache directory. 

Caching Strategies
------------------
PageCache uses various strategies to differentiate among separate versions of the same page. 

All PageCache Strategies support sessions. See PageCache [cache page with sessions](examples/demo-session-support.php) example.

`DefaultStrategy()` is the default behaviour of PageCache. It caches pages and generated cache filenames using this PHP code: `md5($_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING'] . $session_str)`. You could create your own naming strategy and pass it to PageCache:

```php
$cache = new PageCache\PageCache();
$cache->setStrategy( new MyOwnStrategy() );
```

Included with the PageCache is the `MobileStrategy()` based on [Mobile_Detect](https://github.com/serbanghita/Mobile-Detect) . It is useful if you are serving the same URL differently accross devices. See [cache_mobiledetect.php PageCache example](examples/cache_mobiledetect.php) file for demo using MobileDetect._

You can define your own naming strategy, for example to incorporate logged in users into your applications. In this situations, URL might remain same, while content of the page will be different for each logged in user.

Config file
----
Although not required, configuration file can be specified during PageCache initialization for system wide caching properties

```php
//optional system-wide cache config
$config_file_ = __DIR__.'/config.php';
$cache = new PageCache\PageCache($config_file_);
```

All available configuration options from a config file:
```php
$config = array(
    /**
     * Minimum cache file size.
     * Generated cache files less than this many bytes, are considered invalid and are regenerated
     * Default 10
     */
    'min_cache_file_size' => 10,

    /**
     * Set true to enable logging, not recommended for production use, only for debugging
     * Default: false
     *
     * Effects both internal logger, and any external PSR-3 logger (if any) activated via setLogger() method
     */
    'enable_log' => false,

    /**
     * Internal log file location, enable_log must be true for loging to work
     * When external loger is provided via setLogger(), internal logging is disabled.
     */
    'log_file_path' => __DIR__ . '/log/cache.log',

    /**
     * Current page's cache expiration in seconds.
     * Default: 20 minutes, 1200 seconds.
     */
    'expiration' => 1200,

    /**
     * Cache directory location (mind the trailing slash "/").
     * Cache files are saved here.
     */
    'cache_path' => __DIR__ . '/tmp/cache/',

    /**
     * Use session support, if you have a login area or similar.
     * When page content changes according to some Session value, although URL remains the same.
     * Disabled by default.
     */
    'use_session' => false,

    /**
     * Exclude $_SESSION key(s) from caching strategies. Pass session name as keys to the array.
     *
     * When to use: Your application changes $_SESSION['count'] variable, but that doesn't reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *              Example: 'session_exclude_keys'=>array('count')
     */
    'session_exclude_keys' => array(),

    /**
     *
     * Locking mechanism to use when writing cache files. Default is LOCK_EX | LOCK_NB, which locks for
     * exclusive write while being non-blocking. Set whatever you want.
     * Read for details (http://php.net/manual/en/function.flock.php)
     *
     * Set file_lock = false to disable file locking.
     */
    'file_lock' => LOCK_EX | LOCK_NB
);
```

API - PageCache class public methods
------------------------------------
The following are public methods of PageCache class that you could call from your application. Check out examples for code samples.

- init():void - initiate cache, this should be your last method to call on PageCache object.
- setStrategy(\PageCache\StrategyInterface):void - set cache file strategy. Built-in strategies are DefaultStrategy() and MobileStrategy(). Define your own if needed.
- clearPageCache():void - Clear cache for current page, if this page was cached before.
- setPath(string):void - Location of cache files directory.
- setExpiration(int):void - Time in seconds for cache to expire.
- logFilePath(string):void - Set Log file path.
- enableLog():void - Enable logging.
- disableLog():void - Disable logging.
- enableSession():void - Enable session support.
- disableSession():void - Disable session support.
- sessionExclude(array):void - Exclude $_SESSION key(s) from caching strategies 
- isCached():bool - Checks if current page is in cache, true if exists false if not cached yet.
- getFilePath():string - Get full path for current page's filename. At this point file itself might or might not have been created.
- getFile():string - Get current page's cache file name.
- getPageCache():bool - Return current page cache as a string or false on error, if this page was cached before.
- getSessionExclude():array|null - Get excluded $_SESSION keys.
... docs for more methods to be completed

Caching pages using Sessions (i.e. User Login enabled applications)
-------------------------------------------------------------------
PageCache makes it simple to maintain a full page cache in PHP while using sessions.

For PageCache to be aware of your $_SESSION, in config file or in your PHP file you must enable session support.
In your PHP file, before calling `init()` call `$cache->enableSession()`. That's it! Now your session pages will be cached seperately for your different session values. 

Another handy method is `sessionExcludeKeys()`. Check out [Session exclude keys](examples/demo-session-exclude-keys.php) example for code.

When to use `sessionExcludeKeys()`: For example let's assume that your application changes $_SESSION['count'] variable, but that doesn't reflect on the page content.
Exclude this variable, otherwise PageCache will generate seperate cache files for each value of $_SESSION['count] session variable. To exclude 'count' session variable:
```php
    // ...
    $cache->enableSession();
    $cache->sessionExcludeKeys(array('count'));
    // ...
    $cache->init();
```

That's it!

Check out [PageCache examples](examples/) folder for sample code.
