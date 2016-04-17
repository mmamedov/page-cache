<?php

/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Strategy;

use PageCache\StrategyInterface;

/**
 * DefaultStrategy is a default cache file naming strategy, based on incoming url
 *
 * @package PageCache
 */
class DefaultStrategy implements StrategyInterface
{
    public function strategy()
    {
        return md5($_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING']);
    }
}