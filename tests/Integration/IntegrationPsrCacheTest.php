<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Denis Terekhov <i.am@spotman.ru>
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Tests\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use PageCache\Storage\FileSystem\FileSystemCacheAdapter;

/**
 * Class IntegrationPsrCacheTest
 *
 * @package PageCache\Tests\Integration
 */
class IntegrationPsrCacheTest extends SimpleCacheTest
{
    public function createSimpleCache()
    {
        return new FileSystemCacheAdapter(
            __DIR__.DIRECTORY_SEPARATOR.'cache',
            LOCK_EX | LOCK_NB,
            500
        );
    }
}
