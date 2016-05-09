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

use PageCache\SessionHandler;
use PageCache\StrategyInterface;

/**
 * DefaultStrategy is a default cache file naming strategy, based on incoming url and session(or not)
 *
 * @package PageCache
 */
class DefaultStrategy implements StrategyInterface
{
    /**
     * @return string md5
     */
    public function strategy()
    {
        //when session support is enabled add that to file name
        $session_str = SessionHandler::process();

        return md5( $_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING'] . $session_str );
    }

}