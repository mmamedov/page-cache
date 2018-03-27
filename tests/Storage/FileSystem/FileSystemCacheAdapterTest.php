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

use PageCache\Storage\FileSystem\FileSystemCacheAdapter;

/**
 * Class FileSystemCacheAdapterTest
 * @package PageCache\Tests\Storage\FileSystem
 */
class FileSystemCacheAdapterTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @throws \Exception
     * @throws \PageCache\PageCacheException
     * @throws \PageCache\Storage\CacheAdapterException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @dataProvider invalidKeys
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testInvalidCacheKey($key)
    {
        $fileAdapter = new FileSystemCacheAdapter(__DIR__ . '/../../tmp', LOCK_EX, 0);
        $fileAdapter->set($key, '1');
    }

    /**
     * @throws \Exception
     * @throws \PageCache\PageCacheException
     * @throws \PageCache\Storage\CacheAdapterException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @dataProvider validKeys
     *
     */
    public function testValidCacheKey($key)
    {
        $fileAdapter = new FileSystemCacheAdapter(__DIR__ . '/../../tmp', LOCK_EX, 0);
        $fileAdapter->set($key, '1');
    }

    /**
     * @throws \Exception
     * @throws \PageCache\PageCacheException
     * @throws \PageCache\Storage\CacheAdapterException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @dataProvider validKeys
     *
     */
    public function testHas($key)
    {
        $fileAdapter = new FileSystemCacheAdapter(__DIR__ . '/../../tmp', LOCK_EX, 0);
        $fileAdapter->set($key, '1');

        $this->assertTrue($fileAdapter->has($key));
    }

    /**
     * @throws \Exception
     * @throws \PageCache\PageCacheException
     * @throws \PageCache\Storage\CacheAdapterException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @dataProvider validKeys
     *
     */
    public function testGet($key)
    {
        $fileAdapter = new FileSystemCacheAdapter(__DIR__ . '/../../tmp', LOCK_EX, 0);
        $fileAdapter->set($key, 'RandomValue');

        $this->assertSame('RandomValue', $fileAdapter->get($key));
    }

    public function invalidKeys()
    {
        return [
            ['**Dasf'],
            ['==asdfdasf'],
            ['[[['],
            ['~~!#$GFDSAS'],
            ['']
        ];
    }

    public function validKeys()
    {
        return [
            ['asdfasdfasdf'],
            ['ASDFAFKASLPQWE'],
            ['MNDJDSJaadoikekk1230988813'],
            ['OASDFHabsdfgajskdf123098689-mob'],
            ['234324.FKKAjdld-2341_PIYNx']
        ];
    }

}
