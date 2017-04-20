<?php
/**
 * This file is part of the PageCache package.
 *
 * @author    Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2017
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache;

use DateTime;
use PageCache\Strategy\DefaultStrategy;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;

/**
 * Class PageCache
 * PageCache is the main class, create PageCache object and call init() to start caching
 *
 * @package PageCache
 */
class PageCache
{
    /**
     * Make sure only one instance of PageCache is created
     *
     * @var bool
     */
    private static $ins = null;

    /**
     * @var HttpHeaders
     */
    protected $httpHeaders;

    /**
     * @var \PageCache\Storage
     */
    private $storage;

    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    private $cache_adapter;

    /**
     * Cache directory
     *
     * @var string
     */
    private $cache_path;

    /**
     * Cache expiration in seconds
     *
     * @var int
     */
    private $cache_expire = 1200;

    /**
     * @var string
     */
    private $current_key;

    /**
     * @var \PageCache\CacheItemInterface
     */
    private $current_item;

    /**
     * Enable logging
     *
     * @var bool
     */
    private $enable_log = false;

    /**
     * File path for internal log file
     *
     * @var string
     */
    private $log_file_path;

    /**
     * StrategyInterface for cache naming strategy
     *
     * @var StrategyInterface
     */
    private $strategy;

    /**
     * Configuration array
     *
     * @var array
     */
    private $config;

    /**
     * File locking preference for flock() function.
     * Default is a non-blocking exclusive write lock: LOCK_EX | LOCK_NB = 6
     * When false, file locking is disabled.
     *
     * @var false|int
     */
    private $file_lock = 6;

    /**
     * Regenerate cache if cached content is less that this many bytes (some error occurred)
     *
     * @var int
     */
    private $min_cache_file_size = 10;

    /**
     * Forward Last-Modified, Expires and ETag headers from application
     *
     * @var bool
     */
    private $forward_headers = false;

    /**
     * When logging is enabled, defines a PSR logging library for logging exceptions and errors.
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger = null;

    /**
     * PageCache constructor.
     *
     * @param null|string $config_file_path
     *
     * @throws \Exception
     */
    public function __construct($config_file_path = null)
    {
        if (PageCache::$ins) {
            throw new PageCacheException('PageCache already created.');
        }
        // Load configuration file
        if ($config_file_path && file_exists($config_file_path)) {
            $config = null;
            /** @noinspection PhpIncludeInspection */
            include $config_file_path;

            $this->parseConfig($config);
        } else {
            //config file not found, set defaults
            //do not use $_SESSION in cache, by default
            SessionHandler::disable();
        }
        PageCache::$ins = true;

        $this->httpHeaders = new HttpHeaders();
    }

    /**
     * Parses conf.php files and sets parameters for this object
     *
     * @param array $config
     *
     * @throws \Exception min params not set
     */
    private function parseConfig(array $config)
    {
        $this->config = $config;

        $this->min_cache_file_size = (int)$this->config['min_cache_file_size'];

        if (isset($this->config['enable_log']) && $this->isBool($this->config['enable_log'])) {
            $this->enable_log = $this->config['enable_log'];
        }

        if (isset($this->config['expiration'])) {
            if ($this->config['expiration'] < 0) {
                throw new PageCacheException('PageCache config: invalid expiration value, < 0.');
            }

            $this->cache_expire = (int)$this->config['expiration'];
        }

        // Path to store cache files
        if (isset($this->config['cache_path'])) {
            // @codeCoverageIgnoreStart
            if (substr($this->config['cache_path'], -1) !== '/') {
                throw new PageCacheException('PageCache config: / trailing slash is expected at the end of cache_path.');
            }

            //path writable?
            if (empty($this->config['cache_path']) || !is_writable($this->config['cache_path'])) {
                throw new PageCacheException('PageCache config: cache path not writable or empty');
            }

            $this->cache_path = $this->config['cache_path'];
            // @codeCoverageIgnoreEnd
        }

        // Log file path
        if (isset($this->config['log_file_path']) && !empty($this->config['log_file_path'])) {
            $this->log_file_path = $this->config['log_file_path'];
        }

        // Use $_SESSION while caching or not
        if (isset($this->config['use_session']) && $this->isBool($this->config['use_session'])) {
            SessionHandler::setStatus($this->config['use_session']);
        }

        // Session exclude key
        if (isset($this->config['session_exclude_keys']) && !empty($this->config['session_exclude_keys'])) {
            // @codeCoverageIgnoreStart
            SessionHandler::excludeKeys($this->config['session_exclude_keys']);
            // @codeCoverageIgnoreEnd
        }

        // File Locking
        if (isset($this->config['file_lock']) && !empty($this->config['file_lock'])) {
            $this->file_lock = $this->config['file_lock'];
        }

        // Send HTTP headers
        if (isset($this->config['send_headers']) && $this->isBool($this->config['send_headers'])) {
            $this->httpHeaders->enableHeaders($this->config['send_headers']);
        }

        // Forward Last-Modified and ETag headers to cache item
        if (isset($this->config['forward_headers']) && $this->isBool($this->config['forward_headers'])) {
            $this->forward_headers = $this->config['forward_headers'];
        }
    }

    /**
     * Checks if given variable is a boolean value.
     * For PHP < 5.5 (boolval alternative)
     *
     * @param mixed $var
     *
     * @return bool true if is boolean, false if is not
     */
    private function isBool($var)
    {
        return ($var === true || $var === false);
    }

    /**
     * Destroy PageCache instance
     */
    public static function destroy()
    {
        if (PageCache::$ins) {
            PageCache::$ins = null;
            SessionHandler::reset();
        }
    }

    public function setCache(CacheInterface $cache)
    {
        $this->cache_adapter = $cache;
    }

    /**
     * Initialize cache.
     * If you need to set configuration options, do so before calling this method.
     */
    public function init()
    {
        $this->log(__METHOD__.' uri:'.$_SERVER['REQUEST_URI']
            .'; script:'.$_SERVER['SCRIPT_NAME'].'; query:'.$_SERVER['QUERY_STRING'].'.');

        $this->storage = new Storage($this->cache_adapter ?: $this->getDefaultCacheAdapter(), $this->cache_expire);

        if (!$this->strategy) {
            $this->strategy = new DefaultStrategy();
        }

        // Detect current request hash key
        $key = $this->getCurrentKey();

        // Search for valid cache item for current request
        if ($this->current_item = $this->storage->get($key)) {
            // Display cache item if found
            // If cache file not found or not valid, init() continues with cache generation(storePageContent())
            $this->displayItem($this->current_item);
        }

        $this->log(__METHOD__.' Cache item not found for hash '.$key);

        // Fetch page content and save it
        ob_start(function ($content) {
            try {
                return $this->storePageContent($content);
            } catch (\Throwable $t) {
                $this->logException($t);
            } catch (\Exception $e) {
                $this->logException($e);
            }

            return $content;
        });
    }

    /**
     * Log message using PSR Logger, or error_log.
     * Works only when logging was enabled.
     *
     * @param string          $msg
     * @param null|\Exception $exception
     *
     * @return bool true when logged, false when didn't log
     */
    private function log($msg, $exception = null)
    {
        if (!$this->enable_log) {
            return false;
        }

        // If an external logger is not available but internal logger is configured
        if (!$this->logger && $this->log_file_path) {
            $this->logger = new DefaultLogger($this->log_file_path);
        }

        if ($this->logger) {
            $level = $exception ? LogLevel::ALERT : LogLevel::DEBUG;
            $this->logger->log($level, $msg, ['exception' => $exception]);
        }

        return true;
    }

    private function getDefaultCacheAdapter()
    {
        return new FileSystemCacheAdapter($this->cache_path, $this->file_lock, $this->min_cache_file_size);
    }

    private function getCurrentKey()
    {
        if (!$this->current_key) {
            $this->current_key = $this->strategy->strategy();
        }

        return $this->current_key;
    }

    /**
     * Display cache item.
     *
     * @param \PageCache\CacheItemInterface $item
     */
    private function displayItem(CacheItemInterface $item)
    {
        $this->httpHeaders
            ->setLastModified($item->getLastModified())
            ->setExpires($item->getExpiresAt())
            ->setETag($item->getETagString());

        // Send headers and process If-Modified-Since header
        $this->httpHeaders->send();

        // Normal flow, show cached content
        $this->log(__METHOD__.' Cache item found.');

        // Echo content and stop execution
        echo $item->getContent();
        exit();
    }

    /**
     * Write page to cache, and display it.
     * When write is unsuccessful, string content is returned.
     *
     * @param string $content String from ob_start
     *
     * @return string Page content
     */
    private function storePageContent($content)
    {
        $key  = $this->getCurrentKey();
        $item = new CacheItem($key);

        $isHeadersForwardingEnabled = $this->forward_headers && $this->httpHeaders->isEnabledHeaders();

        $this->log('Header forwarding is '.($isHeadersForwardingEnabled ? 'enabled' : 'disabled'));

        $expiresAt = $isHeadersForwardingEnabled
            ? $this->httpHeaders->detectResponseExpires()
            : null;

        $lastModified = $isHeadersForwardingEnabled
            ? $this->httpHeaders->detectResponseLastModified()
            : null;

        $eTagString = $isHeadersForwardingEnabled
            ? $this->httpHeaders->detectResponseETagString()
            : null;

        // Store original Expires header time if set
        if ($expiresAt) {
            $item->setExpiresAt($expiresAt);
        }

        // Set current time as last modified if none provided
        if (!$lastModified) {
            $lastModified = new DateTime;
        }

        /**
         * Set ETag from from last modified time if none provided
         *
         * @link https://github.com/mmamedov/page-cache/issues/1#issuecomment-273875002
         */
        if (!$eTagString) {
            $eTagString = md5($lastModified->getTimestamp());
        }

        $item
            ->setContent($content)
            ->setLastModified($lastModified)
            ->setETagString($eTagString);

        $this->storage->set($item);

        $this->log(__METHOD__.' Data stored for key '.$key);

        // Return page content
        return $content;
    }

    /**
     * @param \Throwable|\Exception $e
     *
     * @return bool
     */
    private function logException($e)
    {
        return $this->log('', $e);
    }

    /**
     * Clear cache for provided page (or current page if none given)
     *
     * @param \PageCache\CacheItemInterface|null $item
     *
     * @throws \PageCache\PageCacheException
     */
    public function clearPageCache(CacheItemInterface $item = null)
    {
        // Use current item if not provided in arguments
        if (!$item) {
            $item = $this->current_item;
        }

        if (!$item) {
            throw new PageCacheException(__METHOD__.' Page cache item can not be detected');
        }

        $this->storage->delete($item);
    }

    /**
     * Return current page cache as a string or false on error, if this page was cached before.
     *
     * @deprecated No direct cache file manipulating
     */
    public function getPageCache()
    {
        if (!$this->current_item) {
            return false;
        }

        return $this->current_item->getContent();
    }

    /**
     * Checks if current page is in cache.
     *
     * @return bool Returns true if page has a valid cache file saved, false if not
     */
    public function isCached()
    {
        $key = $this->getCurrentKey();

        $item = $this->storage->get($key);

        return (bool)$item;
    }

    /**
     * Get current page's cache file name. At this point file itself might or might not have been created.
     *
     * @return string cache file
     * @deprecated No direct file manipulating
     */
    public function getFile()
    {
        throw new PageCacheException(__METHOD__.' is deprecated');
    }

    /**
     * Get full path for current page's filename. At this point file itself might or might not have been created.
     *
     * Filename is created the same way as getFile()
     *
     * @deprecated No direct file manipulating
     * @return string
     */
    public function getFilePath()
    {
        throw new PageCacheException(__METHOD__.' is deprecated');
    }

    /**
     * Location of cache files directory.
     *
     * @param $path string full path of cache files
     *
     * @throws \Exception
     */
    public function setPath($path)
    {
        if (empty($path) || !is_writable($path)) {
            $this->log(__METHOD__.' Cache path not writable.');
            throw new PageCacheException('setPath() - Cache path not writable: '.$path);
        }
        if (substr($path, -1) !== '/') {
            throw new PageCacheException('setPath() - / trailing slash is expected at the end of cache_path.');
        }
        $this->cache_path = $path;
    }

    /**
     * Time in seconds for cache to expire
     *
     * @param $seconds int seconds
     *
     * @throws \Exception
     */
    public function setExpiration($seconds)
    {
        if ($seconds < 0 || !is_numeric($seconds)) {
            $this->log(__METHOD__.' Invalid expiration value, < 0: '.$seconds);
            throw new PageCacheException(__METHOD__.' Invalid expiration value, < 0.');
        }

        $this->cache_expire = (int)$seconds;
    }

    /**
     * Enable logging.
     */
    public function enableLog()
    {
        $this->enable_log = true;
    }

    /**
     * Disable logging.
     */
    public function disableLog()
    {
        $this->enable_log = false;
    }

    /**
     * Use sessions when caching page.
     * For the same URL session enabled page might be displayed differently, when for example user has logged in.
     */
    public function enableSession()
    {
        SessionHandler::enable();
    }

    /**
     * Do not use sessions when caching page.
     */
    public function disableSession()
    {
        SessionHandler::disable();
    }

    /**
     * Exclude $_SESSION key(s) from caching strategies.
     *
     * When to use: Your application changes $_SESSION['count'] variable, but that doesn't reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *
     * @param array $keys $_SESSION keys to exclude from caching strategies
     */
    public function sessionExclude(array $keys)
    {
        SessionHandler::excludeKeys($keys);
    }

    /**
     * Get excluded $_SESSION keys
     *
     * @return array|null
     */
    public function getSessionExclude()
    {
        return SessionHandler::getExcludeKeys();
    }

    /**
     * Set logger
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get file_lock value
     *
     * @return false|int
     */
    public function getFileLock()
    {
        return $this->file_lock;
    }

    /**
     * Set file_lock value
     *
     * @param false|int $file_lock
     */
    public function setFileLock($file_lock)
    {
        $this->file_lock = $file_lock;
    }

    /**
     * Kept for backwards-compatibility. Same as getExpiration()
     *
     * @deprecated Use getExpiration() instead
     * @return int
     */
    public function getCacheExpiration()
    {
        return $this->cache_expire;
    }

    /**
     * Get cache expiration in seconds
     *
     * @return int
     */
    public function getExpiration()
    {
        return $this->cache_expire;
    }

    /**
     * Get cache directory path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->cache_path;
    }

    /**
     * Get file path for internal log file
     *
     * @return string
     */
    public function getLogFilePath()
    {
        return $this->log_file_path;
    }

    /**
     * Set path for internal log file
     *
     * @param string $log_file_path
     */
    public function setLogFilePath($log_file_path)
    {
        if (!empty($log_file_path)) {
            $this->log_file_path = $log_file_path;
        }
    }

    /**
     * Get minimum allowed size of a cache file.
     *
     * @return int
     */
    public function getMinCacheFileSize()
    {
        return $this->min_cache_file_size;
    }

    /**
     * When generated cache file is less that this size, it is considered as invalid (will be regenerated on next call)
     *
     * @param $min_cache_file_size int bytes for filename
     */
    public function setMinCacheFileSize($min_cache_file_size)
    {
        $this->min_cache_file_size = $min_cache_file_size;
    }

    /**
     * Get current Strategy.
     *
     * @return StrategyInterface
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * Caching strategy - expected file name for this current page.
     *
     * @param StrategyInterface $strategy object for choosing appropriate cache file name
     */
    public function setStrategy(StrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * Enable or disable headers.
     *
     * @param bool $enable True to enable, false to disable
     */
    public function enableHeaders($enable)
    {
        $this->httpHeaders->enableHeaders($enable);
    }

    /**
     * Enable or disable HTTP headers forwarding.
     * Works only if headers are enabled via PageCache::enableHeaders() method or via config
     *
     * @param bool $enable True to enable, false to disable
     */
    public function forwardHeaders($enable)
    {
        $this->forward_headers = (bool)$enable;
    }

    /**
     * Delete everything from cache.
     */
    public function clearCache()
    {
        $this->storage->clear();
    }
}
