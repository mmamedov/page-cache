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

namespace PageCache;

use Psr\SimpleCache\CacheInterface;
use DateTime;

/**
 * Class Storage
 * Wrapper for PSR-16 SimpleCache adapter
 *
 * @package PageCache
 */
class Storage
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
     * @return \PageCache\CacheItemInterface|null
     */
    public function get($key)
    {
        /** @var \PageCache\CacheItemInterface $item */
        $item = $this->adapter->get($key);

        if (!$item) {
            return null;
        }

        // Generate expiration time
        $this->randomizeExpirationTime($item);

        // Cache expired?
        if ($this->isExpired($item)) {
            return null;
        }

        return $item;
    }

    public function set(CacheItemInterface $item)
    {
        $key = $item->getKey();

        $this->adapter->set($key, $item);
    }

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
     * @param \PageCache\CacheItemInterface $item
     * @param \DateTime|null                $time
     *
     * @return bool
     */
    private function isExpired(CacheItemInterface $item, DateTime $time = null)
    {
        if (!$time) {
            $time = new DateTime();
        }

        return ($time > $item->getExpiresAt());
    }

    /**
     * Calculate and returns item's expiration time.
     *
     * Cache expiration is cache_expire seconds +/- a random value of seconds, from -6 to 6.
     *
     * So although expiration is set for example 200 seconds, it is not guaranteed that it will expire in exactly
     * that many seconds. It could expire at 200 seconds, but also could expire in 206 seconds, or 194 seconds, or
     * anywhere in between 206 and 194 seconds. This is done to better deal with cache stampede, and improve cache
     * hit rate.
     *
     * @param \PageCache\CacheItemInterface $item
     */
    private function randomizeExpirationTime(CacheItemInterface $item)
    {
        // Get expires time (if previously set by headers forwarding)
        $expiresAtTimestamp = $item->getExpiresAt() ? $item->getExpiresAt()->getTimestamp() : null;

        // Generate expires time from creation date and default interval
        if (!$expiresAtTimestamp) {
            $expiresAtTimestamp = $item->getCreatedAt()->getTimestamp() + $this->cacheExpiresIn;
        }

        // Slightly random offset
        $offset = log10(mt_rand(10, 1000)) * mt_rand(-2, 2);

        $newValue = new DateTime;
        $newValue->setTimestamp($expiresAtTimestamp + $offset);

        $item->setExpiresAt($newValue);
    }
}
