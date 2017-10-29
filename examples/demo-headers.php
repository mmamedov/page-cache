<?php

/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2017
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * This demo demonstrates how HTTP cache related headers are sent in PageCache
 *
 */

/**
 * To test concept, once files are cached uncomment 2 lines below.
 * Nothing at all will be sent to the browser except the 304 Header, and you should still see page contents.
 */
//header('HTTP/1.1 304 Not Modified');
//exit();


require_once __DIR__ . '/../vendor/autoload.php';

use PageCache\PageCache;

$cache = new PageCache();
$cache->config()->setCachePath(__DIR__ . '/cache/')
                ->setEnableLog(true)
                ->setLogFilePath(__DIR__ . '/log/cache.log')
                ->setCacheExpirationInSeconds(600)
//    Uncomment to enable Dry Run mode
//                ->setDryRunMode(false)
                ->setSendHeaders(true);
// Uncomment to delete this page from cache
//$cache->clearPageCache();
$cache->init();

?>
<html>
<body>
<h1>Example with HTTP caching headers</h1>
<h3 style="color: red">This is a demo PageCache page that is going to be cached.</h3>
<h3 style="color:green">Notice that first call to this URL resulted in HTTP Response code <b>200</b>. Consequent calls,
    until page expiration, will result in <b>304 Not Modified<b>. When 304 is being returned, no content is
    retrieved from the server, which makes your application load super fast - cached content comes
    from web browser.</h3>
<h3>Default cache expiration time for this page is 1 minutes. You can change this value in your <i>conf.php</i>
    and passing its file path to PageCache constructor, or by calling <i>setExpiration()</i> method.
    <span style="color: green;">Refresh browser to see changes.</span></h3>
<h3>This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br><br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 1 minute.</h4>
</body>
</html>