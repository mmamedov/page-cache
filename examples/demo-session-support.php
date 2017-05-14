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
 * Two versions of this page will be saved.
 * If your cache directory is cache, then look for cache/54/3/668.. and cache/97/52/ead.. files.
 *
 * Despite that URL doesn't change (PageCache DefaultStrategy doesn't support $_POST()), PageCache is able to
 * save two different versions of the page based on $_SESSION data.
 *
 * enableSession() is useful if you are going to cache a dynamic PHP application where you use $_SESSION
 * for various things, like user login and etc.
 *
 *
 * NOTE: If you want to cache only URLs before user login or other session manipulations, you could put
 * PageCache call inside if(!isset($_SESSION[..])) { //run PageCache only on pages without certain Session variable }
 *
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * session is started only when "Enable session" button is pressed
 */

if (isset($_POST['withsessions']) && $_POST['withsessions'] == '1') {
    session_start();

    //sample session data
    $_SESSION['demo_session'] = array('my val1', 'my val2');
    $_SESSION['user'] = 12;
    $_SESSION['login'] = true;
}

$cache = new PageCache\PageCache();

//cache path
$cache->config()->setCachePath(__DIR__ . '/cache/');

//Disable line below and you will see only 1 cache file generated,
// no differentiation between session and without session calls
//
//use session support in cache
//
$cache->config()->setUseSession(true);

//do disable session cache uncomment this line, or comment line above and see
//$cache->config()->setUseSession(false);

//enable log
//$cache->config()->setEnableLog(true);
//$cache->config()->setLogFilePath(__DIR__.'/log/cache.log');


//start cache;
$cache->init();

echo 'Everything below is cached, including this line<hr>';

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
<h1>Demo using Session Support</h1>
Click on the links below to see how PageCache works with sessions. Although page URL doesn't change, PageCache is able
to cache 2 different version of this page based on Session variables.
<br/><br>
<fieldset>
    <form method="post" action="">
        <input type="hidden" value="1" name="withsessions">
        <button type="submit">Enable session cache</button>
    </form>
    <form method="post" action="">
        <input type="hidden" value="0" name="withsessions">
        <button type="submit">No session cache</button>
    </form>
</fieldset>


<?php
/**
 * Print session data if present
 */
if (isset($_SESSION['demo_session'])) {
    echo '<b>SESSION IS ENABLED. Session contents:</b> <fieldset><pre>';
    print_r($_SESSION);
    echo '</pre></fieldset>';
} else {
    echo '<fieldset>No session data</fieldset>';
}

?>

<br>
<h3 style="color: red">This is a Session demo PageCache page that is going to be cached.</h3>
<h3>Default cache expiration time for this page is 20 minutes. You can change this value in your <i>conf.php</i>
    and passing its file path to PageCache constructor, or by calling <i>setExpiration()</i> method.</h3>
<h3 style="color: green;">This is a dynamic PHP <i>date('H:i:s')</i>
    call, note that time doesn't change on refresh: <?php echo date('H:i:s'); ?>.</h3>
<br>
<h4>Check examples/cache/ directory to see cached content.
    Erase this file to regenerate cache, or it will automatically be regenerated in 20 minutes.</h4>
</body>
</html>