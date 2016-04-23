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
 *
 * MobileStrategy uses "serbanghita/Mobile-Detect" package and provides a different cached version for mobile devices.
 *
 * Use enableSession() to enable session support
 *
 * If you are displaying a different version of your page for mobile devices use:
 *      $cache->setStrategy(new \PageCache\MobileStrategy() );
 *
 */
class MobileStrategy implements StrategyInterface
{
    private $MobileDetect;
    private $session_support;


    public function __construct()
    {
        $this->MobileDetect = new \Mobile_Detect;
    }

    /**
     * Sets a "-mob" ending to cache files for visitors coming from mobile devices (phones but not tablets)
     *
     * @return string file name
     */
    public function strategy()
    {
        $ends = '';
        if ($this->currentMobile()) {
            $ends = '-mob';
        }

        //when session support is enabled add that to file name
        $session_str = SessionHandler::process();

        return md5( $_SERVER['REQUEST_URI'] . $_SERVER['SCRIPT_NAME'] . $_SERVER['QUERY_STRING']. $session_str )  . $ends;
    }

    /**
     * Whether curernt page was accessed from a mobile phone
     *
     * @return bool true for phones, else false
     */
    private function currentMobile()
    {
        if (empty($this->MobileDetect) || !($this->MobileDetect instanceof \Mobile_Detect)) {
            return false;
        }

        //for phones only, not tablets
        //create your own Strategy if needed, and change this functionality
        if ($this->MobileDetect->isMobile() && !$this->MobileDetect->isTablet()) {
            return true;
        } else {
            return false;
        }
    }

}