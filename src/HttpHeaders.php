<?php
namespace PageCache;

/**
 * This file is part of the PageCache package.
 *
 * @author    Muhammed Mamedov <mm@turkmenweb.net>
 * @copyright 2016
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Class HttpHeaders
 *
 * @package PageCache
 */
class HttpHeaders
{
    const HEADER_EXPIRES         = 'Expires';
    const HEADER_LAST_MODIFIED   = 'Last-Modified';
    const HEADER_NOT_MODIFIED    = 'HTTP/1.1 304 Not Modified';
    const HEADER_ETAG            = 'ETag';
    const HTTP_IF_MODIFIED_SINCE = 'HTTP_IF_MODIFIED_SINCE';
    const HTTP_IF_NONE_MATCH     = 'HTTP_IF_NONE_MATCH';

    const DATE_FORMAT        = 'D, d M Y H:i:s';
    const DATE_FORMAT_CREATE = self::DATE_FORMAT . ' \G\M\T';
    const DATE_FORMAT_PARSE  = self::DATE_FORMAT . ' T';

    /**
     * Last modified time of the cache item
     *
     * @var \DateTime
     */
    private $itemLastModified;

    /**
     * @var \DateTime
     */
    private $itemExpiresAt;

    /**
     * @var string
     */
    private $itemETagString;

    /**
     * @var array
     */
    private $responseHeaders = [];

    /**
     * Set Last-Modified header
     *
     * @param \DateTime $lastModified
     *
     * @return $this
     */
    public function setLastModified(\DateTime $lastModified)
    {
        $this->itemLastModified = $lastModified;

        return $this;
    }

    /**
     * Set ETag header
     *
     * @param string $value
     *
     * @return $this
     */
    public function setETag($value)
    {
        $this->itemETagString = (string)$value;

        return $this;
    }

    /**
     * Set Expires header
     *
     * @param \DateTime $expirationTime
     *
     * @return $this
     */
    public function setExpires(\DateTime $expirationTime)
    {
        $this->itemExpiresAt = $expirationTime;

        return $this;
    }

    /**
     * Send Headers
     */
    public function send()
    {
        // Last-Modified
        if ($this->itemLastModified) {
            $this->setHeader(
                self::HEADER_LAST_MODIFIED,
                $this->itemLastModified->format(self::DATE_FORMAT_CREATE)
            );
        }

        // Expires
        if ($this->itemExpiresAt) {
            $this->setHeader(
                self::HEADER_EXPIRES,
                $this->itemExpiresAt->format(self::DATE_FORMAT_CREATE)
            );
        }

        // ETag
        if ($this->itemETagString) {
            $this->setHeader(
                self::HEADER_ETAG,
                $this->itemETagString
            );
        }
    }

    /**
     * @return array
     */
    public function getSentHeaders()
    {
        /** @link http://php.net/manual/ru/function.headers-list.php#120539 */
        return (PHP_SAPI === 'cli' && \function_exists('xdebug_get_headers'))
            ? xdebug_get_headers()
            : headers_list();
    }

    /**
     * Sends HTTP Header
     *
     * @param string      $name             Header name
     * @param string|null $value            Header value
     * @param int         $httpResponseCode HTTP response code
     */
    private function setHeader($name, $value = null, $httpResponseCode = null)
    {
        header($name . ($value ? ': ' . $value : ''), true, $httpResponseCode);
    }

    /**
     * Set Not Modified header, only if HTTP_IF_MODIFIED_SINCE was set or ETag matches
     * Content body is not sent when this header is set. Client/browser will use its local copy.
     *
     * When return is true, 304 header will be sent later and exit() will be called.
     *
     * @return  bool To end execution or not
     */
    public function checkIfNotModified()
    {
        $lastModifiedTimestamp = $this->itemLastModified->getTimestamp();
        $modifiedSinceTimestamp = $this->getIfModifiedSinceTimestamp();

        $notModified = false;

        // Do we have matching ETags
        if (!empty($_SERVER[self::HTTP_IF_NONE_MATCH])) {
            $notModified = $_SERVER[self::HTTP_IF_NONE_MATCH] === $this->itemETagString;
        }

        // Client's version older than server's?
        // If ETags matched ($notModified=true), we skip this step.
        // Because same hash means same file contents, no need to further check if-modified-since header
        if (!$notModified && $modifiedSinceTimestamp !== false) {
            $notModified = $modifiedSinceTimestamp >= $lastModifiedTimestamp;
        }

        return $notModified;
    }

    /**
     * Send 304 Not Modified header.
     * Keeping this separate because right after this is set, we exit().
     */
    public function sendNotModifiedHeader()
    {
        http_response_code(304);
        $this->setHeader(self::HEADER_NOT_MODIFIED);
    }

    /**
     * Get timestamp value from If-Modified-Since request header
     *
     * @return false|int Timestamp or false when header not found
     */
    private function getIfModifiedSinceTimestamp()
    {
        if (!empty($_SERVER[self::HTTP_IF_MODIFIED_SINCE])) {
            $modifiedSinceString = $_SERVER[self::HTTP_IF_MODIFIED_SINCE];
        } elseif (!empty($_ENV[self::HTTP_IF_MODIFIED_SINCE])) {
            $modifiedSinceString = $_ENV[self::HTTP_IF_MODIFIED_SINCE];
        } else {
            $modifiedSinceString = null;
        }

        if ($modifiedSinceString) {
            // Some versions of IE 6 append "; length=##"
            if (($pos = strpos($modifiedSinceString, ';')) !== false) {
                $modifiedSinceString = substr($modifiedSinceString, 0, $pos);
            }

            return strtotime($modifiedSinceString);
        }

        return false;
    }

    /**
     * @return bool|\DateTime
     */
    public function detectResponseLastModified()
    {
        $value = $this->detectResponseHeaderValue(self::HEADER_LAST_MODIFIED);

        return $value
            ? \DateTime::createFromFormat(self::DATE_FORMAT_PARSE, $value)
            : false;
    }

    /**
     * @return bool|\DateTime
     */
    public function detectResponseExpires()
    {
        $value = $this->detectResponseHeaderValue(self::HEADER_EXPIRES);

        return $value
            ? \DateTime::createFromFormat(self::DATE_FORMAT_PARSE, $value)
            : false;
    }

    /**
     * @return mixed|null
     */
    public function detectResponseETagString()
    {
        return $this->detectResponseHeaderValue(self::HEADER_ETAG);
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    private function detectResponseHeaderValue($name)
    {
        $headers = $this->getResponseHeaders();

        return isset($headers[$name]) ? $headers[$name] : null;
    }

    /**
     * Get headers and populate local responseHeaders variable
     *
     * @return array
     */
    private function getResponseHeaders()
    {
        if (!$this->responseHeaders) {
            foreach ($this->getSentHeaders() as $item) {
                list($key, $value) = explode(':', $item, 2);

                $this->responseHeaders[$key] = trim($value);
            }
        }

        return $this->responseHeaders;
    }

}
