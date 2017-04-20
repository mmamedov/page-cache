<?php
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
        $dir = dirname($this->file);

        if (!file_exists($dir)) {
            throw new PageCacheException('Log file directory does not exists '.$dir);
        }
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

        error_log(
            '['.date('Y-m-d H:i:s').'] '
            .$message.($exception ? ' {Exception: '.$exception->getMessage().'}' : '')."\n",
            3,
            $this->file,
            null
        );
    }
}
