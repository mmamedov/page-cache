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

class FileSystemFiveTest2 extends \PHPUnit_Framework_TestCase
{
    public function testWriteAttemptWithLock()
    {
        $fpath = __DIR__ . '/../../tmp/paratestUsingLock.txt';

        $fs = new FileSystem('content written with lock Paratest');
        $fs->setFilePath($fpath);
        $fs->setFileLock(LOCK_EX);

        $result = $fs->writeAttempt();
        $this->assertEquals(FileSystem::OK, $result);
    }

    private function deleteFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
