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
 * SessionHandler is responsible for caching based on $_SESSION.
 * For different session values different cache files created. This behaviour is disabled by default.
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
            $tmp = isset($_SESSION) ? $_SESSION : [];

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
     * Exclude $_SESSION key(s) from caching strategies.
     *
     * When to use: i.e. Your application changes $_SESSION['count'] variable, but that does not reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *
     * @param array $keys $_SESSION keys to exclude from caching strategies
     */
    public static function excludeKeys(array $keys)
    {
        self::$exclude_keys = $keys;
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

    /**
     * Get excluded $_SESSION keys
     *
     * @return array|null
     */
    public static function getExcludeKeys()
    {
        return self::$exclude_keys;
    }

    /**
     * Enable session support. Use sessions when caching page.
     * For the same URL session enabled page might be displayed differently, when for example user has logged in.
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
