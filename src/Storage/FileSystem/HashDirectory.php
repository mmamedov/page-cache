<?php
/**
 * This file is part of the PageCache package.
 *
 * @author    Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2016
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Storage\FileSystem;

use PageCache\PageCacheException;

/**
 * Class HashDirectory.
 * HashDirectory creates subdirectories where cache files are stored, based on cache file name.
 *
 * @package PageCache
 */
class HashDirectory
{
    /**
     * Filename
     *
     * @var string|null
     */
    private $file;

    /**
     * Directory where filename will be stored.
     * Subdirectories are going to be created inside this directory, if necessary.
     *
     * @var string|null
     */
    private $dir;

    /**
     * Filesystem permissions in octal form
     * Server umask must be configured properly to prevent data leakage
     *
     * @var int
     */
    private $directoryPermissions = 0777;

    /**
     * HashDirectory constructor.
     *
     * @param string|null $file
     * @param string|null $dir
     *
     * @throws \PageCache\PageCacheException
     */
    public function __construct($file = null, $dir = null)
    {
        $this->setDir($dir);
        $this->setFile($file);
    }

    /**
     * Set directory
     *
     * @param string|null $dir
     *
     * @throws \PageCache\PageCacheException
     */
    public function setDir($dir)
    {
        if (empty($dir) || !@is_dir($dir)) {
            throw new PageCacheException(__METHOD__ . ': ' . (string)$dir . ' in constructor is not a directory');
        }

        // Check for trailing slash and add it if not exists
        if (mb_substr($dir, -1, 1) !== DIRECTORY_SEPARATOR) {
            $dir .= DIRECTORY_SEPARATOR;
        }

        $this->dir = $dir;
    }

    /**
     * Set file
     *
     * @param string|null $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * Get full file path for a provided filename
     *
     * @param mixed $file cache key
     *
     * @return string path
     */
    public function getFullPath($file)
    {
        $this->setFile($file);
        return $this->dir . $this->getHash() . $file;
    }

    /**
     * Based on incoming string (filename) return 2 directories to store cache file.
     * If directories(one or both) not present create whichever is not there yet.
     *
     * Returns null if $this->file or $this->dir is not set.
     *
     * @return string|null with two directory names like '10/55/', ready to be appended to cache_dir
     */
    public function getHash()
    {
        if (empty($this->file) || empty($this->dir)) {
            return null;
        }

        $path = $this->getDirectoryPathByHash($this->file);

        // Create directories
        $this->createSubDirs($path);
        return $path;
    }

    /**
     *  Inside $this->dir (Cache Directory), create 2 sub directories to store current cache file
     *
     * @param string $path Relative directory path
     *
     * @throws \PageCache\PageCacheException directories not created
     */
    private function createSubDirs($path)
    {
        $fullPath = $this->dir . $path;

        if (!\file_exists($fullPath) && !mkdir($fullPath, $this->directoryPermissions, true) && !is_dir($fullPath)) {
            throw new PageCacheException(__METHOD__ . ': ' . $fullPath . ' cache directory could not be created');
        }
    }

    /**
     * Get subdirectories for location of where cache file would be placed.
     * Returns null when filename is empty, otherwise 2 subdirectories where filename would be located.
     *
     * @param string $filename Cache file name
     *
     * @return null|string null
     */
    public function getLocation($filename)
    {
        return empty($filename) ? null : $this->getDirectoryPathByHash($filename);
    }

    /**
     * Get a path with 2 directories, based on filename hash
     *
     * @param string $filename
     *
     * @return string directory path
     */
    private function getDirectoryPathByHash($filename)
    {
        //get 2 number
        $val1 = ord($filename[1]);
        $val2 = ord($filename[3]);

        //normalize to 99
        $val1 %= 99;
        $val2 %= 99;
        return $val1 . '/' . $val2 . '/';
    }

    /**
     * Removes all files and directories inside a directory.
     * Used for deleting all cache content.
     *
     * @param string $dir
     *
     * @return bool
     */
    public function clearDirectory($dir)
    {
        if (empty($dir) || !@is_dir($dir)) {
            return false;
        }

        $iterator = new \RecursiveDirectoryIterator($dir);
        $filter = new \RecursiveCallbackFilterIterator($iterator, function ($current) {
            /** @var \SplFileInfo $current */
            $filename = $current->getBasename();
            // Check for files and dirs starting with "dot" (.gitignore, etc)
            return !($filename && $filename[0] === '.');
        });

        /** @var \SplFileInfo[] $listing */
        $listing = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($listing as $item) {
            $path = $item->getPathname();
            $item->isDir() ? rmdir($path) : unlink($path);
        }
        return true;
    }
}
