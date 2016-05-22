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

use PageCache\HashDirectory;

class HashDirectoryTest extends \PHPUnit_Framework_TestCase
{

    private $dir;
    private $filename;

    /** @var  HashDirectory */
    private $hd;

    public function setUp()
    {
        $this->dir = __DIR__ . '/tmp/';
        $this->filename = '18a3938de0087a87d3530084cd46edf4';
        $this->hd = new HashDirectory($this->filename, $this->dir);
    }

    public function tearDown()
    {
        unset($this->hd);

        //delete directories
        if (is_dir($this->dir . '56/51')) {
            rmdir($this->dir . '56/51');
        }
        if (is_dir($this->dir . '56')) {
            rmdir($this->dir . '56');
        }

        if (is_dir($this->dir . '51/48')) {
            rmdir($this->dir . '51/48');
        }
        if (is_dir($this->dir . '51')) {
            rmdir($this->dir . '51');
        }
    }

    public function testGetHash()
    {
        $val1 = ord('8'); //56
        $val2 = ord('3'); //51

        //normalize to 99
        $val1 = $val1 % 99; //56
        $val2 = $val2 % 99; //51

        $returned = $val1 . '/' . $val2 . '/';

        $this->assertEquals($returned, $this->hd->getHash());
        $this->assertFileExists($this->dir . '56/51');
        $this->assertEquals($returned, $this->hd->getLocation($this->filename));

        //new object
        $newFilename = '93f0938de0087a87d3530084cd46edf4';
        $newHd = new HashDirectory($newFilename, $this->dir);

        $this->assertFileNotExists($this->dir . '51/48');
        $this->assertEquals('51/48/', $newHd->getHash());
        $this->assertAttributeEquals('93f0938de0087a87d3530084cd46edf4', 'file', $newHd);
        $this->assertFileExists($this->dir . '51/48');
    }

    public function testGetLocation()
    {
        $this->assertEquals('56/51/', $this->hd->getLocation($this->filename));
    }

    /**
     * @expectedException \Exception
     */
    public function testConstructorException()
    {
        $new = new HashDirectory('false file', 'false directory');
    }
}
