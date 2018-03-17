<?php
/**
 * This file is part of the PageCache package.
 *
 * @author    Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2016
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache;

use DateTime;
use PageCache\Storage\CacheItem;
use PageCache\Storage\CacheItemInterface;
use PageCache\Storage\CacheItemStorage;
use PageCache\Storage\FileSystem\FileSystemCacheAdapter;
use PageCache\Strategy\DefaultStrategy;
use Psr\Log\LoggerInterface;
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
     * @var \PageCache\Storage\CacheItemStorage
     */
    private $itemStorage;

    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    private $cacheAdapter;

    /**
     * Cache data key based on Strategy
     *
     * @var string
     */
    private $currentKey;

    /**
     * @var \PageCache\Storage\CacheItemInterface
     */
    private $currentItem;

    /**
     * StrategyInterface for cache naming strategy
     *
     * @var StrategyInterface
     */
    private $strategy;

    /**
     * Configuration object
     *
     * @var Config
     */
    private $config;

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
     * @throws \PageCache\PageCacheException
     */
    public function __construct($config_file_path = null)
    {
        if (PageCache::$ins) {
            throw new PageCacheException('PageCache already created.');
        }

        $this->config = new Config($config_file_path);
        $this->httpHeaders = new HttpHeaders();
        $this->strategy = new DefaultStrategy();

        // Disable Session by default
        if (!$this->config->isUseSession()) {
            SessionHandler::disable();
        }

        PageCache::$ins = true;
    }

    /**
     * Set Cache Adapter
     *
     * @param CacheInterface $cache Cache Interface compatible Adapter
     */
    public function setCacheAdapter(CacheInterface $cache)
    {
        $this->cacheAdapter = $cache;
    }

    /**
     * Initialize cache.
     * If you need to set configuration options, do so before calling this method.
     */
    public function init()
    {
        if ($this->config()->isDryRunMode()) {
            $this->log('Dry run mode is on. Live content is displayed, no cached output.');
        }

        $this->log(__METHOD__ . ' uri:' . $_SERVER['REQUEST_URI']
            . '; script:' . $_SERVER['SCRIPT_NAME'] . '; query:' . $_SERVER['QUERY_STRING'] . '.');

        // Search for valid cache item for current request
        $item = $this->getCurrentItem();

        if ($item) {
            // Display cache item if found
            // If cache file not found or not valid, init() continues with cache generation(storePageContent())
            $this->displayItem($item);
        }

        $this->log(__METHOD__ . ' Cache item not found for hash ' . $this->getCurrentKey());

        /**
         * Cache item not found. Fetch content, save it, display it on this run.
         */
        ob_start([$this, 'storePageHandler']);
    }

    /**
     * Invoked by ob_start() to store page content
     *
     * @param string $content from ob_start
     * @return string Contents of the page
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function storePageHandler($content)
    {
        try {
            return $this->storePageContent($content);
        } catch (\Throwable $t) {
            $this->log('', $t);
        } catch (\Exception $e) {
            $this->log('', $e);
        }

        return $content;
    }

    /**
     * Get Default Cache Adapter
     *
     * @return FileSystemCacheAdapter
     * @throws PageCacheException
     */
    private function getDefaultCacheAdapter()
    {
        return new FileSystemCacheAdapter(
            $this->config->getCachePath(),
            $this->config->getFileLock(),
            $this->config->getMinCacheFileSize()
        );
    }

    /**
     * Get current key
     *
     * @return string
     */
    public function getCurrentKey()
    {
        if (!$this->currentKey) {
            $this->currentKey = $this->getStrategy()->strategy();
        }

        return $this->currentKey;
    }

    /**
     * Display cache item, send headers if necessary.
     *
     * @param \PageCache\Storage\CacheItemInterface $item
     */
    private function displayItem(CacheItemInterface $item)
    {
        $isDryRun = $this->config()->isDryRunMode();

        $this->httpHeaders
            ->setLastModified($item->getLastModified())
            ->setExpires($item->getExpiresAt())
            ->setETag($item->getETagString());

        // Decide if sending headers from Config
        // Send headers (if not disabled) and process If-Modified-Since header
        if ($this->config->isSendHeaders()) {
            $logHeaders = sprintf(
                '%s: %s, %s: %s,%s: %s',
                HttpHeaders::HEADER_LAST_MODIFIED,
                $item->getLastModified()->format(HttpHeaders::DATE_FORMAT_CREATE),
                HttpHeaders::HEADER_EXPIRES,
                $item->getExpiresAt()->format(HttpHeaders::DATE_FORMAT_CREATE),
                HttpHeaders::HEADER_ETAG,
                $item->getETagString()
            );
            $this->log(__METHOD__ . ' uri:' . $_SERVER['REQUEST_URI']
                . '; Headers {' . $logHeaders . '}');

            if (!$isDryRun) {
                $this->httpHeaders->send();
                $this->log(
                    __METHOD__ . ' Headers sent: ' . PHP_EOL . implode(
                        PHP_EOL,
                        $this->httpHeaders->getSentHeaders()
                    )
                );
            }

            // Check if conditions for the If-Modified-Since header are met
            if ($this->httpHeaders->checkIfNotModified()) {
                if (!$isDryRun) {
                    $this->httpHeaders->sendNotModifiedHeader();
                    $this->log(__METHOD__ . ' 304 Not Modified header was set. Exiting w/o content.');
                    $this->log(__METHOD__ . ' Response status: ' . http_response_code());
                    exit();
                }
                $this->log(__METHOD__ . ' 304 Not Modified header was set. Not exiting w/o content - Dry Mode.');
            }
        }

        // Show cached content
        $this->log(__METHOD__ . ' Cache item found: ' . $this->getCurrentKey());
        $this->log(__METHOD__ . ' Response status: ' . http_response_code());

        if (!$isDryRun) {
            // Echo content and stop execution
            echo $item->getContent();
            exit();
        }
    }

    /**
     * Write page to cache, and display it.
     * When write is unsuccessful, string content is returned.
     *
     * @param string $content String from ob_start
     *
     * @return string Page content
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function storePageContent($content)
    {
        $key = $this->getCurrentKey();
        $item = new CacheItem($key);

        // When enabled we store original header values with the item
        $isHeadersForwardingEnabled = $this->config->isSendHeaders() && $this->config->isForwardHeaders();

        $this->log(__METHOD__ . ' Header forwarding is ' . ($isHeadersForwardingEnabled ? 'enabled' : 'disabled'));

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
        if (!empty($expiresAt)) {
            $item->setExpiresAt($expiresAt);
        }

        // Set current time as last modified if none provided
        if (empty($lastModified)) {
            $lastModified = new DateTime();
        }

        /**
         * Set ETag from from last modified time if none provided
         *
         * @link https://github.com/mmamedov/page-cache/issues/1#issuecomment-273875002
         */
        if (empty($eTagString)) {
            $eTagString = md5($lastModified->getTimestamp());
        }

        $item
            ->setContent($content)
            ->setLastModified($lastModified)
            ->setETagString($eTagString);

        $this->getItemStorage()->set($item);

        $logHeaders = sprintf(
            '%s: %s, %s: %s',
            HttpHeaders::HEADER_LAST_MODIFIED,
            $item->getLastModified()->format(HttpHeaders::DATE_FORMAT_CREATE),
            HttpHeaders::HEADER_ETAG,
            $item->getETagString()
        );

        $this->log(__METHOD__ . ' Data stored for key ' . $key . '; Headers {' . $logHeaders . '}');

        // Return page content
        return $content;
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
     * Clear cache for provided page (or current page if none given)
     *
     * @param \PageCache\Storage\CacheItemInterface|null $item
     *
     * @throws \PageCache\PageCacheException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function clearPageCache(CacheItemInterface $item = null)
    {
        // Use current item if not provided in arguments
        if (is_null($item)) {
            $item = $this->getCurrentItem();
        }

        // Current item might have returned null
        if (is_null($item)) {
            throw new PageCacheException(__METHOD__ . ' Page cache item can not be detected');
        }

        $this->getItemStorage()->delete($item);
    }

    /**
     * Return current page cache as a string or false on error, if this page was cached before.
     *
     * @return string|false
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getPageCache()
    {
        $key = $this->getCurrentKey();
        $item = $this->getItemStorage()->get($key);

        return $item ? $item->getContent() : false;
    }

    /**
     * Checks if current page is in cache.
     *
     * @param \PageCache\Storage\CacheItemInterface|null $item
     *
     * @return bool Returns true if page has a valid cache file saved, false if not
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function isCached(CacheItemInterface $item = null)
    {
        if (!$item) {
            $key = $this->getCurrentKey();
            $item = $this->getItemStorage()->get($key);
        }

        return $item ? true : false;
    }

    /**
     * Create and return cache storage instance.
     * If cache adapter was not set previously, sets Default cache adapter(FileSystem)
     *
     * @return \PageCache\Storage\CacheItemStorage
     */
    private function getItemStorage()
    {
        // Hack for weird initialization logic
        if (!$this->itemStorage) {
            $this->itemStorage = new CacheItemStorage(
                $this->cacheAdapter ?: $this->getDefaultCacheAdapter(),
                $this->config->getCacheExpirationInSeconds()
            );
        }

        return $this->itemStorage;
    }

    /**
     * Detect and return current page cached item (or null if current page was not cached yet)
     *
     * @return \PageCache\Storage\CacheItemInterface|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getCurrentItem()
    {
        // Hack for weird initialization logic
        if (!$this->currentItem) {
            $key = $this->getCurrentKey();
            $this->currentItem = $this->getItemStorage()->get($key);
        }

        return $this->currentItem;
    }

    /**
     * Set logger
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Delete everything from cache.
     */
    public function clearAllCache()
    {
        $this->log(__METHOD__ . ' Clearing all cache.');
        $this->getItemStorage()->clear();
    }

    /**
     * Log message using PSR Logger, or error_log.
     * Works only when logging was enabled and log file path was define.
     * Attempts to create default logger if no logger exists.
     *
     * @param string $msg Message
     * @param null|\Exception $exception
     *
     * @return bool true when logged, false when didn't log
     */
    private function log($msg, $exception = null)
    {
        if (!$this->config->isEnableLog()) {
            return false;
        }

        if (empty($this->logger) && !empty($this->config->getLogFilePath())) {
            $this->logger = new DefaultLogger($this->config->getLogFilePath());
        }

        if ($exception) {
            $this->logger->log(LogLevel::ALERT, $msg, ['exception' => $exception]);
        } else {
            $this->logger->log(LogLevel::DEBUG, $msg, []);
        }

        return true;
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
     * For changing config values
     *
     * @return Config
     */
    public function config()
    {
        return $this->config;
    }
}
