<?php
namespace PageCache;

use DateTime;

/**
 * Describes data stored in cache item
 */
interface CacheItemInterface
{
    /**
     * @return string
     */
    public function getKey();

    /**
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * @return \DateTime
     */
    public function getLastModified();

    /**
     * @param \DateTime $time
     *
     * @return $this
     */
    public function setLastModified(DateTime $time);

    /**
     * @return \DateTime
     */
    public function getExpiresAt();

    /**
     * @param \DateTime $time
     *
     * @return $this
     */
    public function setExpiresAt(DateTime $time);

    /**
     * @return string
     */
    public function getETagString();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setETagString($value);

    /**
     * @return string
     */
    public function getContent();

    /**
     * @param string $data
     *
     * @return $this
     */
    public function setContent($data);
}
