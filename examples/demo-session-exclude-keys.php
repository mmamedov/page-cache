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
 * This demo demonstrates caching functionality of PageCache.
 *
 * We exclude certain session keys from caching logic
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

session_start();
session_destroy();

if (isset($_POST['withsessions'])) {
    if ($_POST['withsessions'] == '1') {
        $_SESSION['excl'] = 1;
        $_SESSION['PageCache'] = 'PHP full page caching';
    } elseif ($_POST['withsessions'] == '2') {
        $_SESSION['excl'] = 555;
        $_SESSION['PageCache'] = 'PHP full page caching';
    }
}

echo '<fieldset style="background-color: #eee; padding: 10px;">var_dump($_SESSION) call, before init(), 
        so this content is not cached. 
        Notice how with each button click below actual session value changes, but since it is excluded from tracking,
        same cache for different session values is generated: ';
var_dump($_SESSION);
echo 'var_dump ends. All below is cached.</fieldset>';

$cache = new PageCache\PageCache(__DIR__ . '/config.php');
$cache->config()->setUseSession(true)
                // Exclude $_SESSION['exc'] from cache strategy.
                // Comment line below, and cached version for each 'excl' session variable
                //   will be saved in a different cache file
                ->setSessionExcludeKeys(array('excl'));

//init
$cache->init();


?>
<html xmlns="http://www.w3.org/1999/html">
<head>
    <style type="text/css">
        button {
            margin: 0;
            padding: 10px;
            font-weight: bold;
        }

        fieldset {
            margin-bottom: 10px;
            background-color: #eee;
        }

        form {
            float: left;
            margin: 0;
            margin-right: 20px;
            padding: 20px;
        }
    </style>
</head>
<body>
<h1>Demo using Session Support + Exclude Session Keys</h1>
Click on the links below to see how PageCache works with session exclude. Although page URL doesn't change, PageCache is
able to cache 2 different version of this page based on Session variables.
Session key 'exc' changes, but PageCache ignores it and produces only a single cached page. If you don't call
excludeKeys(), 2 versions of the page will be generated. <br>
<br/>Whichever button you press first, it will be cached, while other won't - because 'excl' parameter doesn't effect
cache. <br/>But you will still get 2 caches of this page because $_SESSION['PageCache'] is being set, and this change is
being recorded.
<br/><br>
<fieldset>
    <form>
        <a href="demo-session-exclude-keys.php">Main</a>
    </form>
    <form method="post" action="">
        <input type="hidden" value="1" name="withsessions">
        <button type="submit">Set $_SESSION['excl']= 1</button>
    </form>
    <form method="post" action="">
        <input type="hidden" value="2" name="withsessions">
        <button type="submit">Set $_SESSION['excl'] = 555</button>
    </form>

    <br class="clear"/><br/>
    <code>PageCache call demo: $cache->config()->setSessionExcludeKeys(array('excl'));</code>
</fieldset>


<?php
/**
 * Print session data if present
 */
if (isset($_SESSION['excl'])) {
    var_dump($_SESSION);
}

echo '<br><b>Stored under cache key: </b><fieldset>'
    . ($cache->getCurrentKey())
    . '</fieldset>';
?>

<br>
<h3 style="color: red">This is a Session Exclude demo PageCache page that is going to be cached.</h3>
<h3>Default cache expiration time for this page is 20 minutes. You can change this value in your <i>conf.php</i>
    and passing its file path to PageCache constructor, or by calling <i>setExpiration()</i> method.</h3>
<h3 style="color: green;">This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 20 minutes.</h4>
</body>
</html>
