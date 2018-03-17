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

class Config
{
    /**
     * Configuration array
     *
     * @var string
     */
    private $config = [];

    /**
     * Regenerate cache if cached content is less that this many bytes (some error occurred)
     *
     * @var int
     */
    private $minCacheFileSize = 10;

    /**
     * @var bool
     */
    private $enableLog = false;

    /**
     * File path for internal log file
     *
     * @var string
     */
    private $logFilePath;

    /**
     * Cache expiration in seconds
     *
     * @var int
     */
    private $cacheExpirationInSeconds = 1200;

    /**
     * Cache directory
     *
     * @var string
     */
    private $cachePath;

    /**
     * @var bool
     */
    private $useSession = false;

    /**
     * @var array
     */
    private $sessionExcludeKeys = [];

    /**
     * File locking preference for flock() function.
     * Default is a non-blocking exclusive write lock: LOCK_EX | LOCK_NB = 6
     * When false, file locking is disabled.
     *
     * @var false|int
     */
    private $fileLock = LOCK_EX | LOCK_NB;

    /**
     * @var bool
     */
    private $sendHeaders = false;

    /**
     * When true enables a dry run of the system. Useful for testing.
     * Default is false
     *
     * @var bool
     */
    private $dryRunMode = false;

    /**
     * Forward Last-Modified, Expires and ETag headers from application
     *
     * @var bool
     */
    private $forwardHeaders = false;

    /**
     * Config constructor.
     *
     * @param string $config_file_path Config File path
     * @throws PageCacheException
     */
    public function __construct($config_file_path = null)
    {
        // Load configuration file
        if (!empty($config_file_path)) {
            if (!file_exists($config_file_path)) {
                throw new PageCacheException(__METHOD__ . ' Config file path not valid: ' . $config_file_path);
            }
            /** @noinspection PhpIncludeInspection */
            $this->config = include $config_file_path;
            $this->setConfigValues();
        }
    }

    /**
     * Reads config array and assigns config values
     *
     * @throws PageCacheException
     */
    private function setConfigValues()
    {
        if (isset($this->config['min_cache_file_size']) && is_numeric($this->config['min_cache_file_size'])) {
            $this->minCacheFileSize = (int)$this->config['min_cache_file_size'];
        }

        //Enable Log
        if (isset($this->config['enable_log']) && $this->isBool($this->config['enable_log'])) {
            $this->enableLog = $this->config['enable_log'];
        }

        //Cache Expiration Time
        if (isset($this->config['cache_expiration_in_seconds'])) {
            if ($this->config['cache_expiration_in_seconds'] < 0) {
                throw new PageCacheException('PageCache config: invalid expiration value, < 0.');
            }
            $this->cacheExpirationInSeconds = (int)$this->config['cache_expiration_in_seconds'];
        }

        // Path to store cache files
        if (isset($this->config['cache_path'])) {
            // @codeCoverageIgnoreStart
            if (substr($this->config['cache_path'], -1) !== '/') {
                throw new PageCacheException(
                    'PageCache config: / trailing slash is expected at the end of cache_path.'
                );
            }

            //path writable?
            if (empty($this->config['cache_path']) || !is_writable($this->config['cache_path'])) {
                throw new PageCacheException('PageCache config: cache path not writable or empty');
            }

            $this->cachePath = $this->config['cache_path'];
            // @codeCoverageIgnoreEnd
        }

        // Log file path
        if (isset($this->config['log_file_path']) && !empty($this->config['log_file_path'])) {
            $this->logFilePath = $this->config['log_file_path'];
            // Directory must exist. File doesn't have to exist, if not found it will be created on first log write
            if (!$this->isParentDirectoryExists($this->logFilePath)) {
                throw new PageCacheException('Log file directory does not exist for the path provided '
                    . $this->logFilePath);
            }
        }

        // Use $_SESSION while caching or not
        if (isset($this->config['use_session']) && $this->isBool($this->config['use_session'])) {
            $this->useSession = $this->config['use_session'];
        }

        // Session exclude key
        if (isset($this->config['session_exclude_keys']) && is_array($this->config['session_exclude_keys'])) {
            // @codeCoverageIgnoreStart
            $this->sessionExcludeKeys = $this->config['session_exclude_keys'];
            // @codeCoverageIgnoreEnd
        }

        // File Locking
        if (isset($this->config['file_lock']) && !empty($this->config['file_lock'])) {
            $this->fileLock = $this->config['file_lock'];
        }

        // Send HTTP headers
        if (isset($this->config['send_headers']) && $this->isBool($this->config['send_headers'])) {
            $this->sendHeaders = $this->config['send_headers'];
        }

        // Forward Last-Modified and ETag headers to cache item
        if (isset($this->config['forward_headers']) && $this->isBool($this->config['forward_headers'])) {
            $this->forwardHeaders = $this->config['forward_headers'];
        }

        // Enable Dry run mode
        if (isset($this->config['dry_run_mode']) && $this->isBool($this->config['dry_run_mode'])) {
            $this->dryRunMode = $this->config['dry_run_mode'];
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
    public function isBool($var)
    {
        return ($var === true || $var === false);
    }

    /**
     * Get minimum allowed size of a cache file.
     *
     * @return int
     */
    public function getMinCacheFileSize()
    {
        return $this->minCacheFileSize;
    }

    /**
     * When generated cache file is less that this size, it is considered as invalid (will be regenerated on next call)

     * @param int $minCacheFileSize
     *
     * @return Config for chaining
     */
    public function setMinCacheFileSize($minCacheFileSize)
    {
        $this->minCacheFileSize = (int)$minCacheFileSize;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEnableLog()
    {
        return $this->enableLog;
    }

    /**
     * Disable or enable logging
     *
     * @param bool $enableLog
     * @return Config for chaining
     */
    public function setEnableLog($enableLog)
    {
        $this->enableLog = (bool)$enableLog;
        return $this;
    }

    /**
     * Get file path for internal log file
     *
     * @return string
     */
    public function getLogFilePath()
    {
        return $this->logFilePath;
    }

    /**
     * Set path for internal log file
     *
     * @param string $logFilePath
     * @return Config for chaining
     * @throws PageCacheException
     */
    public function setLogFilePath($logFilePath)
    {
        if (!$this->isParentDirectoryExists($logFilePath)) {
            throw new PageCacheException('Log file directory does not exist for the path provided '
                . $logFilePath);
        }

        $this->logFilePath = $logFilePath;
        return $this;
    }

    /**
     * @return int
     */
    public function getCacheExpirationInSeconds()
    {
        return $this->cacheExpirationInSeconds;
    }

    /**
     * @param int $seconds
     * @return Config for chaining
     * @throws PageCacheException
     */
    public function setCacheExpirationInSeconds($seconds)
    {
        if ($seconds < 0 || !is_numeric($seconds)) {
            throw new PageCacheException(__METHOD__ . ' Invalid expiration value, < 0.');
        }

        $this->cacheExpirationInSeconds = (int)$seconds;
        return $this;
    }

    /**
     * Get cache directory path
     *
     * @return string
     */
    public function getCachePath()
    {
        return $this->cachePath;
    }

    /**
     * Set cache path directory
     *
     * @param string $cachePath Full path of cache files
     *
     * @return Config for chaining
     * @throws PageCacheException
     */
    public function setCachePath($cachePath)
    {
        if (empty($cachePath) || !is_writable($cachePath)) {
            throw new PageCacheException(__METHOD__.' - Cache path not writable: '.$cachePath);
        }
        if (substr($cachePath, -1) !== '/') {
            throw new PageCacheException(__METHOD__.' - / trailing slash is expected at the end of cache_path.');
        }
        $this->cachePath = $cachePath;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUseSession()
    {
        return $this->useSession;
    }

    /**
     * Enable or disable Session handeling in cache files
     *
     * @param bool $useSession
     * @return Config for chaining
     */
    public function setUseSession($useSession)
    {
        $this->useSession = (bool)$useSession;
        if ($useSession) {
            SessionHandler::enable();
        } else {
            SessionHandler::disable();
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getSessionExcludeKeys()
    {
        return $this->sessionExcludeKeys;
    }

    /**
     * @param array $sessionExcludeKeys
     * @return Config for chaining
     */
    public function setSessionExcludeKeys(array $sessionExcludeKeys)
    {
        $this->sessionExcludeKeys = $sessionExcludeKeys;
        SessionHandler::excludeKeys($sessionExcludeKeys);
        return $this;
    }

    /**
     * @return false|int
     */
    public function getFileLock()
    {
        return $this->fileLock;
    }

    /**
     * Set file_lock value
     *
     * @param false|int $fileLock
     * @return Config for chaining
     */
    public function setFileLock($fileLock)
    {
        $this->fileLock = $fileLock;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSendHeaders()
    {
        return $this->sendHeaders;
    }

    /**
     * Enable or disable headers.
     * @param bool $sendHeaders
     * @return Config for chaining
     */
    public function setSendHeaders($sendHeaders)
    {
        $this->sendHeaders = (bool)$sendHeaders;
        return $this;
    }

    /**
     * @return bool
     */
    public function isForwardHeaders()
    {
        return $this->forwardHeaders;
    }

    /**
     * Enable or disable HTTP headers forwarding.
     * Works only if headers are enabled via PageCache::enableHeaders() method or via config
     *
     * @param bool $forwardHeaders True to enable, false to disable
     * @return Config for chaining
     */
    public function setForwardHeaders($forwardHeaders)
    {
        $this->forwardHeaders = (bool)$forwardHeaders;
        return $this;
    }

    /**
     * Enable or disable Dry Run Mode. Output will not be changed, everything else will function.
     *
     * @param bool $dryRunMode
     * @return Config for chaining
     */
    public function setDryRunMode($dryRunMode)
    {
        $this->dryRunMode = (bool)$dryRunMode;
        return $this;
    }

    /**
     * Whether Dry run mode is enabled
     *
     * @return bool
     */
    public function isDryRunMode()
    {
        return $this->dryRunMode;
    }

    /**
     * Checks if the parent directory of the file path provided exists
     *
     * @param string $file_path File Path
     * @return bool true if exists, false if not
     */
    private function isParentDirectoryExists($file_path)
    {
        $dir = dirname($file_path);
        return file_exists($dir);
    }
}
