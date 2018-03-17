<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Tests\Storage\FileSystem;

use PageCache\Storage\FileSystem\FileSystem;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * FileSystem is a file storage for cache.
 * Virtual directory structure is used in testing
 *
 * Class FileSystemTest
 * @package PageCache\Tests\Storage
 */
class FileSystemTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var  vfsStreamDirectory
     */
    private $virtualRoot;

    public function setUp()
    {
        $this->virtualRoot = vfsStream::setup('fileSystemDir');
    }

    private function getVirtualDirectory()
    {
        //setup virtual dir
        return vfsStream::url('fileSystemDir/');
    }

    public function testConstructor()
    {
        $fs = new FileSystem('somevalue');
        $this->assertAttributeEquals('somevalue', 'content', $fs);
        $this->assertAttributeEquals(null, 'file_lock', $fs);
    }

    public function testConst()
    {
        $this->assertEquals(1, FileSystem::OK);
        $this->assertEquals(2, FileSystem::ERROR);
        $this->assertEquals(3, FileSystem::ERROR_OPEN);
        $this->assertEquals(4, FileSystem::ERROR_WRITE);
        $this->assertEquals(5, FileSystem::ERROR_LOCK);
    }

    public function testSetFileLock()
    {
        $content = 'some content';
        $fs = new FileSystem($content);
        $this->assertAttributeEmpty('file_lock', $fs);

        $fs->setFileLock(LOCK_EX);
        $this->assertAttributeEquals(LOCK_EX, 'file_lock', $fs);

        $fs->setFileLock(LOCK_EX | LOCK_NB);
        $this->assertAttributeEquals(LOCK_EX | LOCK_NB, 'file_lock', $fs);
    }

    /**
     * @expectedException \Exception
     */
    public function testSetFileLockException()
    {
        $fs = new FileSystem('a');
        $fs->setFileLock('');
    }

    public function testSetFilePath()
    {
        $content = 'some content';
        $fs = new FileSystem($content);
        $this->assertAttributeEmpty('filepath', $fs);

        $path = '/some/path/to/cache/file/74bf6b958564c606d2672751fc82b8e6';
        $fs->setFilePath($path);
        $this->assertAttributeEquals($path, 'filepath', $fs);
    }

    /**
     * @expectedException \Exception
     */
    public function testSetFilePathException()
    {
        $fs = new FileSystem('a');
        $fs->setFilePath('');
    }

    /**
     * @depends testSetFilePath
     */
    public function testGetFilePath()
    {
        $fs = new FileSystem('aa');
        $path = '/file/74bf6b958564c606d2672751fc82b8e6';
        $fs->setFilePath($path);

        $this->assertEquals($path, $fs->getFilepath());
    }

    /**
     * @depends testSetFileLock
     */
    public function testGetFileLock()
    {
        $fs = new FileSystem('a');
        $this->assertEquals(null, $fs->getFileLock());

        $fs->setFileLock(LOCK_EX);
        $this->assertEquals(2, $fs->getFileLock());

        $fs->setFileLock(LOCK_EX | LOCK_NB);
        $this->assertEquals(6, $fs->getFileLock());
        $this->assertEquals(LOCK_EX | LOCK_NB, $fs->getFileLock());
    }

    public function testWriteAttempt()
    {
        $fpath = $this->getVirtualDirectory() . 'testfiletowrite.txt';

        $fs = new FileSystem('content');
        $result = $fs->writeAttempt();

        //no filepath, error
        $this->assertEquals(FileSystem::ERROR, $result);

        $this->assertFileNotExists($fpath);
        $fs->setFilePath($fpath);
        $result = $fs->writeAttempt();
        $this->assertEquals(FileSystem::OK, $result);
        $this->assertFileExists($fpath);
        $this->assertEquals('content', file_get_contents($fpath));
    }

    public function testWriteAttemptWithLock()
    {
        $fpath = $this->getVirtualDirectory() . 'testfiletowriteWithLock.txt';

        $fs = new FileSystem('content written with lock');
        $fs->setFilePath($fpath);
        $fs->setFileLock(LOCK_EX | LOCK_NB);

        $this->assertFileNotExists($fpath);
        $result = $fs->writeAttempt();
        $this->assertEquals(FileSystem::OK, $result);
        $this->assertFileExists($fpath);
        $this->assertEquals('content written with lock', file_get_contents($fpath));

        //multiple writes
        $result1 = $fs->writeAttempt();
        $result2 = $fs->writeAttempt();
        $result3 = $fs->writeAttempt();
        $this->assertEquals(FileSystem::OK, $result1);
        $this->assertEquals(FileSystem::OK, $result2);
        $this->assertEquals(FileSystem::OK, $result3);
        $this->assertEquals('content written with lock', file_get_contents($fpath));
    }

    /**
     * When directory/file are not writable and writeAttempt is being made.
     */
    public function testWriteAttemptForErrorOpen()
    {
        $fpath = $this->getVirtualDirectory() . 'SomeNewFile';

        //make base directory not writable
        chmod($this->getVirtualDirectory(), 0111);

        $fs = new FileSystem('write attempt');
        $fs->setFilePath($fpath);

        //@supress warning from fopen not being able to open file
        $this->assertEquals(FileSystem::ERROR_OPEN, @$fs->writeAttempt());
    }

    public function testPHPflock()
    {
        $fpath = $this->getVirtualDirectory() . 'testfiletowriteWithLock.txt';

        $fp = fopen($fpath, 'c');

        flock($fp, LOCK_EX);
        $new = fopen($fpath, 'c');

        flock($fp, LOCK_UN);

        if (flock($new, LOCK_EX)) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false, 'Lock was not obtained, it should have.');
        }
    }

    public function testPHPflockBlocking()
    {
        $fpath = $this->getVirtualDirectory() . 'testfiletowriteWithLock.txt';

        $fp = fopen($fpath, 'c');
        flock($fp, LOCK_EX);

        $new_fp = fopen($fpath, 'c');

        //since $fp, has Exclusive lock, no one else should have it
        $this->assertFalse(flock($new_fp, LOCK_EX));

        //release lock
        flock($fp, LOCK_UN);
        //now it should work
        $this->assertTrue(flock($new_fp, LOCK_EX));
    }
}
