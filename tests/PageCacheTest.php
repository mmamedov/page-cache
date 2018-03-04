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

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PageCache\Config;
use PageCache\Storage\CacheItemStorage;
use PageCache\DefaultLogger;
use PageCache\PageCache;
use PageCache\SessionHandler;
use PageCache\Strategy\DefaultStrategy;
use PageCache\Strategy\MobileStrategy;

class PageCacheTest extends \PHPUnit\Framework\TestCase
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
        $pc      = new PageCache();
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
        $this->assertAttributeInstanceOf(Config::class, 'config', $pc);
    }

    /**
     * Default init
     */
    public function testInit()
    {
        $pc = new PageCache();
        $pc->config()->setCachePath(vfsStream::url('tmpdir').'/');

        // No CacheItemStorage before init()
        $this->assertAttributeEquals(null, 'itemStorage', $pc);

        $pc->init();

        // CacheItemStorage created
        $this->assertAttributeInstanceOf(CacheItemStorage::class, 'itemStorage', $pc);

        $output = 'Testing output for testInit()';
        $this->expectOutputString($output);
        echo $output;

        $this->assertFalse($pc->isCached());
        ob_end_flush();
        $this->assertTrue($pc->isCached());
    }

    public function testInitWithHeaders()
    {
        $pc = new PageCache();
        $pc->config()->setCachePath(vfsStream::url('tmpdir').'/')
                     ->setSendHeaders(true);

        $pc->init();
        $output = 'Testing output for InitWithHeaders() with Headers enabled';
        $this->expectOutputString($output);
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
        $this->expectException(\InvalidArgumentException::class);

        try {
            $pc->setStrategy(new \stdClass());
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException;
            // echo '~~~~As expected PHP7 throws Throwable.';
        } catch (\Exception $e) {
            throw new \InvalidArgumentException;
            // echo '~~~~As expected PHP5 throws Exception.';
        }
    }

    public function testClearPageCache()
    {
        $pc = new PageCache(__DIR__.'/config_test.php');
        $pc->config()->setCachePath(vfsStream::url('tmpdir').'/');

        $pc->init();
        $output = 'Testing output for clearPageCache()';
        $this->expectOutputString($output);
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
        $pc->config()->setCachePath($cachePath);
        $this->assertSame(false, $pc->getPageCache());
        $pc->destroy();

        $pc = new PageCache(__DIR__.'/config_test.php');
        $pc->config()->setCachePath($cachePath);
        $pc->init();
        $output = 'Testing output for getPageCache()';
        $this->expectOutputString($output);
        echo $output;
        ob_end_flush();
        $this->assertSame($output, $pc->getPageCache());
    }

    public function testIsCached()
    {
        $pc = new PageCache(__DIR__.'/config_test.php');
        $pc->config()->setCachePath(vfsStream::url('tmpdir').'/');

        //no cache exists
        $this->assertFalse($pc->isCached(), ' is cached');

        //cache page
        $pc->init();
        $output = 'testIsCached() being test... this line is going to populate cache file for testing...';
        $this->expectOutputString($output);
        echo $output;

        //manually end output buffering. file cache must exist
        ob_end_flush();

        //cache exists now
        $this->assertTrue(
            $pc->isCached(),
            __METHOD__.' after init cache item does not exist'
        );
        $this->assertEquals($output, $pc->getPageCache(), 'Cache file contents not as expected.');
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
        $tmpDir  = vfsStream::url('tmpdir');
        $tmpFile = $tmpDir.'/log.txt';

        $pc = new PageCache();
        $pc->config()->setEnableLog(true)
                     ->setCachePath($tmpDir.'/')
                     ->setLogFilePath($tmpFile);

        // No logger
        $this->assertAttributeEquals(null, 'logger', $pc);

        //During init logger is initialized
        $pc->init();
        $output = 'testLog() method testing, output testing.';
        $this->expectOutputString($output);
        echo $output;
        ob_end_flush();

        $this->assertAttributeInstanceOf(DefaultLogger::class, 'logger', $pc);
        $this->assertContains('PageCache\PageCache::init', file_get_contents($tmpFile));
        $this->assertContains('PageCache\PageCache::storePageContent', file_get_contents($tmpFile));
    }

    public function testLogWithMonolog()
    {
        $cachePath      = vfsStream::url('tmpdir').'/';
        $defaultLogFile = vfsStream::url('tmpdir').'/log.txt';
        $monologLogFile = vfsStream::url('tmpdir').'/monolog.log';

        $pc = new PageCache();
        $pc->config()->setEnableLog(true)
            ->setCachePath($cachePath)
            ->setLogFilePath($defaultLogFile); //internal logger, should be ignored

        $monolog_logger = new Logger('PageCache');
        $monolog_logger->pushHandler(new StreamHandler($monologLogFile, Logger::DEBUG));
        $pc->setLogger($monolog_logger);

        $pc->init();
        ob_end_flush();
        $this->assertContains(
            'PageCache\PageCache::init',
            file_get_contents($monologLogFile)
        );
        $this->assertFalse(file_exists($defaultLogFile));
    }

    /**
     * @throws \Exception
     * @doesNotPerformAssertions
     */
    public function testDestroy()
    {
        $pc = new PageCache();
        $pc->config()->setUseSession(true);
        $pc->destroy();

        new PageCache();
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
