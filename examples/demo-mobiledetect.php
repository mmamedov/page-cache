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
 * This demo demonstrates Mobile_Detect integration into PageCache.
 * You can implement your own strategies and pass them into setStrategy().
 * Examine code of Strateg/DefaultStrategy() to give you idea how to write one.
 *
 *
 * !!! IMPORTANT: To use this demo you need to composer install require_dev mobiledetect/mobiledetectlib:
 *                composer require mobiledetect/mobiledetectlib
 *                or whichever other way you prefer. mobiledetect is being suggested during PageCage install.
 *
 */

/**
 * Composer autoload, or use any other means
 */
require_once __DIR__ . '/../vendor/autoload.php';

use PageCache\PageCache;
use PageCache\Strategy\MobileStrategy;

/**
 * PageCache setup
 */
$config_file = __DIR__ . '/config.php';
$cache = new PageCache($config_file);
$cache->setStrategy(new MobileStrategy());
$cache->config()->setEnableLog(true);
//Enable session support if needed, check demos and README for details
//uncomment for session support
//$cache->config()->setUseSession(true);
$cache->init();

/**
 * Mobile detect helper function for detecting mobile devices.
 * Tablets are excluded on purpose here, suit your own needs.
 *
 * Mobile_detect project URL: http://www.mobiletedect.net
 *                          : https://packagist.org/packages/mobiledetect/mobiledetectlib
 *
 */
function isMobileDevice()
{
    $mobileDetect = new \Mobile_Detect();

    /**
     * Check for mobile devices, that are not tables. We want phones only.
     * If you need ALL mobile devices use this:   if($mobileDetect->isMobile())
     *
     */
    return $mobileDetect->isMobile() && !$mobileDetect->isTablet();
}
?>
<html>
<body>
<h1>Example #3</h1>
<h3 style="color: red">This is a basic MobileStrategy() PageCache page that is going to be cached, uses optional
    Mobile_Detect package</h3>
<p style="border:1px solid #ccc;">
    Visit this page with a desktop browser on your computer, and then using a mobile phone.<br/>
    You will notice 2 files inside cache/ directory, one regular cache file and the other same file but with "-mob"
    added to it.
</p>
<?php
/**
 *  Cache for Mobile Phones only
 */
if (isMobileDevice()) {
    ?>
    <h3>This section will be displayed on mobile phones only</h3>
    <?php
} else {
    /**
     * Cache the desktop version
     */
    ?>
    <h3>This section will be displayed on desktop devices, but not on mobile phones</h3>

    <?php
} ?>

<h3>This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br><br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 10 minutes, as per conf.php</h4>
</body>
</html>