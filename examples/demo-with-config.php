<?php

/**
 *
 * This demo demonstrates use of global conf.php config file in PageCache.
 *
 * It's useful to have settings defined in one file, to avoid repeating yourself
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

//PageCache configuration in a file
$config_file = __DIR__ . '/conf.php';

//pass config file
$cache = new PageCache\PageCache($config_file);
$cache->init();

?>
<html>
<body>
<h1>Example #2</h1>
<h3>This is a demo PageCache page that is going to be cached.</h3>
<h3 style="color: red">Demo with conf.php configuration file usage.</h3>
<h3>This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br><br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 10 minutes, as per conf.php</h4>
</body>
</html>