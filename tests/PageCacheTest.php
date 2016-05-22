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
use PageCache\PageCache;
use PageCache\SessionHandler;
use PageCache\Strategy;

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
     * @expectedException \Exception
     */
    public function testConstructor1()
    {
        $pc = new PageCache();
        $another = new PageCache();
    }

    /**
     * Without config file
     */
    public function testConstructor2()
    {
        $pc = new PageCache();
        $this->assertFalse(SessionHandler::getStatus());

        $this->assertAttributeInstanceOf('PageCache\Strategy\DefaultStrategy', 'strategy', $pc);
        $this->assertAttributeEquals(null, 'config', $pc);
    }

    public function testInit()
    {
        $pc = new PageCache();
        $pc->setPath(vfsStream::url('tmpdir') . '/');
        $pc->init();
        $output = 'Testing output for clearPageCache()';
        echo $output;

        $this->assertFalse($pc->isCached());
        $this->assertFileNotExists($pc->getFilePath());
        ob_end_flush();
        $this->assertFileExists($pc->getFilePath());
        $this->assertTrue($pc->isCached());
    }

    public function testSetStrategy()
    {
        $pc = new PageCache();
        $pc->setStrategy(new Strategy\MobileStrategy());
        $this->assertAttributeInstanceOf('PageCache\Strategy\MobileStrategy', 'strategy', $pc);

        $pc->setStrategy(new Strategy\DefaultStrategy());
        $this->assertAttributeInstanceOf('PageCache\Strategy\DefaultStrategy', 'strategy', $pc);
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
        $this->assertFalse(file_exists($pc->getFilePath()), 'file exists');

        $pc->init();
        $output = 'Testing output for clearPageCache()';
        echo $output;
        ob_end_flush();
        $this->assertTrue(file_exists($pc->getFilePath()), 'file does not exist');

        $pc->clearPageCache();
        $this->assertFalse(file_exists($pc->getFilePath()), 'file exists');
    }

    public function testGetPageCache()
    {
        $pc = new PageCache();
        $result = $pc->getPageCache();
        $this->assertSame(false, $result);
        $pc->destroy();

        $pc = new PageCache(__DIR__ . '/config_test.php');
        $pc->setPath(vfsStream::url('tmpdir') . '/');
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
        $this->assertFalse(file_exists($pc->getFilePath()), 'file exists');

        //cache page
        $pc->init();
        $output = 'testIsCached() being test... this line is going to populate cache file for testing...';
        echo $output;

        //manually end output buffering. file cache must exist
        ob_end_flush();

        //cache exists now
        $this->assertTrue($pc->isCached());
        $this->assertTrue(
            file_exists($pc->getFilePath()),
            __METHOD__ . ' after init cache file does not exist'
        );
        $this->assertEquals($output, file_get_contents($pc->getFilePath()), 'Cache file contents not as expected.');
    }

    public function testGetFile()
    {
        $pc = new PageCache();
        $file = $pc->getFile();

        $this->assertNotNull($file);
    }

    public function testSetPath()
    {
        $pc = new PageCache();
        $this->assertAttributeSame(null, 'cache_path', $pc);

        $pc->setPath(__DIR__ . '/');
        $this->assertAttributeSame(__DIR__ . '/', 'cache_path', $pc);
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
        $this->assertAttributeSame(10, 'cache_expire', $pc);
    }

    public function testSetExpirationException()
    {
        $pc = new PageCache();
        $this->setExpectedException('\Exception');
        $pc->setExpiration(-1);
    }

    public function testEnableLog()
    {
        $pc = new PageCache();
        $pc->enableLog();
        $this->assertAttributeSame(true, 'enable_log', $pc);
    }

    public function testDisableLog()
    {
        $pc = new PageCache();
        $pc->disableLog();
        $this->assertAttributeSame(false, 'enable_log', $pc);
    }

    public function testSetMinCacheFileSize()
    {
        $pc = new PageCache();
        $pc->setMinCacheFileSize(0);
        $this->assertAttributeSame(0, 'min_cache_file_size', $pc);

        $pc->setMinCacheFileSize(10000);
        $this->assertAttributeSame(10000, 'min_cache_file_size', $pc);
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

        $this->assertAttributeSame(1, 'min_cache_file_size', $pc);
        $this->assertAttributeSame(false, 'enable_log', $pc);
        $this->assertAttributeSame(600, 'cache_expire', $pc);
        $this->assertAttributeContains('/tmp/cache/', 'cache_path', $pc);
        $this->assertAttributeContains('/tmp', 'log_file_path', $pc);
        $this->assertSame(false, SessionHandler::getStatus());
        $this->assertSame(null, SessionHandler::getExcludeKeys());
        $this->assertAttributeSame($config['file_lock'], 'file_lock', $pc);
    }

    public function testSetLogger()
    {
        $pc = new PageCache();
        $this->assertAttributeEmpty('logger', $pc);

        $pc->setLogger(new \stdClass());
        $this->assertAttributeNotInstanceOf('\Psr\Log\LoggerInterface', 'logger', $pc);

        $pc->setLogger(new Logger('testmonolog'));
        $this->assertAttributeInstanceOf('\Psr\Log\LoggerInterface', 'logger', $pc);
    }

    public function testLog()
    {
        $pc = new PageCache();
        $pc->enableLog();
        $pc->setPath(vfsStream::url('tmpdir') . '/');
        $pc->setLogFilePath(vfsStream::url('tmpdir') . '/log.txt');
        $pc->init();
        $output = 'testLog() method testing, output testing.';
        echo $output;
        ob_end_flush();

        $this->assertContains('PageCache\PageCache::init', file_get_contents(vfsStream::url('tmpdir') . '/log.txt'));
    }

    public function testLogWithMonolog()
    {
        $pc = new PageCache();
        $pc->enableLog();
        $pc->setLogFilePath(vfsStream::url('tmpdir') . '/log.txt'); //internal logger, should be ignored

        $logger = new Logger('PageCache');
        $logger->pushHandler(new StreamHandler(vfsStream::url('tmpdir') . '/monolog.log', Logger::DEBUG));
        $pc->setLogger($logger);

        $pc->init();
        ob_end_flush();
        $this->assertContains(
            'PageCache\PageCache::init',
            file_get_contents(vfsStream::url('tmpdir') . '/monolog.log')
        );
        $this->assertFalse(file_exists(vfsStream::url('tmpdir') . '/log.txt'));
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
        $this->assertAttributeEquals(LOCK_EX, 'file_lock', $pc);

        $pc->setFileLock(LOCK_EX | LOCK_NB);
        $this->assertAttributeEquals(LOCK_EX | LOCK_NB, 'file_lock', $pc);
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

        $pc->setPath(__DIR__ . '/');
        $this->assertNotEmpty($pc->getPath());
    }

    public function testGetLogFilePath()
    {
        $pc = new PageCache();
        $this->assertNull($pc->getLogFilePath());

        $pc->setLogFilePath('somepath/to/file');
        $this->assertAttributeEquals('somepath/to/file', 'log_file_path', $pc);
    }

    public function testGetMinCacheFileSize()
    {
        $pc = new PageCache();
        $pc->getMinCacheFileSize();
        $this->assertAttributeSame(10, 'min_cache_file_size', $pc);

        $pc->setMinCacheFileSize(10240);
        $this->assertEquals(10240, $pc->getMinCacheFileSize());
    }

    public function testGetStrategy()
    {
        $pc = new PageCache();
        $this->assertInstanceOf('PageCache\Strategy\DefaultStrategy', $pc->getStrategy());
    }

    private function setServerParameters()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
        }

        if (!isset($_SERVER['QUERY_STRING'])) {
            $_SERVER['QUERY_STRING'] = '/';
        }
    }
}
