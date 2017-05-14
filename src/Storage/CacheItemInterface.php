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
