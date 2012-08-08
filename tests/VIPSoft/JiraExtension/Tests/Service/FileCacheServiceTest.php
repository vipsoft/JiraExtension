<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Tests\Service;

use VIPSoft\JiraExtension\Service\FileCacheService;

/**
 * File cache service test
 *
 * @group Service
 *
 * @author Jakub Zalas <jakub@zalas.pl>
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class FileCacheServiceTest extends \PHPUnit_Framework_TestCase
{
    private $cacheDirectory = null;

    public function setUp()
    {
        $this->cacheDirectory = '/tmp/filecacheservicetest'.date('YmdHis');
    }

    public function tearDown()
    {
        if (!is_dir($this->cacheDirectory)){
            return;
        }

        $dir = new \DirectoryIterator($this->cacheDirectory);

        foreach ($dir as $fileInfo) {
            if (!$fileInfo->isDot()) {
                unlink($fileInfo->getRealPath());
            }
        }

        rmdir($this->cacheDirectory);
    }

    public function testThatMetadataIsStoredInCache()
    {
        $fileCache = new FileCacheService($this->cacheDirectory);
        $fileCache->write('foo', 'bar', 1344344336);

        unset($fileCache);

        $this->assertFileExists($this->cacheDirectory.'/cache.meta');
    }

    public function testThatMetadataIsNotStoredIfNoDataIsCached()
    {
        $fileCache = new FileCacheService($this->cacheDirectory);

        unset($fileCache);

        $this->assertFileNotExists($this->cacheDirectory.'/cache.meta');
    }

    public function testGetKeys()
    {
        $fileCache = new FileCacheService($this->cacheDirectory);
        $fileCache->write('foo', 'content', 1344344331);
        $fileCache->write('bar', 'content', 1344344353);

        $this->assertEquals(array('foo', 'bar'), $fileCache->getKeys());
    }

    /**
     * @dataProvider provideDates
     */
    public function testConvertToUnixTimestamp($date, $expectedTimestamp)
    {
        $fileCache = new FileCacheService($this->cacheDirectory);

        $timestamp = $fileCache->convertToUnixTimestamp($date);

        $this->assertEquals($expectedTimestamp, $timestamp);
    }

    public function provideDates()
    {
        return array(
            array('2011-05-11T18:51:30+00:00', 1305139890),
            array('2011-05-11T18:51:30+01:00', 1305136290),
            array('2011-05-11T18:51:30Z', 1305139890),
            array('2011-05-11T17:51:30-01:00', 1305139890),
            array('2011-05-11T18:51:30.500+00:00', 1305139890),
        );
    }

    public function testGetLatestTimestamp()
    {
        $fileCache = new FileCacheService($this->cacheDirectory);
        $fileCache->write('foo', 'content', 1344344331);
        $fileCache->write('bar', 'content', 1344344353);
        $fileCache->write('baz', 'content', 1344344342);

        $this->assertEquals(1344344353, $fileCache->getLatestTimestamp());
    }

    public function testThatDataCanBeRetrievedByAnotherCacheInstance()
    {
        $fileCache = new FileCacheService($this->cacheDirectory);
        $fileCache->write('foo', 'content 1', 1344344331);
        $fileCache->write('bar', 'content 2', 1344344353);

        unset($fileCache);

        $fileCache = new FileCacheService($this->cacheDirectory);

        $this->assertEquals('content 1', $fileCache->read('foo'));
        $this->assertEquals('content 2', $fileCache->read('bar'));
    }
}
