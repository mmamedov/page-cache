<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Denis Terekhov <i.am@spotman.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Storage;

use Psr\SimpleCache\CacheException;

class CacheAdapterException extends \Exception implements CacheException
{
}
