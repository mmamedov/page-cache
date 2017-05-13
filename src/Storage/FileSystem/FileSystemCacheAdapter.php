<?php
/**
 * This file is part of the PageCache package.
 *
 * @author    Denis Terekhov <i.am@spotman.ru>
 * @package   PageCache
 * @copyright 2017
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Storage\FileSystem;

use DateInterval;
use PageCache\CacheAdapterException;
use PageCache\PageCacheException;
use Psr\SimpleCache\CacheInterface;

/**
 * Class FileSystemPsrCacheAdapter
 *
 * @package PageCache
 */
class FileSystemCacheAdapter implements CacheInterface
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var int
     */
    protected $fileLock;

    /**
     * @var int
     */
    protected $minFileSize;

    /**
     * @var \PageCache\Storage\FileSystem\HashDirectory
     */
    protected $hashDirectory;

    /**
     * FileSystemPsrCacheAdapter constructor.
     *
     * @param string $path
     * @param int    $fileLock
     * @param int    $minFileSize
     */
    public function __construct($path, $fileLock, $minFileSize)
    {
        $this->path        = $path;
        $this->fileLock    = $fileLock;
        $this->minFileSize = $minFileSize;

        $this->hashDirectory = new HashDirectory();
        $this->hashDirectory->setDir($this->path);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        $path = $this->makeFullPath($key);

        return $this->isValidFile($path);
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        $path = $this->makeFullPath($key);

        if (!$this->isValidFile($path)) {
            return $default;
        }

        /** @noinspection PhpIncludeInspection */
        return include $path;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                $key   The key of the item to store.
     * @param mixed                 $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *                                     for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
     * @throws \Exception If the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        $data = $this->prepareItemData($value);

        $path    = $this->makeFullPath($key);
        $storage = new FileSystem($data);

        try {
            $storage->setFileLock($this->fileLock);
            $storage->setFilePath($path);
        } catch (\Throwable $t) {
            throw new CacheAdapterException(__METHOD__.' FileSystem Exception', 0, $t);
        } catch (\Exception $e) {
            throw new CacheAdapterException(__METHOD__.' FileSystem Exception', 0, $e);
        }

        $result = $storage->writeAttempt();

        if ($result !== FileSystem::OK) {
            throw new CacheAdapterException(__METHOD__.' FileSystem writeAttempt not an OK result: '.$result);
        }

        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        $path = $this->makeFullPath($key);

        /**
         * Cache file name is now available, check if cache file exists.
         * If init() wasn't called on this page before, there won't be any cache saved, so we check with file_exists.
         */
        if (file_exists($path) && is_file($path)) {
            unlink($path);

            return true;
        }

        return false;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        return $this->hashDirectory->clearDirectory($this->path);
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        throw new PageCacheException(__METHOD__.' not implemented');
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable              $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        throw new PageCacheException(__METHOD__.' not implemented');
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        throw new PageCacheException(__METHOD__.' not implemented');
    }

    private function makeFullPath($key)
    {
        return $this->hashDirectory->getFullPath($key);
    }

    private function isValidFile($path)
    {
        return (file_exists($path) && filesize($path) >= $this->minFileSize);
    }

    private function prepareItemData($data)
    {
        return '<?php return unserialize('.var_export(serialize($data), true).');';
    }
}
