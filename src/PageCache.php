<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache;

use PageCache\Storage\FileSystem;
use PageCache\Strategy;

/**
 *
 * PageCache is the main class, create PageCache object and call init() to start caching
 *
 */
class PageCache
{
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
     * Full path of the current cache file
     *
     * @var string
     */
    private $file;

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
     * Make sure only one instance of PageCache is created
     *
     * @var bool
     */
    private static $ins = null;

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
     * @throws \Exception
     */
    public function __construct($config_file_path = null)
    {
        if (isset(PageCache::$ins)) {
            throw new \Exception('PageCache already created.');
        }

        /**
         * load configuration file
         */
        if (!is_null($config_file_path) && file_exists($config_file_path)) {
            $config = null;
            include $config_file_path;

            $this->parseConfig($config);
        } else {
            //config file not found, set defaults
            //do not use $_SESSION in cache, by default
            SessionHandler::disable();
        }

        PageCache::$ins = true;

        //default file naming strategy
        $this->strategy = new Strategy\DefaultStrategy();
    }

    /**
     * Initialize cache.
     * If you need to set configuration options, do so before calling this method.
     */
    public function init()
    {
        $this->log(__METHOD__ . ' uri:' . $_SERVER['REQUEST_URI']
            . '; script:' . $_SERVER['SCRIPT_NAME'] . '; query:' . $_SERVER['QUERY_STRING'] . '.');
        $this->generateCacheFile();
        $this->display();

        //array to handle current namespace
        ob_start(array($this, 'createPage'));
    }

    /**
     * Fetch cache and display it.
     *
     * Cache expiration is cache_expire seconds +/- a random value of seconds, from -6 to 6.
     *
     * So although expiration is set for example 200 seconds, it is not guaranteed that it will expire in exactly
     * that many seconds. It could expire at 200 seconds, but also could expire in 206 seconds, or 194 seconds, or
     * anywhere in between 206 and 194 seconds. This is done to better deal with cache stampede, and improve cache
     * hit rate.
     *
     * If cache file not found or not valid, function returns, and init() continues with cache generation(createPage())
     */
    private function display()
    {
        $file = $this->file;

        if (!file_exists($file)
            || (filemtime($file) < (time() - $this->cache_expire + log10(rand(10, 1000)) * rand(-2, 2)))
            || filesize($file) < $this->min_cache_file_size
        ) {
            $this->log(__METHOD__ . ' Cache file not found or cache expired or min_cache_file_size not met.');
            return;
        }

        $this->log(__METHOD__ . ' Cache file found.');

        //Read file, output cache. If error occurred, ob_start() will be called next in PageCache
        if (@readfile($file) !== false) {
            //stop execution
            exit();
        }
    }

    /**
     * Write page to cache, and display it.
     * When write is unsuccessful, string content is returned.
     *
     * @param $content string from ob_start
     * @return string page content
     */
    private function createPage($content)
    {
        $storage = new FileSystem($content);

        try {
            $storage->setFileLock($this->file_lock);
            $storage->setFilePath($this->file);
        } catch (\Exception $e) {
            $this->log(__METHOD__ . ' FileSystem Exception', $e);
        }

        $result = $storage->writeAttempt();

        if ($result !== FileSystem::OK) {
            $this->log(__METHOD__ . ' FileSystem writeAttempt not an OK result: ' . $result);
        }

        /**
         * Return page content
         */
        return $content;
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
     *
     * Generates cache file name based on URL, Strategy, and SessionHandler
     *
     */
    private function generateCacheFile()
    {
        //cache file name already generated?
        if (!empty($this->file)) {
            return;
        }

        try {
            $fname = $this->strategy->strategy();

            $hashDirectory = new HashDirectory($fname, $this->cache_path);
            $dir_str = $hashDirectory->getHash();

            $this->file = $this->cache_path . $dir_str . $fname;
        } catch (\Exception $e) {
            $this->log(__METHOD__ . ' Exception', $e);
        }

        $this->log(__METHOD__ . ' Cache file: ' . $this->file);
    }

    /**
     * Clear cache for current page, if this page was cached before.
     */
    public function clearPageCache()
    {
        //if cache file name not set yet, get it
        if (!empty($this->file)) {
            $filepath = $this->file;
        } else {
            $filepath = $this->getFilePath();
        }

        /**
         * Cache file name is now available, check if cache file exists.
         * If init() wasn't called on this page before, there won't be any cache saved, so we check with file_exists.
         */
        if (file_exists($filepath) && is_file($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Return current page cache as a string or false on error, if this page was cached before.
     */
    public function getPageCache()
    {
        //if cache file name not set yet, get it
        if (!empty($this->file)) {
            $filepath = $this->file;
        } else {
            $filepath = $this->getFilePath();
        }

        //suppress E_WARNING of file_get_contents, when file not found
        if (false !== $str = @file_get_contents($filepath)) {
            return $str;
        }

        return false;
    }

    /**
     * Checks if current page is in cache.
     *
     * @return bool true if page has a valid cache file saved, false if not
     */
    public function isCached()
    {
        //if cache file name not set yet, get it
        if (!empty($this->file)) {
            $filepath = $this->file;
        } else {
            $filepath = $this->getFilePath();
        }

        if (file_exists($filepath) && filesize($filepath) >= $this->min_cache_file_size) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get current page's cache file name. At this point file itself might or might not have been created.
     *
     * @return string cache file
     */
    public function getFile()
    {
        //call strategy
        return $this->strategy->strategy();
    }

    /**
     * Get full path for current page's filename. At this point file itself might or might not have been created.
     *
     * Filename is created the same way as getFile()
     *
     * @return array array(filename, directory)
     */
    public function getFilePath()
    {
        $fname = $this->getFile();
        $subdir = HashDirectory::getLocation($fname);

        return $this->cache_path . $subdir . $fname;
    }

    /**
     * Location of cache files directory.
     *
     * @param $path string full path of cache files
     * @throws \Exception
     */
    public function setPath($path)
    {
        //path writable?
        if (empty($path) || !is_writable($path)) {
            $this->log(__METHOD__ . ' Cache path not writable.');
            throw new \Exception('setPath() - Cache path not writable: ' . $path);
        }

        if (substr($path, -1) != '/') {
            throw new \Exception('setPath() - / trailing slash is expected at the end of cache_path.');
        }

        $this->cache_path = $path;
    }

    /**
     * Time in seconds for cache to expire
     *
     * @param $seconds int seconds
     * @throws \Exception
     */
    public function setExpiration($seconds)
    {
        if ($seconds < 0 || !is_numeric($seconds)) {
            $this->log(__METHOD__ . ' Invalid expiration value, < 0: ' . $seconds);
            throw new \Exception('Invalid expiration value, < 0.');
        }

        $this->cache_expire = intval($seconds);
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
     * When generated cache file is less that this size, it is considered as invalid (will be regenerated on next call)
     *
     * @param $min_cache_file_size int bytes for filename
     */
    public function setMinCacheFileSize($min_cache_file_size)
    {
        $this->min_cache_file_size = $min_cache_file_size;
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

        return true;
    }

    /**
     * Exclude $_SESSION key(s) from caching strategies.
     *
     * When to use: Your application changes $_SESSION['count'] variable, but that doesn't reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *
     * @param array $keys $_SESSION keys to exclude from caching strategies
     * @return bool
     */
    public function sessionExclude(array $keys)
    {
        SessionHandler::excludeKeys($keys);

        return true;
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
     * Parses conf.php files and sets parameters for this object
     *
     * @param array $config
     * @throws \Exception min params not set
     * @return true
     */
    private function parseConfig(array $config)
    {
        $this->config = $config;

        $this->min_cache_file_size = intval($this->config['min_cache_file_size']);

        if (isset($this->config['enable_log']) && $this->isBool($this->config['enable_log'])) {
            $this->enable_log = $this->config['enable_log'];
        }

        if (isset($this->config['expiration'])) {
            if ($this->config['expiration'] < 0) {
                throw new \Exception('PageCache config: invalid expiration value, < 0.');
            }

            $this->cache_expire = intval($this->config['expiration']);
        }

        //path to store cache files
        if (isset($this->config['cache_path'])) {
            if (substr($this->config['cache_path'], -1) != '/') {
                throw new \Exception('PageCache config: / trailing slash is expected at the end of cache_path.');
            }

            //path writable?
            if (empty($this->config['cache_path']) || !is_writable($this->config['cache_path'])) {
                throw new \Exception('PageCache config: cache path not writable or empty');
            }

            $this->cache_path = $this->config['cache_path'];
        }

        //log file path
        if (isset($this->config['log_file_path']) && !empty($this->config['log_file_path'])) {
            $this->log_file_path = $this->config['log_file_path'];
        }

        //use $_SESSION while caching or not
        if (isset($this->config['use_session']) && $this->isBool($this->config['use_session'])) {
            SessionHandler::setStatus($this->config['use_session']);
        }

        //session exclude key
        if (isset($this->config['session_exclude_keys']) && !empty($this->config['session_exclude_keys'])) {
            SessionHandler::excludeKeys($this->config['session_exclude_keys']);
        }

        //File Locking
        if (isset($this->config['file_lock']) && !empty($this->config['file_lock'])) {
            $this->file_lock = $this->config['file_lock'];
        }

        return true;
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
     * Log message using PSR Logger (if enabled)
     *
     * @param string $msg
     * @param null|\Exception $exception
     * @return bool
     */
    private function log($msg, $exception = null)
    {
        if (!$this->enable_log) {
            return false;
        }

        /**
         * if an external Logger is available
         */
        if (isset($this->logger)) {

            /** @var \Psr\Log\LoggerInterface */
            $this->logger->debug($msg, array('Exception', $exception));
        } else {
            //internal simple log
            if (!empty($this->log_file_path)) {
                error_log(
                    '[' . date('Y-m-d H:i:s') . '] '
                    . $msg . (empty($exception) ? '' : ' {Exception: ' . $exception->getMessage() . '}') . "\n",
                    3,
                    $this->log_file_path,
                    null
                );
            }
        }

        return true;
    }

    /**
     * Checks if given variable is a boolean value.
     * For PHP < 5.5 (boolval alternative)
     *
     * @param mixed $var
     * @return bool true if is boolean, false if is not
     */
    private function isBool($var)
    {
        if ($var === true || $var === false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Destroy PageCache instance
     */
    public static function destroy()
    {
        if (isset(PageCache::$ins)) {
            PageCache::$ins = null;
            SessionHandler::reset();
        }
    }

    /**
     * @return false|int
     */
    public function getFileLock()
    {
        return $this->file_lock;
    }

    /**
     * @param false|int $file_lock
     */
    public function setFileLock($file_lock)
    {
        $this->file_lock = $file_lock;
    }


    /**
     * Kept for BC. Same as getExpiration()
     *
     * @deprecated Use getExpiration() instead
     * @return int
     */
    public function getCacheExpiration()
    {
        return $this->cache_expire;
    }

    /**
     * Get cach expiration in seconds
     *
     * @return int
     */
    public function getExpiration()
    {
        return $this->getCacheExpiration();
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->cache_path;
    }

    /**
     * @return string
     */
    public function getLogFilePath()
    {
        return $this->log_file_path;
    }

    /**
     * @param string $log_file_path
     */
    public function setLogFilePath($log_file_path)
    {
        if (!empty($log_file_path)) {
            $this->log_file_path = $log_file_path;
        }
    }

    /**
     * @return int
     */
    public function getMinCacheFileSize()
    {
        return $this->min_cache_file_size;
    }

    /**
     * @return StrategyInterface
     */
    public function getStrategy()
    {
        return $this->strategy;
    }
}
