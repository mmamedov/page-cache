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
 * Use enableSession() to enable session support
 *
 * If you are displaying a different version of your page for mobile devices use:
 * $cache->setStrategy(new \PageCache\MobileStrategy() );
 *
 */
class MobileStrategy implements StrategyInterface
{
    /**
     * Mobile Detect instance
     *
     * @var \Mobile_Detect|null
     */
    private $MobileDetect;

    /**
     * MobileStrategy constructor.
     * $mobileDetect object can be passed as a parameter. Useful for testing.
     *
     * @param \Mobile_Detect|null $mobileDetect
     */
    public function __construct(\Mobile_Detect $mobileDetect = null)
    {
        $this->MobileDetect = $mobileDetect ?: new \Mobile_Detect;
    }

    /**
     * Generate cache file name
     * Sets a "-mob" ending to cache files for visitors coming from mobile devices (phones but not tablets)
     *
     * @return string file name
     */
    public function strategy()
    {
        $ends = $this->currentMobile() ? '-mob' : '';

        //when session support is enabled add that to file name
        $session_str = SessionHandler::process();

        $uri = empty($_SERVER['REQUEST_URI']) ? 'uri' : $_SERVER['REQUEST_URI'];
        $query = empty($_SERVER['QUERY_STRING']) ? 'query' : $_SERVER['QUERY_STRING'];
        
        return md5($uri . $_SERVER['SCRIPT_NAME'] . $query . $session_str) . $ends;
    }

    /**
     * Whether current page was accessed from a mobile phone
     *
     * @return bool true for phones, else false
     */
    private function currentMobile()
    {
        if (!$this->MobileDetect || !($this->MobileDetect instanceof \Mobile_Detect)) {
            return false;
        }

        //for phones only, not tablets
        //create your own Strategy if needed, and change this functionality
        return $this->MobileDetect->isMobile() && !$this->MobileDetect->isTablet();
    }
}
