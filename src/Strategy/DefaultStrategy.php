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
 * DefaultStrategy is a default cache file naming strategy, based on incoming url and session(or not)
 *
 * @package PageCache
 */
class DefaultStrategy implements StrategyInterface
{
    private $session_support;

    /**
     * @param $session_support boolean set to true for session support
     * @return string md5
     */
    public function strategy($session_support = false)
    {
        //when session support is enabled add that to file name
        $this->session_support = $session_support;
        $session_str = $this->process_session();

        return md5($_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING'] . $session_str);
    }

    public function process_session()
    {
        $out = null;

        if ($this->session_support) {
            $out = print_r($_SESSION, true);
        }

        return $out;

    }
}