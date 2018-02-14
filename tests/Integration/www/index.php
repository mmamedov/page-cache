<?php
//declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PageCache\PageCache;
use PageCache\Tests\Integration\IntegrationWebServerTest;
use Symfony\Component\Cache\Simple\FilesystemCache;

ini_set('display_errors', 1);

$docRoot = __DIR__;
$libRoot = dirname(__DIR__.'/../../../../');

require $libRoot.'/vendor/autoload.php';

$logsDirectory  = $docRoot.'/logs';
$cacheDirectory = $docRoot.'/cache';

if (!file_exists($cacheDirectory) && !mkdir($cacheDirectory, 0777) && !is_dir($cacheDirectory)) {
    throw new Exception('Can not create cache directory');
}

if (!file_exists($logsDirectory) && !mkdir($logsDirectory, 0777) && !is_dir($logsDirectory)) {
    throw new RuntimeException('Can not create logs directory');
}

// Detect content file and query parameters
$uri         = $_SERVER['REQUEST_URI'];
$path        = parse_url($uri, PHP_URL_PATH);
$contentFile = $docRoot.$path;

parse_str(parse_url($uri, PHP_URL_QUERY), $query);
$cacheName  = (string)$query[IntegrationWebServerTest::CACHE_TYPE_KEY];
$loggerName = (string)$query[IntegrationWebServerTest::LOGGER_KEY];
$clearCache = (bool)$query[IntegrationWebServerTest::CACHE_CLEAR_KEY];
$redirect   = (bool)$query[IntegrationWebServerTest::REDIRECT_KEY];

// Redirect to the same page if needed
if ($redirect) {
    $redirectKey = IntegrationWebServerTest::REDIRECT_KEY;
    // Prevent endless redirects
    $uri = str_replace($redirectKey.'=1', $redirectKey.'=0', $uri);
    header('Location: '.$uri);
}

// Clear query string so default strategy would not rely on test configuration parameters
$_SERVER['QUERY_STRING'] = null;

// Instance with default configuration
$pc = new PageCache();

// Common configuration
$pc->config()
    // 60 seconds is enough for testing both concurrency and single requests
    ->setCacheExpirationInSeconds(60)
    ->setLogFilePath($logsDirectory.'/page-cache.log')
    ->setEnableLog(true)
    ->setUseSession(true)
    ->setForwardHeaders(true)
    ->setSendHeaders(true);

setCacheImplementation($pc, $cacheName, $cacheDirectory);
setLoggerImplementation($pc, $loggerName, $logsDirectory);

// Clear cache if needed (trying to create race conditions on empty cache)
if ($clearCache) {
    $pc->clearAllCache();
}

// Initialize
$pc->init();

// Send headers (Last-Modified, Expires)
$lastModifiedTimestamp = filemtime($contentFile);
$expiresInTimestamp    = time() + $pc->config()->getCacheExpirationInSeconds();

header('Last-Modified: '.gmdate("D, d M Y H:i:s \G\M\T", $lastModifiedTimestamp));
header('Expires: '.gmdate("D, d M Y H:i:s \G\M\T", $expiresInTimestamp));

// Send content
include $contentFile;


function setCacheImplementation(PageCache $pc, $name, $cacheDirectory)
{
    if (!$name) {
        throw new RuntimeException('No cache implementation set');
    }

    switch ($name) {
        case IntegrationWebServerTest::CACHE_TYPE_INTERNAL:
            // Internal cache is used by default, needs only directory to set
            $pc->config()->setCachePath($cacheDirectory.DIRECTORY_SEPARATOR);
            break;

        case IntegrationWebServerTest::CACHE_TYPE_SYMFONY_FILESYSTEM:
            // Using basic symfony/cache
            $ttl          = $pc->config()->getCacheExpirationInSeconds() * 2;
            $cacheAdapter = new FilesystemCache('symfony-cache', $ttl, $cacheDirectory);
            $pc->setCacheAdapter($cacheAdapter);
            break;

        default:
            throw new RuntimeException('Unknown cache implementation key: '.$name);
    }
}

function setLoggerImplementation(PageCache $pc, $name, $logsDirectory)
{
    if (!$name) {
        throw new RuntimeException('No logger implementation set');
    }

    switch ($name) {
        case IntegrationWebServerTest::LOGGER_INTERNAL:
            // Internal logger is used by default, needs only cache file to set
            $pc->config()->setLogFilePath($logsDirectory.DIRECTORY_SEPARATOR.'page-cache.internal.log');
            break;

        case IntegrationWebServerTest::LOGGER_MONOLOG:
            $logger = new Logger('default');
            $logger->pushHandler(new StreamHandler($logsDirectory.DIRECTORY_SEPARATOR.'page-cache.monolog.log'));

            $pc->setLogger($logger);
            break;

        default:
            throw new RuntimeException('Unknown logger implementation key: '.$name);
    }
}

