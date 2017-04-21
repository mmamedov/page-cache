<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Tests;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStream;
use PageCache\CacheItemStorage;
use PageCache\DefaultLogger;
use PageCache\PageCache;
use PageCache\PageCacheException;
use PageCache\SessionHandler;
use PageCache\Storage\FileSystem\FileSystemPsrCacheAdapter;
use PageCache\Strategy\DefaultStrategy;
use PageCache\Strategy\MobileStrategy;
use Psr\Log\LoggerInterface;

class PageCacheTest extends \PHPUnit_Framework_TestCase
{
    /** @var  vfsStreamDirectory */
    private $root;

    public function setUp()
    {
        date_default_timezone_set('UTC');
        $this->setServerParameters();
        $this->root = vfsStream::setup('tmpdir');
    }

    protected function tearDown()
    {
        PageCache::destroy();
        SessionHandler::reset();
    }

    /**
     * Multiple Instances
     *
     * @expectedException \PageCache\PageCacheException
     */
    public function testSingleton()
    {
        $pc = new PageCache();
        $another = new PageCache();
    }

    /**
     * Without config file
     */
        public function testConstructWithoutConfig()
    {
        $pc = new PageCache();
        $this->assertFalse(SessionHandler::getStatus());

        $this->assertAttributeInstanceOf(\PageCache\HttpHeaders::class, 'httpHeaders', $pc);
        $this->assertAttributeInstanceOf(DefaultStrategy::class, 'strategy', $pc);
        $this->assertAttributeEquals(null, 'config', $pc);
    }

    /**
     * Default init
     */
    public function testInit()
    {
        $pc = new PageCache();
        $pc->setPath(vfsStream::url('tmpdir') . '/');

        // No CacheItemStorage before init()
        $this->assertAttributeEquals(null, 'itemStorage', $pc);

        $pc->init();

        // CacheItemStorage created
        $this->assertAttributeInstanceOf(CacheItemStorage::class, 'itemStorage', $pc);

        $output = 'Testing output for testInit()';
        echo $output;

        $this->assertFalse($pc->isCached());
        ob_end_flush();
        $this->assertTrue($pc->isCached());
    }

    public function testInitWithHeaders()
    {
        $pc = new PageCache();
        $pc->setPath(vfsStream::url('tmpdir') . '/');
        $pc->enableHeaders(true);
        $pc->init();
        $output = 'Testing output for InitWithHeaders() with Headers enabled';
        echo $output;
        $this->assertFalse($pc->isCached());
        ob_end_flush();
        $this->assertTrue($pc->isCached());
    }

    public function testSetStrategy()
    {
        $pc = new PageCache();

        $pc->setStrategy(new MobileStrategy());
        $this->assertAttributeInstanceOf(MobileStrategy::class, 'strategy', $pc);

        $pc->setStrategy(new DefaultStrategy());
        $this->assertAttributeInstanceOf(DefaultStrategy::class, 'strategy', $pc);
    }

    public function testSetStrategyException()
    {
        $pc = new PageCache();
        try {
            $pc->setStrategy(new \stdClass());
            $this->expectException('PHPUnit_Framework_Error');
        } catch (\Throwable $e) {
            // echo '~~~~As expected PHP7 throws Throwable.';
        } catch (\Exception $e) {
            // echo '~~~~As expected PHP5 throws Exception.';
        }
    }

    public function testClearPageCache()
    {
        $pc = new PageCache(__DIR__ . '/config_test.php');
        $pc->setPath(vfsStream::url('tmpdir') . '/');

        $pc->init();
        $output = 'Testing output for clearPageCache()';
        echo $output;
        ob_end_flush();
        $this->assertTrue($pc->isCached(), 'cache does not exist');

        $pc->clearPageCache();
        $this->assertFalse($pc->isCached(), 'cache exists');
    }

    public function testGetPageCache()
    {
        $cachePath = vfsStream::url('tmpdir').'/';

        $pc = new PageCache();
        $pc->setPath($cachePath);
        $this->assertSame(false, $pc->getPageCache());
        $pc->destroy();

        $pc = new PageCache(__DIR__ . '/config_test.php');
        $pc->setPath($cachePath);
        $pc->init();
        $output = 'Testing output for getPageCache()';
        echo $output;
        ob_end_flush();
        $this->assertEquals($output, $pc->getPageCache());
    }

    public function testIsCached()
    {
        $pc = new PageCache(__DIR__ . '/config_test.php');
        $pc->setPath(vfsStream::url('tmpdir') . '/');

        //no cache exists
        $this->assertFalse($pc->isCached(), ' is cached');

        //cache page
        $pc->init();
        $output = 'testIsCached() being test... this line is going to populate cache file for testing...';
        echo $output;

        //manually end output buffering. file cache must exist
        ob_end_flush();

        //cache exists now
        $this->assertTrue(
            $pc->isCached(),
            __METHOD__ . ' after init cache item does not exist'
        );
        $this->assertEquals($output, $pc->getPageCache(), 'Cache file contents not as expected.');
    }

    public function testSetPath()
    {
        $pc = new PageCache();
        $this->assertAttributeSame(null, 'cachePath', $pc);

        $dir = __DIR__ . '/';
        $pc->setPath($dir);
        $this->assertAttributeSame($dir, 'cachePath', $pc);
    }

    /**
     * @expectedException \Exception
     */
    public function testSetPath2()
    {
        $pc = new PageCache();
        $pc->setPath('nonexistant_dir');
    }

    public function testSetExpiration()
    {
        $pc = new PageCache();
        $pc->setExpiration(10);
        $this->assertAttributeSame(10, 'cacheExpire', $pc);
    }

    public function testSetExpirationException()
    {
        $pc = new PageCache();
        $this->expectException(PageCacheException::class);
        $pc->setExpiration(-1);
    }

    public function testEnableLog()
    {
        $pc = new PageCache();
        $pc->enableLog();
        $this->assertAttributeSame(true, 'logEnabled', $pc);
    }

    public function testDisableLog()
    {
        $pc = new PageCache();
        $pc->disableLog();
        $this->assertAttributeSame(false, 'logEnabled', $pc);
    }

    public function testSetMinCacheFileSize()
    {
        $pc = new PageCache();
        $pc->setMinCacheFileSize(0);
        $this->assertAttributeSame(0, 'minCacheFileSize', $pc);

        $pc->setMinCacheFileSize(10000);
        $this->assertAttributeSame(10000, 'minCacheFileSize', $pc);
    }

    public function testEnableSession()
    {
        $pc = new PageCache();

        $pc->enableSession();
        $this->assertEquals(true, SessionHandler::getStatus());
    }

    public function testDisableSession()
    {
        $pc = new PageCache();

        $pc->disableSession();
        $this->assertEquals(false, SessionHandler::getStatus());
    }

    public function testSessionExclude()
    {
        $pc = new PageCache();

        $pc->sessionExclude(array());
        $this->assertEquals(array(), SessionHandler::getExcludeKeys());

        $pc->sessionExclude(array(1, 2, 3));
        $this->assertEquals(array(1, 2, 3), SessionHandler::getExcludeKeys());
    }

    public function testGetSessionExclude()
    {
        $pc = new PageCache();
        $result = $pc->getSessionExclude();
        $this->assertEmpty($result);

        $pc->sessionExclude(array(null, '2', 3, false, new \stdClass()));
        $this->assertEquals(array(null, '2', 3, false, new \stdClass()), SessionHandler::getExcludeKeys());
    }

    public function testParseConfig()
    {
        $pc = new PageCache(__DIR__ . '/config_test.php');
        $this->assertAttributeNotEmpty('config', $pc);

        //include $config array
        $config = null;
        include(__DIR__ . '/config_test.php');
        $this->assertAttributeEquals($config, 'config', $pc);

        $this->assertAttributeSame(1, 'minCacheFileSize', $pc);
        $this->assertAttributeSame(false, 'logEnabled', $pc);
        $this->assertAttributeSame(600, 'cacheExpire', $pc);
        $this->assertAttributeContains('/tmp/cache/', 'cachePath', $pc);
        $this->assertAttributeContains('/tmp', 'logFilePath', $pc);
        $this->assertSame(false, SessionHandler::getStatus());
        $this->assertSame(null, SessionHandler::getExcludeKeys());
        $this->assertAttributeSame($config['file_lock'], 'fileLock', $pc);
        $this->assertAttributeSame($config['forward_headers'], 'forwardHeaders', $pc);
    }

    /**
     * Config enable log not boolean(ignored), expiration negative -> throws exception
     */
    public function testWrongParseConfig()
    {
        $this->expectException(PageCacheException::class);
        $pc = new PageCache(__DIR__ . '/config_wrong_test.php');

        $this->assertAttributeEmpty('logEnabled', $pc);
    }

    public function testSetLogger()
    {
        $pc = new PageCache();
        $this->assertAttributeEmpty('logger', $pc);

        $logger = new Logger('testmonolog');

        $pc->setLogger($logger);
        $this->assertAttributeEquals($logger, 'logger', $pc);
    }

    public function testDefaultLogger()
    {
        $tmpDir = vfsStream::url('tmpdir');
        $tmpFile = $tmpDir.'/log.txt';

        $pc = new PageCache();
        $pc->enableLog();
        $pc->setPath($tmpDir . '/');
        $pc->setLogFilePath($tmpFile);

        // No logger
        $this->assertAttributeEquals(null, 'logger', $pc);

        $pc->init();
        $output = 'testLog() method testing, output testing.';
        echo $output;
        ob_end_flush();

        $this->assertAttributeInstanceOf(DefaultLogger::class, 'logger', $pc);
        $this->assertContains('PageCache\PageCache::init', file_get_contents($tmpFile));
    }

    public function testLogWithMonolog()
    {
        $cachePath = vfsStream::url('tmpdir').'/';
        $defaultLogFile = vfsStream::url('tmpdir').'/log.txt';
        $monologLogFile = vfsStream::url('tmpdir').'/monolog.log';

        $pc = new PageCache();
        $pc->setPath($cachePath);
        $pc->setLogFilePath($defaultLogFile); //internal logger, should be ignored
        $pc->enableLog();

        $logger = new Logger('PageCache');
        $logger->pushHandler(new StreamHandler($monologLogFile, Logger::DEBUG));
        $pc->setLogger($logger);

        $pc->init();
        ob_end_flush();
        $this->assertContains(
            'PageCache\PageCache::init',
            file_get_contents($monologLogFile)
        );
        $this->assertFalse(file_exists($defaultLogFile));
    }

    public function testDestroy()
    {
        $pc = new PageCache();
        $pc->enableSession();
        $this->assertEquals(true, SessionHandler::getStatus());
        $pc->destroy();

        $pc2 = new PageCache();
        $this->assertEquals(false, SessionHandler::getStatus());
    }

    public function testSetFileLock()
    {
        $pc = new PageCache();
        $pc->setFileLock(LOCK_EX);
        $this->assertAttributeEquals(LOCK_EX, 'fileLock', $pc);

        $pc->setFileLock(LOCK_EX | LOCK_NB);
        $this->assertAttributeEquals(LOCK_EX | LOCK_NB, 'fileLock', $pc);
    }

    public function testGetFileLock()
    {
        $pc = new PageCache();
        $this->assertEquals(6, $pc->getFileLock());

        $pc->setFileLock(LOCK_EX);
        $this->assertEquals(LOCK_EX, $pc->getFileLock());
    }

    public function testGetExpiration()
    {
        $pc = new PageCache();
        $this->assertEquals(1200, $pc->getExpiration());

        $pc->setExpiration(20);
        $this->assertEquals(20, $pc->getExpiration());
    }

    public function testGetPath()
    {
        $pc = new PageCache();
        $this->assertNull($pc->getPath());

        $dir = __DIR__.'/';
        $pc->setPath($dir);
        $this->assertEquals($dir, $pc->getPath());
    }

    public function testGetLogFilePath()
    {
        $pc = new PageCache();
        $this->assertNull($pc->getLogFilePath());

        $path = 'somepath/to/file';

        $pc->setLogFilePath($path);
        $this->assertAttributeEquals($path, 'logFilePath', $pc);
    }

    public function testGetMinCacheFileSize()
    {
        $pc = new PageCache();
        $pc->getMinCacheFileSize();
        $this->assertAttributeSame(10, 'minCacheFileSize', $pc);

        $pc->setMinCacheFileSize(10240);
        $this->assertEquals(10240, $pc->getMinCacheFileSize());
    }

    public function testGetStrategy()
    {
        $pc = new PageCache();

        $pc->setStrategy(new DefaultStrategy());
        $this->assertInstanceOf(DefaultStrategy::class, $pc->getStrategy());
    }

    private function setServerParameters()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = '/';
        }

        if (!isset($_SERVER['SCRIPT_NAME'])) {
            $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
        }

        if (!isset($_SERVER['QUERY_STRING'])) {
            $_SERVER['QUERY_STRING'] = '';
        }
    }
}
