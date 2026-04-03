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

use PageCache\Config;
use PageCache\PageCache;
use PageCache\PageCacheException;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config
     */
    private $config;

    public function setUp(): void
    {
        date_default_timezone_set('UTC');
        $this->config = new Config();
    }

    public function testPageCacheIntegrationWithSetPath()
    {
        $pc = new PageCache();
        //initially cachePath is null
        $this->assertSame(null, $this->config->getCachePath());
        $this->assertNull($this->config->getCachePath());
        $dir = __DIR__.'/';
        $this->config->setCachePath($dir);
        $this->assertSame($this->config->getCachePath(), $dir);
        $this->config->setCachePath($dir);
        $this->assertSame($dir, $this->config->getCachePath());
    }

    public function testSetPath2()
    {
        $this->expectException(\Exception::class);
        $this->config->setCachePath('nonexistant_dir');
    }

    public function testSetGetExpiration()
    {
        $this->config->setCacheExpirationInSeconds(10);
        $this->assertSame(10, $this->config->getCacheExpirationInSeconds());
        $this->assertSame(10, $this->config->getCacheExpirationInSeconds());
    }

    public function testSetExpirationException()
    {
        $this->expectException(PageCacheException::class);
        $this->config->setCacheExpirationInSeconds(-1);
    }

    public function testSetEnableLogIsEnableLog()
    {
        $this->config->setEnableLog(true);
        $this->assertSame(true, $this->config->isEnableLog());
        $this->assertTrue($this->config->isEnableLog());

        $this->config->setEnableLog(false);
        $this->assertSame(false, $this->config->isEnableLog());
        $this->assertFalse($this->config->isEnableLog());
    }


    public function testSetGetMinCacheFileSize()
    {
        $this->config->setMinCacheFileSize(0);
        $this->assertSame(0, $this->config->getMinCacheFileSize());

        $this->config->setMinCacheFileSize(10000);
        $this->assertSame(10000, $this->config->getMinCacheFileSize());
    }

    public function testSetUseSessionIsUseSession()
    {
        //initially useSession is false
        $this->assertSame(false, $this->config->isUseSession());
        $this->config->setUseSession(true);
        $this->assertSame(true, $this->config->isUseSession());
        $this->assertTrue($this->config->isUseSession());
    }

    public function testSetGetSessionExclude()
    {
        $this->assertSame([], $this->config->getSessionExcludeKeys());
        $this->config->setSessionExcludeKeys([1, 2, 3]);
        $this->assertSame([1,2,3], $this->config->getSessionExcludeKeys());
    }

    public function testSetFileLock()
    {
        $this->config->setFileLock(LOCK_EX);
        $this->assertEquals(LOCK_EX, $this->config->getFileLock());

        $this->config->setFileLock(LOCK_EX | LOCK_NB);
        $this->assertEquals(LOCK_EX | LOCK_NB, $this->config->getFileLock());
    }

    public function testGetFileLock()
    {
        $this->assertSame(LOCK_EX | LOCK_NB, $this->config->getFileLock());
        $this->config->setFileLock(LOCK_EX);
        $this->assertSame(LOCK_EX, $this->config->getFileLock());
    }

    public function testSetGetCacheExpirationInSeconds()
    {
        $this->assertSame(1200, $this->config->getCacheExpirationInSeconds());
        $this->config->setCacheExpirationInSeconds(20);
        $this->assertSame(20, $this->config->getCacheExpirationInSeconds());
    }

    public function testGetPath()
    {
        $this->assertNull($this->config->getCachePath());
        $dir = __DIR__.'/';
        $this->config->setCachePath($dir);
        $this->assertSame($dir, $this->config->getCachePath());
    }

    public function testGetLogFilePath()
    {
        $path = 'somepath/to/file';
        $this->expectException(PageCacheException::class);
        $this->config->setLogFilePath($path);
    }

    public function testGetMinCacheFileSize()
    {
        $this->assertSame(10, $this->config->getMinCacheFileSize());
        $this->config->setMinCacheFileSize(10240);
        $this->assertSame(10240, $this->config->getMinCacheFileSize());
    }

    public function testLoadConfigurationFile()
    {
        $config = new Config(__DIR__.'/config_test.php');

        $this->assertSame(1, $config->getMinCacheFileSize());
        $this->assertSame(false, $config->isEnableLog());
        $this->assertSame(600, $config->getCacheExpirationInSeconds());
        $this->assertStringContainsString('/tmp/cache/', $config->getCachePath());
        $this->assertStringContainsString('/tmp', $config->getLogFilePath());
        $this->assertSame(false, $config->isUseSession());
        $this->assertSame([], $config->getSessionExcludeKeys());
        $this->assertSame(LOCK_EX | LOCK_NB, $config->getFileLock());
        $this->assertSame(false, $config->isForwardHeaders());
    }

    /**
     * Config expiration value is negative -> throws exception
     */
    public function testWrongConfigFile()
    {
        $this->expectException(PageCacheException::class);
        new Config(__DIR__.'/config_wrong_test.php');
    }
}
