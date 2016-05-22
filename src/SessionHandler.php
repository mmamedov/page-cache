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
 *
 * SessionHandler is responsible for caching based on $_SESSION.
 * For different session values different cache files created. This behaviour is disabled by default.
 *
 */
class SessionHandler
{
    /**
     * Session support enabled/disabled
     * @var bool
     */
    private static $status = false;

    /**
     * Session keys to exclude
     *
     * @var null|array
     */
    private static $exclude_keys = null;

    /**
     * Serialize session. Exclude $_SESSION[key], if key is defined in excludeKeys()
     *
     * @return string
     */
    public static function process()
    {
        $out = null;

        //session handler enabled
        if (self::$status) {
            //get session into array
            $tmp = $_SESSION;

            //remove excluded keys if were set, and if session is set
            if (!empty(self::$exclude_keys) && isset($_SESSION) && !empty($_SESSION)) {
                foreach (self::$exclude_keys as $key) {
                    if (isset($tmp[$key])) {
                        unset($tmp[$key]);
                    }
                }
            }

            $out = serialize($tmp);
        }

        return $out;
    }

    /**
     * Exclude keys from session
     *
     * @param array $sess_keys
     */
    public static function excludeKeys(array $sess_keys)
    {
        self::$exclude_keys = $sess_keys;
    }

    /**
     * Enable or disable Session support
     *
     * @param bool $status
     */
    public static function setStatus($status)
    {
        if ($status === true || $status === false) {
            self::$status = $status;
        }
    }

    public static function getExcludeKeys()
    {
        return self::$exclude_keys;
    }

    /**
     * Enable session support
     */
    public static function enable()
    {
        self::$status = true;
    }

    /**
     * Disable session support
     */
    public static function disable()
    {
        self::$status = false;
    }

    public static function getStatus()
    {
        return self::$status;
    }

    public static function reset()
    {
        self::$exclude_keys=null;
        self::$status=false;
    }
}
