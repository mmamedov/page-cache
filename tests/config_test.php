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
 * Testing parameters for PageCache
 *
 * If any of the parameters are changed, please change test cases (esp. ConfigTest)
 */
return [

    //generated cache files less than this many bytes, are considered invalid and are regenerated
    //adjust accordingly
    'min_cache_file_size' => 1,

    // set true to enable loging, not recommended for production use, only for debugging
    'enable_log' => false,

    //current page's cache expiration in seconds. Set to 10 minutes:
    'cache_expiration_in_seconds' => 10 * 60,

    //log file location, enable_log must be true for loging to work
    'log_file_path' => __DIR__ . '/tmp',

    //cache directory location (mind the trailing slash "/")
    'cache_path' => __DIR__ . '/tmp/cache/',

    /**
     * Use session or not
     */
    'use_session' => false,

    /**
     * Exclude $_SESSION key(s) from caching strategies. Pass session name as keys to the array.
     *
     *
     * When to use: Your application changes $_SESSION['count'] variable, but that doesn't reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *              Example: 'session_exclude_keys'=>array('count')
     */
    'session_exclude_keys' => [],

    /**
     *
     * Locking mechanism to use when writing cache files. Default is LOCK_EX | LOCK_NB, which locks for
     * exclusive write while being non-blocking. Set whatever you want.
     * Read for details (http://php.net/manual/en/function.flock.php)
     *
     * Set file_lock = false to disable file locking.
     */
    'file_lock' => LOCK_EX | LOCK_NB,

    //Send HTTP headers
    'send_headers' => false,

    'forward_headers' => false,
];
