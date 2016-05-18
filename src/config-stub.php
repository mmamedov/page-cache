<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * Example configuration file
 *
 * Sample config array with all possible values.
 * Copy this configuration file to your local location and edit values that you need.
 *
 * You do not need to copy this config file and use it, you could set up all parameters directly inside code.
 * If you have caching enabled on several pages, and do not want to repeat cache settings, then config file is for you.
 *
 * NOTE: Parameters defined here in $config array are used by all pages using PageCache within you application.
 *       You can override any of these settings in your code.
 *
 */
$config = array(

    /**
     * Minimum cache file size.
     * Generated cache files less than this many bytes, are considered invalid and are regenerated
     * Default 10
     */
    'min_cache_file_size' => 10,

    /**
     * Set true to enable logging, not recommended for production use, only for debugging
     * Default: false
     *
     * Effects both internal logger, and any external PSR-3 logger (if any) activated via setLogger() method
     */
    'enable_log' => false,

    /**
     * Internal log file location, enable_log must be true for loging to work
     * When external loger is provided via setLogger(), internal logging is disabled.
     */
    'log_file_path' => __DIR__ . '/log/cache.log',

    /**
     * Current page's cache expiration in seconds.
     * Default: 20 minutes, 1200 seconds.
     */
    'expiration' => 1200,

    /**
     * Cache directory location (mind the trailing slash "/").
     * Cache files are saved here.
     */
    'cache_path' => __DIR__ . '/tmp/cache/',

    /**
     * Use session support, if you have a login area or similar.
     * When page content changes according to some Session value, although URL remains the same.
     * Disabled by default.
     */
    'use_session' => false,

    /**
     * Exclude $_SESSION key(s) from caching strategies. Pass session name as keys to the array.
     *
     * When to use: Your application changes $_SESSION['count'] variable, but that doesn't reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *              Example: 'session_exclude_keys'=>array('count')
     */
    'session_exclude_keys' => array(),

    /**
     *
     * Locking mechanism to use when writing cache files. Default is LOCK_EX | LOCK_NB, which locks for
     * exclusive write while being non-blocking. Set whatever you want.
     * Read for details (http://php.net/manual/en/function.flock.php)
     *
     * Set file_lock = false to disable file locking.
     */
    'file_lock' => LOCK_EX | LOCK_NB

);
