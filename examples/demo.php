<?php

/**
 *
 * This demo demonstrates basic caching functionality of PageCache.
 *
 */

require_once __DIR__.'/../vendor/autoload.php';

$cache = new PageCache\PageCache();
$cache->setPath(__DIR__.'/cache/');
$cache->init();

?>
<html>
<body>
<h1>Example #1</h1>
<h3 style="color: red">This is a basic demo PageCache page that is going to be cached.</h3>
<h3>Default cache expiration time for this page is 20 minutes. You can change this value in your <i>conf.php</i>
    and passing its file path to PageCache constructor, or by calling <i>setExpiration()</i> method.</h3>
<h3>This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br><br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 20 minutes.</h4>
</body>
</html>