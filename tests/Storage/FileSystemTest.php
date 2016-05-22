<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Tests\Storage;

use PageCache\Storage\FileSystem;

class FileSystemTest extends \PHPUnit_Framework_TestCase
{
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
        $fpath = __DIR__ . '/../tmp/testfiletowrite.txt';
        $this->deleteFile($fpath);

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

        //clean up
        $this->deleteFile($fpath);
    }

    public function testWriteAttemptWithLock()
    {
        $fpath = __DIR__ . '/../tmp/testfiletowriteWithLock.txt';
        $this->deleteFile($fpath);

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

        //clean up
        $this->deleteFile($fpath);
    }

    public function testPHPflock()
    {
        $fpath = __DIR__ . '/../tmp/testfiletowriteWithLock.txt';
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

    private function deleteFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
