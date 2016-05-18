<?php

/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache;

/**
 *
 * HashDirectory creates subdirectories where cache files are stored, based on cache file name.
 *
 */
class HashDirectory
{
    /**
     * Filename
     *
     * @var string
     */
    private $file;

    /**
     * Directory where filename will be stored.
     * Subdirectories are going to be created inside this directory, if necessary.
     *
     * @var string
     */
    private $dir;

    public function __construct($file, $dir)
    {
        if (!@is_dir($dir)) {
            throw new \Exception(__CLASS__.' dir in constructor is not a directory');
        }
        
        $this->dir = $dir;
        $this->file = $file;
    }

    /**
     *  Based on incoming string (filename) return 2 directories to store cache file.
     *  If directories(one or both) not present create whichever is not there yet.
     *
     * @return string with two directory names like '10/55/', ready to be appended to cache_dir
     */
    public function getHash()
    {
        //get two numbers
        $val1 = ord($this->file[1]);
        $val2 = ord($this->file[3]);

        //normalize to 99
        $val1 = $val1 % 99;
        $val2 = $val2 % 99;

        //create directories
        $this->createSubDirs($val1, $val2);

        return $val1 . '/' . $val2 . '/';
    }

    /**
     *  Inside $this->dir (Cache Directory), create 2 sub directories to store current cache file
     *
     *
     * @param $dir1 string directory
     * @param $dir2 string directory
     * @return null;
     * @throws \Exception directories not created
     */
    private function createSubDirs($dir1, $dir2)
    {
        //dir1 not exists, create both
        if (!@is_dir($this->dir . $dir1)) {
            mkdir($this->dir . $dir1);
            mkdir($this->dir . $dir1 . '/' . $dir2);
        } else {
            //dir1 exists
            if (!@is_dir($this->dir . $dir1 . '/' . $dir2)) {
                mkdir($this->dir . $dir1 . '/' . $dir2);
            }
        }

        //check
        if (!@is_dir($this->dir . $dir1 . '/' . $dir2)) {
            throw new \Exception(__CLASS__.' ' . $dir1 . '/' . $dir2
                . ' cache directory could not be created');
        }

        return null;
    }

    /**
     * Get subdirectories for location of where cache file would be placed
     *
     * @param $filename
     * @return null|string null when filename is empty, or 2 subdirectories where filename would be located.
     */
    public static function getLocation($filename)
    {
        if (empty($filename)) {
            return null;
        }

        $val1 = ord($filename[1]);
        $val2 = ord($filename[3]);

        //normalize to 99
        $val1 = $val1 % 99;
        $val2 = $val2 % 99;

        return $val1 . '/' . $val2 . '/';
    }
}
