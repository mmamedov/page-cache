PageCache ChangeLog
===================

### WIP

* Dry Run mode introduced
* More logs added, milliseconds in log times.

### 2.0 (2017-06-05)

* Backwards incompatible refactoring.
* PSR-16 introduced. PageCache now supports distributed file systems (Redis, Memcached, etc). 
* New PHP requirements >= 5.6.
* Config file now must return `return [...]`.
* Config `expiration` setting was renamed to `cache_expiration_in_seconds`

### 1.3.1 (2017-01-15)

* HTTP Headers introduced (optional). Thanks to @spotman.
* Clean cache directory with clearCache() (removes all files and directories inside main cache directory).
* [Refactoring] Removed static method from HashDirectory, other improvements.

### 1.3.0 (2016-05-23)

* PSR-2 coding style adopted (php-cs-fixer and phpcs are being used).
* File locking mechanism using flock(). Single write - many reads of the same cache file.
* Cache stampede protection (dog-piling effect) added, file lock and logarithmic random early and late expiration.
* PSR-3 Logger integration (PageCache already comes with a simple logging support). 
* Storage support added. Currently FileSystem only.
* PSR-0 support removed from php-cs-fixer (PSR-0 is deprecated)
* vfsStream adopted for mocking virtual file system in PHPUnit tests.
* Tests for PageCache class added. 
* Added testing for PHP 7 on Travis.

### 1.2.3 (2016-05-10)

* PHPUnit tests added to tests/, along with needed phpunit.xml settings and testing bootstrap. You can run PHPUnit tests easily on entire library.
* PHPUnit tests developed with full coverage for all classes, except PageCache class.
* Composer autoload-dev now loads tests/
* SessionHandler uses serialize() now.
* MobileStrategy now accepts $mobileDetect parameter. Useful for testing, but not only.
* Travis support added. PageCache successfully passes on PHP 5.5, 5.6 on Travis.

### 1.2.2 (2016-05-23)

* More session support improvements. SessionHandler class added.
* session_exclude_keys config parameter added, along with sessionExclude() method.
* HashDirectory update.
* General improvements.

### 1.2.1 (2016-04-22)

* MobileStrategy bug fixed, session wasn't contained inside md5().

### 1.2.0 (2016-04-22)

* Session support added to PageCache.
* All Strategies were updated to support sessions.
* Composer installation instructions added

### 1.1.0 (2016-04-20)

* clearPageCache() introduced to clear current page cache.
* Added getPageCache() and getFile() methods to PageCache class.
* Default value for min_cache_file_size was reduced to 10 from 1000.
* Examples updates

### 1.0.1 (2016-04-19)

* Improvements.
* Examples, README updates.

### 1.0.0   (2016-04-17)

* Initial release.