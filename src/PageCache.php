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

/**
 *
 * PageCache is the main class, create PageCache object and call init() to start caching
 *
 */
class PageCache
{
    private $cache_path;
    private $cache_expire;
    //full path of active cache file
    private $file;
    private $log_file_path;
    private $enable_log;
    private $strategy;
    private $config;
    //regenerate cache if cached content is less that this many bytes (some error occured)
    private $min_cache_file_size;
    //make sure only one instance of PageCache is created
    private static $ins = null;

    /**
     * PageCache constructor.
     */
    public function __construct($config_file_path = null)
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
        } else {
            /**
             * config file not found, set defaults
             */

            //in 20 minutes cache expires
            $this->cache_expire = 1200;

            //min file size is 10 bytes, generated files less than this value are invalid, renegerated
            $this->min_cache_file_size = 10;

            //do not use $_SESSION in cache, by default
            SessionHandler::disable();
        }

        PageCache::$ins = true;

        //default file naming strategy
        $this->strategy = new Strategy\DefaultStrategy();
    }

    /**
     * Initialize cache.
     * If you need to set configuration options, do so before calling this method.
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
            $this->log(array('msg' => 'File not found or cache expired or min_cache_file_size not met.'));
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
     * Generates cache file name based on URL, Strategy, and SessionHandler
     *
     */
    private function generateCacheFile()
    {
        //cache file name already generated?
        if(!empty($this->file)) {
            return;
        }

        $fname = $this->strategy->strategy();

        $hashDirectory = new HashDirectory($fname, $this->cache_path);
        $dir_str = $hashDirectory->getHash();

        $this->file = $this->cache_path . $dir_str . $fname;

        $this->log(array('msg' => 'Cache file: ' . $this->file));
    }

    /**
     * Clear cache for current page, if this page was cached before.
     */
    public function clearPageCache(){

        //if cache file name not set yet, get it
        if(empty($this->file)){
            $this->generateCacheFile();
        }

        /**
         * Cache file name is now available, check if cache file exists.
         * If init() wasn't called on this page before, there won't be any cache saved, so we check with file_exists.
         */
        if(file_exists($this->file)){
            $this->log(array('msg'=>'PageCache: page cache file found, deleting now.'));
            unlink($this->file);
        }
    }

    /**
     * Return current page cache as a string or false on error, if this page was cached before.
     */
    public function getPageCache(){

        //if cache file name not set yet, get it
        if(empty($this->file)){
            $this->generateCacheFile();
        }

        if( false!== $str = file_get_contents($this->file)){
            return $str;
        }
        else {
            $this->log(array('msg'=>'PageCache: getPageCache() could not open cache file'));
        }

        return false;
    }

    /**
     * Get current page's cache file name.
     *
     * @return string cache file
     */
    public function getFile(){

        //if cache file name not set yet, get it
        if(empty($this->file)){
            $this->generateCacheFile();
        }

        return $this->file;
    }

    /**
     * Location of cache files directory.
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

    /**
     * Time in seconds for cache to expire
     *
     * @param $seconds int seconds
     * @throws \Exception
     */
    public function setExpiration($seconds)
    {
        if ($seconds < 0) {
            throw new \Exception('PageCache: invalid expiration value, < 0.');
        }

        $this->cache_expire = intval($seconds);
    }

    /**
     * Set Log file path.
     *
     * @param $path string log file path
     *
     */
    public function logFilePath($path)
    {
        $this->log_file_path = $path;
    }

    /**
     * Enable logging.
     */
    public function enableLog()
    {
        $this->enable_log = true;
    }

    /**
     * Disable logging.
     */
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
     * Use sessions when caching page.
     * For the same URL session enabled page might be displayed differently, when for example user has logged in.
     */
    public function enableSession(){
        SessionHandler::enable();
    }

    /**
     * Do not use sessions when caching page.
     */
    public function disableSession(){
        SessionHandler::disable();
    }

    /**
     * Exclude $_SESSION key(s) from caching strategies.
     * 
     * When to use: Your application changes $_SESSION['count'] variable, but that doesn't reflect on the page
     *              content. Exclude this variable, otherwise PageCache will generate seperate cache files for each
     *              value of $_SESSION['count] session variable.
     *
     * @param array $keys $_SESSION keys to exclude from caching strategies
     */
    public function sessionExclude(array $keys){
            SessionHandler::excludeKeys($keys);
    }


    /**
     * Parses conf.php files and sets parameters for this object
     *
     * @param array $config
     * @throws \Exception min params not set
     */
    private function parseConfig(array $config)
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

        //use $_SESSION while caching or not
        if(isset($this->config['use_session'])){
            SessionHandler::setStatus($this->config['use_session']);
        }

        //session exclude key
        if (isset($this->config['session_exclude_keys']) && !empty($this->config['session_exclude_keys'])) {
            SessionHandler::excludeKeys($this->config['session_exclude_keys']);
        }

    }

    /**
     * Logs to a file specified in log_file_path, only when loging is enabled
     *
     * @param array $params loging values
     * @throws \Exception
     */
    private function log(array $params)
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