<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2017
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache;

/**
 * Class HttpHeader
 * @package PageCache
 */
class HttpHeader
{
    const HEADER_EXPIRES = 'Expires';
    const HEADER_LAST_MODIFIED = 'Last-Modified';
    const HEADER_NOT_MODIFIED = 'HTTP/1.1 304 Not Modified';
    const HEADER_ETAG = 'ETag';
    const HTTP_IF_MODIFIED_SINCE = 'HTTP_IF_MODIFIED_SINCE';
    const HTTP_IF_NONE_MATCH = 'HTTP_IF_NONE_MATCH';

    /**
     * @var bool Whether we are sending headers or no
     */
    private $enable_headers = false;

    /**
     * Full path of the current cache file
     *
     * @var string
     */
    private $file = '';

    /**
     * Last modified timestamp of the file
     *
     * @var int
     */
    private $last_modified_time = 0;

    /**
     * Set Last-Modified header
     */
    public function setLastModified()
    {
        $this->setHeader(
            self::HEADER_LAST_MODIFIED,
            (new \DateTime())
                ->setTimestamp($this->last_modified_time)
                ->format('D, d M Y H:i:s') . ' GMT'
        );
    }

    /**
     * Set Not Modified header, only if HTTP_IF_MODIFIED_SINCE was set or ETag matches
     * Content body is not sent when this header is set. Client/browser will use its local copy.
     */
    public function setNotModified()
    {
        if (!$this->enable_headers) {
            return;
        }
        $modifiedSinceTimestamp = $this->getIfModifiedSinceTimestamp();

        $notModified = false;

        //Do we have matching ETags
        if (!empty($_SERVER[self::HTTP_IF_NONE_MATCH])) {
            $notModified = $_SERVER[self::HTTP_IF_NONE_MATCH] == $this->getEtagString();
        }

        // Client's version older than server's?
        // If ETags matched ($notModified=true), we skip this step.
        // Because same hash means same file contents, no need to further check if-modified-since header
        if ($notModified) {
            $notModified = $modifiedSinceTimestamp !== false && $modifiedSinceTimestamp >= $this->last_modified_time;
        }

        if ($notModified) {
            $this->setHeader(self::HEADER_NOT_MODIFIED);
            exit();
        }
    }

    /**
     * Set ETag headers, based on contents of the file
     */
    public function setEtag()
    {
        $this->setHeader(
            self::HEADER_ETAG,
            $this->getEtagString()
        );
    }

    /**
     * Get hash for ETag
     *
     * @return string
     */
    private function getEtagString()
    {
        return '"'.sha1_file($this->file).'"';
    }

    /**
     * Set Expires header
     *
     * @param int $expirationTime
     */
    public function setExpires($expirationTime)
    {
        $this->setHeader(
            self::HEADER_EXPIRES,
            (new \DateTime())->setTimestamp($expirationTime)->format('D, d M Y H:i:s') . ' GMT'
        );
    }

    /**
     * Get timestamp value from If-Modified-Since header
     *
     * @return false|int Timestamp or false when header not found
     */
    private function getIfModifiedSinceTimestamp()
    {
        if (!empty($_SERVER[self::HTTP_IF_MODIFIED_SINCE])) {
            $mod_time = $_SERVER[self::HTTP_IF_MODIFIED_SINCE];
            // Some versions of IE 6 append "; length=##"
            if (($pos = strpos($mod_time, ';')) !== false) {
                $mod_time = substr($mod_time, 0, $pos);
            }
            return strtotime($mod_time);
        }
        return false;
    }

    /**
     * Sends HTTP Header
     *
     * @param string $name Header name
     * @param string|null $value Header value
     * @param int $http_reponse_code HTTP response code
     */
    private function setHeader($name, $value = null, $http_reponse_code = null)
    {
        if ($this->enable_headers) {
            header($name . ($value ? ': ' . $value : ''), true, $http_reponse_code);
        }
    }

    /**
     * Enable or disable headers
     *
     * @param bool $enable
     */
    public function enableHeaders($enable)
    {
        $this->enable_headers = $enable ? true : false;
    }

    /**
     * Returns whether headers are enabled or not
     *
     * @return bool
     */
    public function isEnabledHeaders()
    {
        return $this->enable_headers;
    }

    /**
     * Set file
     *
     * @param string $file
     */
    public function setFile($file)
    {
        $this->file = $file;
        $this->last_modified_time = filemtime($this->file);
    }
}
