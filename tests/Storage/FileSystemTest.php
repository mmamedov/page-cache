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
    public function testWriteAttempt()
    {

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
        $this->assertAttributeEquals(null, 'file_lock', $fs);
        $this->assertEquals(null, $fs->getFileLock());

        $fs->setFileLock(LOCK_EX);
        $this->assertEquals(2, $fs->getFileLock());
    }
}
