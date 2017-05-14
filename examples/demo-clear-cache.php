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

use PageCache\PageCache;

$cache = new PageCache();
$cache->config()->setCachePath(__DIR__ . '/cache/');

//Clear cache.
//Note that setPath() must be called prior to this, or 'cache_path' config parameter must be configured.
$cache->clearAllCache();
$cache->init();

?>
<html>
<body>
<h1>Example Clear Cache</h1>
<h3 style="color: red">Notice this page is not being cached, because clearCache() is being called before init()</h3>
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