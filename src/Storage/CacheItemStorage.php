<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Denis Terekhov <i.am@spotman.ru>
 * @package PageCache
 * @copyright 2017
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Storage;

use Psr\SimpleCache\CacheInterface;
use DateTime;

/**
 * Class CacheItemStorage
 * Wrapper for PSR-16 SimpleCache adapter
 *
 * @package PageCache
 */
class CacheItemStorage
{
    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $adapter;

    /**
     * @var int
     */
    protected $cacheExpiresIn;

    /**
     * This a wrapper for PSR-16 adapter
     *
     * @param \Psr\SimpleCache\CacheInterface $adapter
     * @param int                             $cacheExpiresIn
     */
    public function __construct(CacheInterface $adapter, $cacheExpiresIn)
    {
        $this->adapter        = $adapter;
        $this->cacheExpiresIn = $cacheExpiresIn;
    }

    /**
     * @param string $key
     *
     * @return \PageCache\Storage\CacheItemInterface|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get($key)
    {
        /** @var \PageCache\Storage\CacheItemInterface $item */
        $item = $this->adapter->get($key);

        if (!$item) {
            return null;
        }

        $this->randomizeExpirationTime($item);

        // Cache expired?
        if ($this->isExpired($item)) {
            return null;
        }

        return $item;
    }

    /**
     * @param CacheItemInterface $item
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set(CacheItemInterface $item)
    {
        // Add ttl for buggy adapters (double time for correct cache stampede preventing algorithm)
        $this->adapter->set($item->getKey(), $item, $this->cacheExpiresIn * 2);
    }

    /**
     * @param CacheItemInterface $item
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete(CacheItemInterface $item)
    {
        $this->adapter->delete($item->getKey());
    }

    /**
     * Wipes clean the entire cache.
     */
    public function clear()
    {
        $this->adapter->clear();
    }

    /**
     * @param \PageCache\Storage\CacheItemInterface $item
     * @param \DateTime|null                $time
     *
     * @return bool
     */
    private function isExpired(CacheItemInterface $item, DateTime $time = null)
    {
        $time = $time ?: new DateTime();
        return ($time > $item->getExpiresAt());
    }

    /**
     * Calculate and returns item's expiration time.
     *
     * Cache expiration is cacheExpire seconds +/- a random value of seconds, from -6 to 6.
     *
     * So although expiration is set for example 200 seconds, it is not guaranteed that it will expire in exactly
     * that many seconds. It could expire at 200 seconds, but also could expire in 206 seconds, or 194 seconds, or
     * anywhere in between 206 and 194 seconds. This is done to better deal with cache stampede, and improve cache
     * hit rate.
     *
     * @param \PageCache\Storage\CacheItemInterface $item
     */
    private function randomizeExpirationTime(CacheItemInterface $item)
    {
        // Get expires time (if previously set by headers forwarding)
        $expiresAtTimestamp = $item->getExpiresAt() ? $item->getExpiresAt()->getTimestamp() : null;

        // Generate expires time from creation date and default interval
        $expiresAtTimestamp = $expiresAtTimestamp ?: ($item->getCreatedAt()->getTimestamp() + $this->cacheExpiresIn);

        // Slightly random offset
        $offset = log10(mt_rand(10, 1000)) * mt_rand(-2, 2);
        $item->setExpiresAt((new DateTime())->setTimestamp($expiresAtTimestamp + $offset));
    }
}
