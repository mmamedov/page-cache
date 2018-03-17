<?php

/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2018
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * This demo demonstrates usage of default logger
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PageCache\PageCache;

//Setup PageCache
try {
    $cache = new PageCache();
    $cache->config()
        ->setCachePath(__DIR__ . '/cache/')
        //Enable logging: set log file path and enable log
        ->setEnableLog(true)
        ->setLogFilePath(__DIR__.'/log/default_logger.log')
    ;

    //Initiate cache engine
    $cache->init();
} catch (\Exception $e) {
    // Log PageCache error or simply do nothing.
    // In case of PageCache error, page will load normally

    // Do not enable line below in Production. Error output should be used during development only.
    echo '<h3>'.$e->getMessage().'</h3>';
}


?>
<html>
<body>
<h1>PageCache logging with default Logger example</h1>
<h3 style="color: red">This is a demo PageCache page that is going to be cached.</h3>
<h3>Check out examples/log/default_logger.log file for logger entries.</h3>
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