<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Service;

/**
 * File cache service
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class FileCacheService
{
    private $cacheDirectory;
    private $metaData;
    private $dirty = false;

    /**
     * Constructor
     *
     * @param string $cacheDirectory
     */
    public function __construct($cacheDirectory)
    {
        $this->cacheDirectory = $cacheDirectory;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (!$this->dirty) {
            return;
        }

        $metaFile = $this->cacheDirectory . '/cache.meta';

        file_put_contents($metaFile, serialize($this->metaData));
    }

    /**
     * Load cache meta data
     */
    private function loadCacheMetaData()
    {
        if (isset($this->metaData)) {
            return;
        }

        $metaFile = $this->cacheDirectory . '/cache.meta';
        $this->metaData = array();

        if (file_exists($metaFile)) {
            $this->metaData = unserialize(file_get_contents($metaFile));
        }
    }

    /**
     * Get cache keys
     *
     * @return array
     */
    public function getKeys()
    {
        if (!isset($this->cacheDirectory)) {
            return array();
        }

        $this->loadCacheMetaData();

        return array_keys($this->metaData);
    }

    /**
     * Convert RFC 3339 timestamp string to Unix timestamp
     *
     * @param string $dateTime
     *
     * @return integer
     */
    public function convertToUnixTimestamp($dateTime)
    {
        // ignore fractional second
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:|\.\d+)(Z|[+-]\d{2}:\d{2})$/', $dateTime, $matches)) {
            $timestamp = gmmktime(
                $matches[4], $matches[5], $matches[6], // hours, minutes, seconds
                $matches[2], $matches[3], $matches[1]  // month, day, year
            );

            // local offset (timezone)
            if ($matches[7] !== 'Z') {
                preg_match('/^([+-])(\d{2}):(\d{2})$/', $matches[7], $matches);

                $offset = ($matches[1] === '-' ? -1 : 1) * ($matches[2] * 60 + $matches[3]) * 60;
                $timestamp -= $offset;
            }
        }

        return $timestamp;
    }

    /**
     * Get latest timestamp
     *
     * @return integer
     */
    public function getLatestTimestamp()
    {
        if (!isset($this->cacheDirectory)) {
            return;
        }

        $this->loadCacheMetaData();

        $latest = 0;

        foreach ($this->metaData as $key => $timestamp) {
            if ($timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }

    /**
     * Read content from cache
     *
     * @param string $key
     *
     * @return mixed
     */
    public function read($key)
    {
        if (!isset($this->cacheDirectory)) {
            return;
        }

        $this->loadCacheMetaData();

        return unserialize(file_get_contents($this->cacheDirectory . '/' . $key));
    }

    /**
     * Write content to cache
     *
     * @param string  $key       Cache key
     * @param mixed   $content   Content
     * @param integer $timestamp Unix timestamp
     */
    public function write($key, $content, $timestamp = null)
    {
        if (!isset($this->cacheDirectory)) {
            return;
        }

        $this->loadCacheMetaData();
        $this->metaData[$key] = $timestamp;
        $this->dirty = true;

        file_put_contents($this->cacheDirectory . '/' . $key, serialize($content));
    }
}
