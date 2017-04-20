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

/**
 * Interface for cache file naming strategy
 *
 * @package PageCache
 */
interface StrategyInterface
{
    /**
     * Returns cache data key
     *
     * @return string Cache data key
     */
    public function strategy();
}
