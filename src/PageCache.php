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

use PageCache\Strategy;

class PageCache
{
    private $cache_path;
    private $cache_expire;
    private $file;
    private $log_file_path;
    private $enable_log;
    private $strategy;
    private $config;
    private $config_file_path;

    //regenerate cache if cached content is less that this many bytes (some error occured)
    private $min_cache_file_size;

    //make sure only one instance of PageCache is created
    private static $ins = null;

    /**
     * PageCache constructor.
     */
    public function __construct($config_file_path=null)
    {
        if (isset(PageCache::$ins)) {
            throw new \Exception('PageCache already created.');
        }

        /**
         * load configuration file
         */
        if (!is_null($config_file_path) && file_exists($config_file_path)) {
            $config = null;
            include $config_file_path;

            $this->parseConfig($config);
        }
        else{
            /**
             * config file not found, set defaults
             */

            //in 20 minutes cache expires
            $this->cache_expire = 1200;

            //min file size is 10 bytes, generated files less than this value are invalid, renegerated
            $this->min_cache_file_size = 10;

        }

        PageCache::$ins = true;

        //default file naming strategy
        $this->strategy = new Strategy\DefaultStrategy();
    }

    /**
     * Initialize cache.
     */
    public function init()
    {

        $this->log(array('msg' => "\n" . date('d/m/Y H:i:s') . ' init() uri:' . $_SERVER['REQUEST_URI'] . '; script:' . $_SERVER['SCRIPT_NAME'] . '; query:' . $_SERVER['QUERY_STRING']));

        $this->generateCacheFile();

        $this->display();

        ob_start(array($this, 'createPage'));

    }

    /**
     * Fetch cache and display it.
     *
     * If cache file not found or not valid, function returns, and init() continues with cache generation(createPage())
     */
    private function display()
    {

        $file = $this->file;

        if (!file_exists($file) || (filemtime($file) < (time() - $this->cache_expire)) || filesize($file) < $this->min_cache_file_size) {
            $this->log(array('msg' => 'File not found.'));
            return;
        }

        $this->log(array('msg' => 'File found for cache output.'));

        readfile($file);

        //stop execution
        exit();
    }

    /**
     * Write page to cache, and display it.
     *
     * @param $content string from ob_start
     * @return string page content
     */
    private function createPage($content)
    {

        $file = $this->file;

        $fp = fopen($file, 'w');

        //open cache file
        if ($fp === false) {
            //file open,create error
            $this->log(array('msg' => 'fopen() file open error.'));
        } else {
            //write to cache file
            if (fwrite($fp, $content) === false) {
                //file write error
                $this->log(array('msg' => 'fwrite() file write error.'));
            }
            fclose($fp);
        }

        $this->log(array('msg' => 'Cache saved OK: ' . $this->file));

        return $content;
    }


    /**
     * Caching strategy - expected file name for this current page.
     *
     * @param StrategyInterface $strategy object for choosing appropriate cache file name
     */
    public function setStrategy(StrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     *
     * Generates cache file name based on URL (and mobiledetect when enabled)
     *
     */
    private function generateCacheFile()
    {

        $fname = $this->strategy->strategy();

        $hashDirectory = new HashDirectory($fname, $this->cache_path);
        $dir_str = $hashDirectory->getHash();

        $this->file = $this->cache_path . $dir_str . $fname;

        $this->log(array('msg' => 'Cache file: ' . $this->file));
    }

    /**
     * Location of cache files.
     *
     * @param $path string full path of cache files
     * @throws \Exception
     */
    public function setPath($path)
    {

        //path writable?
        if (empty($path) || !is_writable($path)) {
            throw new \Exception('PageCache: cache path not writable.');
        }

        $this->cache_path = $path;
    }

    public function setExpiration($seconds)
    {

        if ($seconds < 0) {
            throw new \Exception('PageCache: invalid expiration value, < 0.');
        }

        $this->cache_expire = intval($seconds);
    }

    /**
     * Set Log file path
     *
     * @param $path string log file path
     *
     */
    public function logFilePath($path)
    {
        $this->log_file_path = $path;
    }

    /**
     * Enable loging
     */
    public function enableLog()
    {
        $this->enable_log = true;
    }

    public function disableLog()
    {
        $this->enable_log = false;
    }


    /**
     * When generated cache file is less that this size, it is considered as invalid (will be regenerated on next call)
     *
     * @param $min_cache_file_size int bytes for filename
     */
    public function setMinCacheFileSize($min_cache_file_size)
    {
        $this->min_cache_file_size = $min_cache_file_size;
    }

    /**
     * Parses conf.php files and sets parameters for this object
     *
     * @param array $config
     * @throws \Exception min params not set
     */
    public function parseConfig(array $config)
    {

        $this->config = $config;

        if (!isset($this->config['min_cache_file_size']) || !isset($this->config['enable_log'])) {
            throw new \Exception('PageCache config:  min_cache_file_size or enable_log params not set.');
        }

        //minimum cache file size bytes
        $this->min_cache_file_size = intval($this->config['min_cache_file_size']);

        $this->enable_log = boolval($this->config['enable_log']);

        //cache expiration in seconds
        if (isset($this->config['expiration'])) {

            if ($this->config['expiration'] < 0) {
                throw new \Exception('PageCache config: invalid expiration value, < 0.');
            }

            $this->cache_expire = intval($this->config['expiration']);
        }

        //path of log file, has effect only if log in enabled.
        if (isset($this->config['log_file_path'])) {
            $this->log_file_path = $this->config['log_file_path'];
        }

        //path to store cache files
        if (isset($this->config['cache_path'])) {

            //path writable?
            if (empty($this->config['cache_path']) || !is_writable($this->config['cache_path'])) {
                throw new \Exception('PageCache config: cache path not writable ');
            }

            $this->cache_path = $this->config['cache_path'];
        }

    }

    /**
     * Logs to a file specified in log_file_path, only when loging is enabled
     *
     * @param array $params loging values
     * @throws \Exception
     */
    public function log(array $params)
    {
        if ($this->enable_log !== true) {
            return;
        }

        if (!isset($params['msg'])) {
            throw new \Exception('PageCache: log msg missing');
        }

        if (empty($this->log_file_path)) {
            throw new \Exception('PageCache: log file path empty');
        }

        error_log($params['msg'] . "\n", 3, $this->log_file_path, null);
    }

}