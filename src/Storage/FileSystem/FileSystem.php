<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Storage\FileSystem;

use PageCache\PageCacheException;

/**
 *
 * File system storage for cache, page cache is saved into file.
 *
 * Class FileSystem
 * @package PageCache\Storage
 *
 */
class FileSystem
{
    /**
     * Content to be written into a file
     *
     * @var string
     */
    private $content = null;

    /**
     * File lock to be used when writing. Support default PHP flock() parameters.
     *
     * @var int|null
     */
    private $file_lock = null;

    /**
     * File path where to write
     *
     * @var string
     */
    private $filepath = null;

    /**
     * writeAttempt successful
     */
    const OK = 1;

    /**
     *  writeAttempt parameters error
     */
    const ERROR = 2;

    /**
     * writeAttempt fopen() error
     */
    const ERROR_OPEN = 3;

    /**
     * writeAttempt fwrite() error
     */
    const ERROR_WRITE = 4;

    /**
     * writeAttempt failed to aquire lock on the file.
     */
    const ERROR_LOCK = 5;

    /**
     * FileSystem constructor.
     * @param string $content page content to be written into file
     */
    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * Attempt to write into the file. If lock is not acquired, file is not written.
     *
     * @return bool
     * @throws \Exception
     */
    public function writeAttempt()
    {
        $result = self::OK;

        if (empty($this->filepath)) {
            return self::ERROR;
        }

        /**
         * Open the file for writing only. If the file does not exist, it is created.
         * If it exists, it is not truncated (as opposed to 'w'). File pointer is moved to beginning.
         *
         * "c" is needed instead of "w", because if lock is not acquired, old version of the file is returned.
         * If "w" option is used, file is truncated no matter what, and empty file is returned.
         */
        $fp = fopen($this->filepath, 'c');

        if ($fp === false) {
            return self::ERROR_OPEN;
        }

        /**
         * File locking disabled?
         */
        if (empty($this->file_lock)) {
            ftruncate($fp, 0);
            if (fwrite($fp, $this->content) === false) {
                $result = self::ERROR_WRITE;
            }
            fclose($fp);

            return $result;
        }

        /**
         * File locking is enabled
         *
         * Try to acquire File Write Lock. If lock is not acquired read file and return old contents.
         *
         * Recommended is: LOCK_EX | LOCK_NB.
         * LOCK_EX to acquire an exclusive lock (writer).
         * LOCK_NB - prevents flock() from blocking while locking, so that others could still read
         * (but not write) while lock is active
         */
        if (flock($fp, $this->file_lock)) {
            /**
             * since "c" was used with fopen, file is not truncated. Truncate manually.
             */
            ftruncate($fp, 0);

            //write content
            if (fwrite($fp, $this->content) === false) {
                $result = self::ERROR_WRITE;
            }

            //release lock
            flock($fp, LOCK_UN);
        } else {
            /**
             * Lock was not granted.
             */

            $result = self::ERROR_LOCK;
        }

        fclose($fp);

        return $result;
    }

    /**
     * Set file locking logic
     *
     * @param false|int $file_lock PHP file lock constant or false for disabling
     * @throws \Exception
     */
    public function setFileLock($file_lock)
    {
        if (empty($file_lock) && $file_lock !== false) {
            throw new PageCacheException(__CLASS__ . ' file lock can not be empty. To disable set to boolean false.');
        }
        $this->file_lock = $file_lock;
    }

    /**
     * Get file lock value
     *
     * @return int|null
     */
    public function getFileLock()
    {
        return $this->file_lock;
    }

    /**
     * Get filepath of file to be written
     *
     * @return null|string
     */
    public function getFilepath()
    {
        return $this->filepath;
    }

    /**
     * Set filepath of the cache file.
     *
     * @param string $filepath cache file path
     * @throws \Exception
     */
    public function setFilePath($filepath)
    {
        if (!isset($filepath) || empty($filepath)) {
            throw new PageCacheException(__CLASS__ . ' file path not set or empty');
        }

        $this->filepath = $filepath;
    }
}
