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
     * Sets cache file name
     *
     * @param $session_support boolean set to true if session pages are cached
     * @return mixed string cache file name
     */
    public function strategy($session_support);

    /**
     * When session support is enabled, processes session variables.
     *
     * @return mixed string session vars
     */
    public function process_session();
}