<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Denis Terekhov <i.am@spotman.ru>
 * @package PageCache
 * @copyright 2017
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache;

use Psr\Log\AbstractLogger;

class DefaultLogger extends AbstractLogger
{
    private $file;

    /**
     * DefaultLogger constructor.
     *
     * @param $file
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $exception = isset($context['exception']) ? $context['exception'] : null;
        $microTime = microtime(true);
        $micro = sprintf("%06d", ($microTime - floor($microTime)) * 1000000);
        $logTime = (new \DateTime(date('Y-m-d H:i:s.' . $micro, $microTime)))->format('Y-m-d H:i:s.u');
        error_log(
            '[' . $logTime . '] '
            .$message.($exception ? ' {Exception: '.$exception->getMessage().'}' : '')."\n",
            3,
            $this->file,
            null
        );
    }
}
