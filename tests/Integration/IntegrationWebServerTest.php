<?php
/**
 * This file is part of the PageCache package.
 *
 * @author       Denis Terekhov <i.am@spotman.ru>
 * @author       Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace PageCache\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Process\Process;
use function GuzzleHttp\Psr7\build_query;

/**
 * Class IntegrationWebServerTest
 *
 * @package      PageCache\Tests\Integration
 */
class IntegrationWebServerTest extends \PHPUnit\Framework\TestCase
{
    const CACHE_TYPE_KEY                = 'cache';
    const CACHE_TYPE_INTERNAL           = 'internal';
    const CACHE_TYPE_SYMFONY_FILESYSTEM = 'symfony-filesystem';

    const CACHE_CLEAR_KEY = 'clear-cache';
    const REDIRECT_KEY    = 'redirect';

    const LOGGER_KEY      = 'logger';
    const LOGGER_INTERNAL = 'internal';
    const LOGGER_MONOLOG  = 'monolog';

    private $serverHost = 'localhost';

    private $serverPort = '9898';

    private $documentRoot = __DIR__.DIRECTORY_SEPARATOR.'www';

    private $cacheDirectory = __DIR__.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'cache';

    private $timeout = 5;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var \Symfony\Component\Process\Process
     */
    private $serverProcess;

    private $contentFiles = [
        '1.php',
        '2.php',
        '3.php',
        '4.php',
        '5.php',
    ];

    private $queryValues = [
        self::CACHE_TYPE_KEY => [
            self::CACHE_TYPE_INTERNAL,
            self::CACHE_TYPE_SYMFONY_FILESYSTEM,
        ],
        self::LOGGER_KEY     => [
            self::LOGGER_INTERNAL,
            self::LOGGER_MONOLOG,
        ],
    ];

    protected function setUp()
    {
        if ($this->canConnectToServer()) {
            throw new \RuntimeException('Something is already running on '.$this->serverHost.':'.$this->serverPort.'. Aborting tests.');
        }

        // Try to start the web server
        $this->startBuiltInServer();

        $start     = microtime(true);
        $connected = false;

        // Try to connect until the time spent exceeds the timeout specified in the configuration
        while (microtime(true) - $start <= $this->timeout) {
            if ($this->canConnectToServer()) {
                $connected = true;
                break;
            }
        }

        if (!$connected) {
            $this->stopBuiltInServer();

            throw new \RuntimeException(
                sprintf(
                    'Could not connect to the web server within the given timeframe (%d second(s))',
                    $this->timeout
                )
            );
        }

        $this->client = new Client([
            'base_uri' => 'http://'.$this->serverHost.':'.$this->serverPort,
        ]);

        // Clear cache so no cached response processed
        $this->clearCacheDirectory();
    }

    protected function tearDown()
    {
        $this->stopBuiltInServer();

        // Clear cache so no cached files remain in filesystem
        $this->clearCacheDirectory();
    }

    /**
     * @dataProvider cacheKeyValuesProvider
     */
    public function testSingle($cacheKey)
    {
        $contentFileName = $this->contentFiles[0];

        $request = $this->makeRequest($contentFileName, [
            self::CACHE_TYPE_KEY => $cacheKey,
        ]);

        // First request on empty cache (dry run and store in cache)
        $firstResponse = $this->sendSingleRequest($request);
        $this->checkResponse($firstResponse, $request, 200);

        // Replay request to the same page (real run, use cached version)
        $secondResponse = $this->sendSingleRequest($request);
        $this->checkResponse($secondResponse, $request, 200);

        // Replay again to the same page (real run, use cached version)
        $thirdResponse = $this->sendSingleRequest($request);
        $this->checkResponse($thirdResponse, $request, 200);
    }

    /**
     * @dataProvider cacheKeyValuesProvider
     */
    public function testSingleLastModified($cacheKey)
    {
        $contentFileName = $this->contentFiles[0];

        $baseRequest = $this->makeRequest($contentFileName, [
            self::CACHE_TYPE_KEY => $cacheKey,
        ]);

        $lastModifiedTimestamp = $this->getContentFileLastModifiedTimestamp($contentFileName);

        // First request on empty cache (dry run and store in cache)
        $firstResponse = $this->sendSingleRequest($baseRequest);
        $this->checkResponseStatusCode($firstResponse, 200);
        $this->checkLastModified($firstResponse, $lastModifiedTimestamp);

        // Replay request to the same page (real run, use must return 200 and last modified from cached item)
        $secondResponse = $this->sendSingleRequest($baseRequest);
        $this->checkResponseStatusCode($secondResponse, 200);
        $this->checkLastModified($secondResponse, $lastModifiedTimestamp);

        // Replay again to the same page (real run, use must return 200 and last modified from cached item)
        $thirdResponse = $this->sendSingleRequest($baseRequest);
        $this->checkResponseStatusCode($thirdResponse, 200);
        $this->checkLastModified($thirdResponse, $lastModifiedTimestamp);
    }

    /**
     * @group        issue10
     * @dataProvider cacheKeyValuesProvider
     */
    public function testSingleNotModified($cacheKey)
    {
        $contentFileName = $this->contentFiles[0];

        $baseRequest = $this->makeRequest($contentFileName, [
            self::CACHE_TYPE_KEY => $cacheKey,
        ]);

        // Add If-Modified-Since header (check empty cache logic)
        $firstRequest = $this->withModifiedSinceHeader($baseRequest, $contentFileName);

        // First request on empty cache (dry run and store in cache)
        $firstResponse = $this->sendSingleRequest($firstRequest);
        $this->checkResponse($firstResponse, $firstRequest, 200);

        // Add If-Modified-Since header (check empty cache logic)
        $secondRequest = $this->withModifiedSinceHeader($baseRequest, $contentFileName);

        // Replay request to the same page (real run, use must return 304 and empty body)
        $secondResponse = $this->sendSingleRequest($secondRequest);
        $this->checkResponse($secondResponse, $secondRequest, 304);

        // Add If-Modified-Since header (check empty cache logic)
        $thirdRequest = $this->withModifiedSinceHeader($baseRequest, $contentFileName);

        // Replay again to the same page (real run, use must return 304 and empty body)
        $thirdResponse = $this->sendSingleRequest($thirdRequest);
        $this->checkResponse($thirdResponse, $thirdRequest, 304);
    }

    public static function cacheKeyValuesProvider()
    {
        return [
            [self::CACHE_TYPE_INTERNAL],
            [self::CACHE_TYPE_SYMFONY_FILESYSTEM],
        ];
    }

    /**
     * @dataProvider cacheClearanceArguments
     */
    public function testMultipleWithClearanceProbability($probability)
    {
        $requests = $this->getRandomRequests(1000, $probability);

        $this->checkMultipleRequests($requests);
    }

    public static function cacheClearanceArguments()
    {
        return [
            [0],
            [10],
            [50],
        ];
    }

    /**
     * @param Request[] $requests
     */
    private function checkMultipleRequests(array $requests)
    {
        $pool = new Pool($this->client, $requests, [
            'concurrency' => 20,
            'fulfilled'   => function ($response, $index) use ($requests) {
                $request = $requests[$index];

                // We do not know exact status code here
                $this->checkResponse($response, $request);
            },
            'rejected'    => function ($reason, $index) use ($requests) {
                $request = $requests[$index];

                throw new \RuntimeException('Request to '.$request->getUri().' failed with reason: '.$reason);
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
    }

    /**
     * @param int      $total
     *
     * @param int|null $clearanceProbability
     *
     * @return Request[]
     * @throws \Exception
     */
    private function getRandomRequests($total, $clearanceProbability = null)
    {
        $output = [];

        for ($i = 0; $i < $total; $i++) {
            $contentFile = $this->getRandomContentFileName();
            $query       = $this->getQueryParametersForRandomSettings($clearanceProbability);

            $request = $this->makeRequest($contentFile, $query);

            // Send If-Modified-Since header sometimes (it is the root of the blank screen probably)
            if (\rand(1, 10) === 1) {
                $request = $this->withModifiedSinceHeader($request, $contentFile);
            }

            $output[] = $request;
        }

        return $output;
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param                          $contentFileName
     *
     * @return \GuzzleHttp\Psr7\Request|mixed
     */
    private function withModifiedSinceHeader(Request $request, $contentFileName)
    {
        $fullContentPath      = $this->getContentFileAbsolutePath($contentFileName);
        $ifModifiedSinceValue = gmdate("D, d M Y H:i:s \G\M\T", filemtime($fullContentPath));

        return $request->withHeader('If-Modified-Since', $ifModifiedSinceValue);
    }

    /**
     * @param int|null $clearanceProbability In percents
     *
     * @return string[]
     * @throws \Exception
     */
    private function getQueryParametersForRandomSettings($clearanceProbability = null)
    {
        $query = [];

        // TODO Randomize cache control headers (Pragma, etc)

        foreach ($this->queryValues as $key => $values) {
            $index       = \rand(0, \count($values) - 1);
            $query[$key] = $values[$index];
        }

        // Clear cache if probability set and matched
        $query[self::CACHE_CLEAR_KEY] = $clearanceProbability && \rand(1, 100) < (int)$clearanceProbability;

        // Redirect sometimes
        $query[self::REDIRECT_KEY] = (bool)\rand(0, 1);

        return $query;
    }

    private function getRandomContentFileName()
    {
        $filesCount = \count($this->contentFiles);

        $index = \rand(0, $filesCount - 1);

        return $this->contentFiles[$index];
    }

    /**
     * Start the built in web server
     */
    private function startBuiltInServer()
    {
        // Build the command
        $command = sprintf('%s -S %s:%d -t %s %s', // >/dev/null 2>&1
            PHP_BINARY,
            $this->serverHost,
            $this->serverPort,
            $this->documentRoot,
            $this->documentRoot.DIRECTORY_SEPARATOR.'index.php'
        );

        if ('\\' !== DIRECTORY_SEPARATOR) {
            // exec is mandatory to deal with sending a signal to the process
            $command = 'exec '.$command;
        }

        $this->serverProcess = new Process($command);
        $this->serverProcess->start();

        if (!$this->serverProcess->isRunning()) {
            throw new \RuntimeException('Could not start the web server');
        }
    }

    /**
     * Kill a server process
     */
    private function stopBuiltInServer()
    {
        $this->serverProcess->stop(5, SIGINT);

        $start   = microtime(true);
        $stopped = false;

        // Try to connect until the time spent exceeds the timeout specified in the configuration
        while (microtime(true) - $start <= $this->timeout) {
            if ($this->serverProcess->isTerminated()) {
                $stopped = true;
                break;
            }
        }

        if (!$stopped) {
            throw new \RuntimeException('Could not stop the web server, kill it by PID = '.$this->serverProcess->getPid());
        }
    }

    /**
     * See if we can connect to the httpd
     *
     * @return boolean
     */
    private function canConnectToServer()
    {
        // Disable error handler for now
        set_error_handler(function () {
            return true;
        });

        // Try to open a connection
        $sp = fsockopen($this->serverHost, $this->serverPort);

        // Restore the handler
        restore_error_handler();

        if ($sp === false) {
            return false;
        }

        fclose($sp);

        return true;
    }

    /**
     * @param string     $contentFileName
     *
     * @param array|null $queryParams
     *
     * @return \GuzzleHttp\Psr7\Request
     */
    private function makeRequest($contentFileName, array $queryParams = null)
    {
        $uri = new Uri('/'.$contentFileName);

        // Use default params if none set
        $queryParams = array_merge([
            self::CACHE_TYPE_KEY  => self::CACHE_TYPE_INTERNAL,
            self::LOGGER_KEY      => self::LOGGER_INTERNAL,
            self::CACHE_CLEAR_KEY => false,
            self::REDIRECT_KEY    => false,
        ], $queryParams ?: []);

        $uri = $uri->withQuery(build_query($queryParams));

        return new Request('GET', $uri);
    }

    private function sendSingleRequest(Request $request)
    {
        return $this->client->send($request, ['timeout' => $this->timeout]);
    }

    /**
     * @param \GuzzleHttp\Psr7\Response $response
     * @param \GuzzleHttp\Psr7\Request  $request
     * @param int|null                  $expectedStatusCode
     */
    private function checkResponse(Response $response, Request $request, $expectedStatusCode = null)
    {
        $responseStatusCode = $response->getStatusCode();

        try {
            if ($expectedStatusCode) {
                $this->assertEquals(
                    $expectedStatusCode,
                    $responseStatusCode,
                    'Response status code is not as expected'
                );
            }

            switch ($responseStatusCode) {
                case 200:
                    $this->checkOkResponse($response, $request);
                    break;

                case 304:
                    $this->checkNotModifiedResponse($response, $request);
                    break;

                default:
                    throw new \RuntimeException('Unknown response status code: '.$responseStatusCode);
            }
        } catch (ExpectationFailedException $e) {
            // Send more debug info
            fwrite(
                STDERR,
                PHP_EOL.
                'Request uri is: '.$request->getUri().PHP_EOL
            );
            fwrite(
                STDERR,
                'Request headers are:'.PHP_EOL.print_r($request->getHeaders(), true).PHP_EOL
            );
            fwrite(
                STDERR,
                'Response status is: '.$response->getStatusCode().' '.$response->getReasonPhrase().PHP_EOL
            );
            fwrite(
                STDERR,
                'Response headers are:'.PHP_EOL.print_r($response->getHeaders(), true).PHP_EOL
            );
            fwrite(
                STDERR,
                'Response body is:'.PHP_EOL.PHP_EOL.$response->getBody().PHP_EOL
            );

            throw $e;
        }
    }

    private function checkOkResponse(Response $response, Request $request)
    {
        $responseContent = $response->getBody()->getContents();

        $this->checkResponseStatusCode($response, 200);

        $this->assertNotEquals('', $responseContent, 'Empty response!');

        $contentFileName = $request->getUri()->getPath();
        $fullContentPath = $this->getContentFileAbsolutePath($contentFileName);
        $originalContent = file_get_contents($fullContentPath);

        $this->assertEquals(
            $originalContent,
            $responseContent,
            'Fetched response is differs from original file content'
        );
    }

    private function checkNotModifiedResponse(Response $response, Request $request)
    {
        $this->assertTrue($request->hasHeader('If-Modified-Since'), 'Request has no If-Modified-Since header');

        $statusCode = $response->getStatusCode();
        $body       = $response->getBody();

        // Check body first so we may see diff with error messages
        $this->assertEquals('', $body->getContents(), 'Body of Not Modified response is not empty on '.$statusCode);

        $this->checkResponseStatusCode($response, 304);
    }

    private function checkResponseStatusCode(Response $response, $expectedStatusCode)
    {
        $this->assertEquals($expectedStatusCode, $response->getStatusCode(), 'Incorrect status code');
    }

    private function checkLastModified(Response $response, $contentLastModifiedTimestamp)
    {
        $contentLastModifiedString  = gmdate("D, d M Y H:i:s \G\M\T", $contentLastModifiedTimestamp);
        $responseLastModifiedString = $response->getHeaderLine('Last-Modified');

        $this->assertEquals(
            $contentLastModifiedString,
            $responseLastModifiedString,
            'Last-Modified header value not match'
        );
    }

    /**
     * @param string $fileName
     *
     * @return string
     */
    private function getContentFileAbsolutePath($fileName)
    {
        return $this->documentRoot.DIRECTORY_SEPARATOR.$fileName;
    }

    /**
     * @param $fileName
     *
     * @return bool|int
     */
    private function getContentFileLastModifiedTimestamp($fileName)
    {
        return filemtime($this->getContentFileAbsolutePath($fileName));
    }

    private function clearCacheDirectory()
    {
        $it = new \RecursiveDirectoryIterator($this->cacheDirectory, \RecursiveDirectoryIterator::SKIP_DOTS);

        $filter = new \RecursiveCallbackFilterIterator($it, function ($current) {
            /** @var \SplFileInfo $current */
            $filename = $current->getBasename();

            // Check for files and dirs starting with "dot" (.gitignore, etc)
            return !($filename && $filename[0] === '.');
        });

        $files = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
    }
}
