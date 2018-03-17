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
use PageCache\Storage\CacheAdapterException;
use PageCache\Storage\InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

/**
 * Class FileSystemPsrCacheAdapter
 *
 * @package PageCache
 */
class FileSystemCacheAdapter implements CacheInterface
{
    const DEFAULT_TTL = 3600;

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
     * @param int $fileLock
     * @param int $minFileSize
     * @throws \PageCache\PageCacheException
     */
    public function __construct($path, $fileLock, $minFileSize)
    {
        $this->path = $path;
        $this->fileLock = $fileLock;
        $this->minFileSize = $minFileSize;

        $this->hashDirectory = new HashDirectory(null, $this->path);
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
     *       MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        $path = $this->getKeyPath($key);

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
     *       MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        $path = $this->getKeyPath($key);

        if (!$this->isValidFile($path)) {
            return $default;
        }

        /** @noinspection PhpIncludeInspection */
        $data = include $path;

        if (!$data || !\is_array($data) || !isset($data['ttl'], $data['item'])) {
            // Prevent errors on broken files, they would be overwritten later by set() call in client logic
            return $default;
        }

        $ttl = (int)$data['ttl'];

        // 0 TTL means expired (allow negative values like -1)
        if ($ttl < 1) {
            // Do not delete cache files, they would be overwritten later by set() call in client logic
            return $default;
        }

        // Process item TTL
        if (filemtime($path) + $ttl < time()) {
            // Do not delete cache files, they would be overwritten later by set() call in client logic
            return $default;
        }

        return $data['item'];
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *                                     the driver supports TTL then the library may set a default value
     *                                     for it or let the driver take care of that.
     *
     * @return bool True on success
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If the $key string is not a legal value.
     * @throws \PageCache\Storage\CacheAdapterException
     * @throws \Exception
     */
    public function set($key, $value, $ttl = null)
    {
        $ttl = $this->normalizeTtl($ttl);

        if ($ttl < 1) {
            // Item marked as expired, delete it
            return $this->delete($key);
        }

        $path = $this->getKeyPath($key);
        $data = $this->prepareItemData($value, $ttl);
        $storage = new FileSystem($data);

        try {
            $storage->setFileLock($this->fileLock);
            $storage->setFilePath($path);
        } catch (\Throwable $t) {
            throw new CacheAdapterException(__METHOD__ . ' FileSystem Exception', 0, $t);
        } catch (\Exception $e) {
            throw new CacheAdapterException(__METHOD__ . ' FileSystem Exception', 0, $e);
        }

        $result = $storage->writeAttempt();

        if ($result !== FileSystem::OK) {
            throw new CacheAdapterException(__METHOD__ . ' FileSystem writeAttempt not an OK result: ' . $result);
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
        $path = $this->getKeyPath($key);

        /**
         * If init() wasn't called on this page before, there won't be any cache saved, so we check with file_exists.
         */
        if (!\file_exists($path)) {
            // Probably the file already deleted in another thread, PSR requires return value to be "true" in this case
            return true;
        }

        if (is_file($path)) {
            return unlink($path);
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
        if (!$keys || (!\is_array($keys) && !$keys instanceof \Traversable)) {
            throw new InvalidArgumentException('Cache keys must be an array or Traversable');
        }

        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
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
     * @throws \PageCache\Storage\CacheAdapterException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!$values || (!\is_array($values) && !$values instanceof \Traversable)) {
            throw new InvalidArgumentException('Cache values must be an array or Traversable');
        }

        $result = true;

        foreach ($values as $key => $value) {
            if (\is_int($key)) {
                $key = (string)$key;
            }

            $result = $this->set($key, $value, $ttl) && $result;
        }

        return $result;
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
        if (!\is_array($keys) && !$keys instanceof \Traversable) {
            throw new InvalidArgumentException('Cache keys must be an array or Traversable');
        }

        $result = true;

        foreach ($keys as $key) {
            $result = $this->delete($key) && $result;
        }

        return $result;
    }

    /**
     * Check if $path is a valid cache file
     *
     * @param string $path Cache file path
     *
     * @return bool True if valid file, false otherwise
     */
    private function isValidFile($path)
    {
        return (file_exists($path) && filesize($path) >= $this->minFileSize);
    }

    /**
     * Format $data value to be used in cache
     *
     * @param mixed                  $itemData
     *
     * @param int|\DateInterval|null $ttl
     *
     * @return string
     * @throws \PageCache\Storage\InvalidArgumentException
     */
    private function prepareItemData($itemData, $ttl = null)
    {
        $ttl = $this->normalizeTtl($ttl);

        // Integrate TTL into data (it will be checked later in the get() method)
        $fileData = [
            'ttl'  => $ttl,
            'item' => $itemData,
        ];

        return '<?php return unserialize(' . var_export(serialize($fileData), true) . ');';
    }

    /**
     * Make full cache file path for provided key
     *
     * @param string $key
     *
     * @return string
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function getKeyPath($key)
    {
        $this->validateKey($key);

        $file = sha1($key);

        return $this->hashDirectory->getFullPath($file);
    }

    /**
     * Check the key is valid and throw an exception if not
     *
     * @param string|mixed $key
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function validateKey($key)
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given',
                is_object($key) ? get_class($key) : gettype($key)));
        }

        if ($key === '') {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }

        if (strpbrk($key, '{}()/\@:') !== false) {
            throw new InvalidArgumentException(sprintf('Cache key "%s" contains reserved characters {}()/\@:', $key));
        }

        if (preg_match('/[^A-Za-z0-9_\.]+/', $key)) {
            throw new InvalidArgumentException('Invalid PSR SimpleCache key: ' . $key .
                ', must contain letters, numbers, underscores and dots only');
        }
    }

    /**
     * Convert TTL to seconds
     *
     * @param int|DateInterval|null $ttl
     *
     * @return int
     * @throws \PageCache\Storage\InvalidArgumentException
     */
    private function normalizeTtl($ttl)
    {
        if ($ttl === null) {
            return self::DEFAULT_TTL; // Default TTL is one hour
        }

        if (\is_int($ttl)) {
            return $ttl;
        }

        if ($ttl instanceof DateInterval) {
            $currentDateTime = new \DateTimeImmutable();
            $ttlDateTime = $currentDateTime->add($ttl);

            return $ttlDateTime->getTimestamp() - $currentDateTime->getTimestamp();
        }

        throw new InvalidArgumentException('Invalid TTL: ' . print_r($ttl, true));
    }
}
