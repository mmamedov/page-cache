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

    //generated cache files less than this many bytes, are considered invalid and are regenerated
    //default 10
    'min_cache_file_size' => 10,

    // set true to enable loging, not recommended for production use, only for debugging
    //default false
    'enable_log' => false,

    //current page's cache expiration in seconds
    //default 20 minutes
    'expiration' => 20 * 60,

    //log file location, enable_log must be true for loging to work
    'log_file_path' => __DIR__ . '/log/cache.log',

    //cache directory location (mind the trailing slash "/")
    'cache_path' => __DIR__ . '/tmp/cache/',

    //Use session support, if you have a login area or similar, when page content changes according to some Session value, although URL remains the same
    //disabled by default
    'use_session'=>false

);