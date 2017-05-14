<?php

/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2016
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * This demo demonstrates basic caching functionality of PageCache.
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * When not specified using a config file or by calling methods, the following parameters are set automatically:
 *
 * cache_expire = 1200 seconds
 * min_cache_file_size = 10
 * file_lock = LOCK_EX | LOCK_NB
 * use_session = false
 * send_headers = false
 * forward_headers = false
 * enable_log = false
 * .. For full list of default values check Config class file
 *
 */
use PageCache\PageCache;

$cache = new PageCache();
$cache->config()->setCachePath(__DIR__ . '/cache/')
                ->setSendHeaders(true);
$cache->init();
?>
<html>
<body>
<h1>Example #1</h1>
<h3 style="color: red">This is a basic demo PageCache page that is going to be cached.</h3>
<h3>Default cache expiration time for this page is 20 minutes. You can change this value in your <i>conf.php</i>
    and passing its file path to PageCache constructor, or by calling <i>setExpiration()</i> method.
    <span style="color: green;">Refresh browser to see changes.</span></h3>
<h3>This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br><br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 20 minutes.</h4>
</body>
</html>