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

use DateTime;

/**
 * Cache Item Storage.
 * Contents of the item are stored as a string.
 *
 * Class CacheItem
 * @package PageCache\Storage
 */
class CacheItem implements CacheItemInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var DateTime
     */
    private $lastModified;

    /**
     * @var DateTime
     */
    private $expiresAt;

    /**
     * @var string
     */
    private $eTagString;

    /**
     * @var string
     */
    private $content;

    /**
     * CacheItem constructor.
     *
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key       = $key;
        $this->createdAt = new DateTime();
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getLastModified()
    {
        return $this->lastModified;
    }

    /**
     * @param \DateTime $time
     *
     * @return $this
     */
    public function setLastModified(\DateTime $time)
    {
        $this->lastModified = $time;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * @param \DateTime $time
     *
     * @return $this
     */
    public function setExpiresAt(\DateTime $time)
    {
        $this->expiresAt = $time;

        return $this;
    }

    /**
     * @return string
     */
    public function getETagString()
    {
        return $this->eTagString;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setETagString($value)
    {
        $this->eTagString = (string)$value;

        return $this;
    }

    public function __toString()
    {
        return $this->getContent();
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function setContent($data)
    {
        $this->content = (string)$data;

        return $this;
    }
}
