<?php

/**
 * This file is part of the contentful.php package.
 *
 * @copyright 2015-2018 Contentful GmbH
 * @license   MIT
 */

namespace Contentful\Tests\Delivery\E2E;

use Contentful\Delivery\Cache\CacheClearer;
use Contentful\Delivery\Cache\CacheWarmer;
use Contentful\Tests\Delivery\TestCase;

class CacheTest extends TestCase
{
    /**
     * @vcr e2e_cache_warmup_clear.json
     */
    public function testCacheWarmupClear()
    {
        self::$cache->clear();

        $client = $this->getClient('cfexampleapi');

        $warmer = new CacheWarmer($client, self::$cache);
        $clearer = new CacheClearer($client, self::$cache);

        $warmer->warmUp();

        $cacheItem = self::$cache->getItem(\Contentful\Delivery\cache_key_space($client->getApi(), 'cfexampleapi'));
        $this->assertTrue($cacheItem->isHit());

        $rawSpace = \json_decode($cacheItem->get(), true);
        $this->assertSame('cfexampleapi', $rawSpace['sys']['id']);

        $clearer->clear();
        $this->assertFalse(self::$cache->hasItem(\Contentful\Delivery\cache_key_space($client->getApi(), 'cfexampleapi')));

        self::$cache->clear();
    }

    /**
     * @vcr e2e_cache_empty.json
     */
    public function testApiWorksWithEmptyCache()
    {
        self::$cache->clear();

        $client = $this->getClient('cfexampleapi_cache');

        $this->assertSame('cfexampleapi', $client->getSpace()->getId());
        $this->assertSame('cat', $client->getContentType('cat')->getId());

        self::$cache->clear();
    }

    /**
     * @vcr e2e_cache_access_cached.json
     */
    public function testAccessCachedContent()
    {
        self::$cache->clear();

        $client = $this->getClient('cfexampleapi');

        $warmer = new CacheWarmer($client, self::$cache);
        $warmer->warmUp();

        $client = $this->getClient('cfexampleapi_cache');

        $this->assertSame('cfexampleapi', $client->getSpace()->getId());
        $this->assertSame('cat', $client->getContentType('cat')->getId());

        self::$cache->clear();
    }

    /**
     * @vcr e2e_cache_access_cached_autowarmup.json
     */
    public function testCachedContentAutoWarmup()
    {
        self::$cache->clear();

        $client = $this->getClient('cfexampleapi_cache_autowarmup');

        $this->assertSame('cfexampleapi', $client->getSpace()->getId());
        $this->assertSame('cat', $client->getContentType('cat')->getId());

        $cacheItem = self::$cache->getItem(\Contentful\Delivery\cache_key_space($client->getApi(), 'cfexampleapi'));
        $this->assertTrue($cacheItem->isHit());

        $rawSpace = \json_decode($cacheItem->get(), true);
        $this->assertSame('cfexampleapi', $rawSpace['sys']['id']);

        self::$cache->clear();
    }

    /**
     * @vcr e2e_cache_invalid_cached_content_type.json
     */
    public function testInvalidCachedContentType()
    {
        self::$cache->clear();

        $client = $this->getClient('88dyiqcr7go8');

        // This fake content type does not contain fields
        // which will actually be in the real API request.
        $client->reviveJson('{"sys":{"space":{"sys":{"type":"Link","linkType":"Space","id":"88dyiqcr7go8"}},"id":"person","type":"ContentType","createdAt":"2018-02-19T16:11:55.140Z","updatedAt":"2018-02-19T16:11:55.140Z","revision":1 },"displayField":"name","name":"Person","description":"","fields":[]}');

        $errorFields = ['name', 'jobTitle', 'picture'];
        // When building entries, missing fields are supposed to trigger
        // a silenced error message for every missing field.
        \set_error_handler(function ($errorCode, $errorMessage) use (&$errorFields) {
            $field = \array_shift($errorFields);

            $this->assertSame(
                'Entry of content type "Person" ("person") being built contains field "'.$field.'" which is not present in the content type definition. Please check your cache for stale content type definitions.',
                $errorMessage
            );
            $this->assertSame(512, $errorCode);
        }, E_USER_WARNING);

        $entry = $client->getEntry('Kpwt1njxgAm04oQYyUScm');
        \restore_error_handler();

        $this->assertSame('Ben Chang', $entry->getName());
        $this->assertSame('Señor', $entry->getJobTitle());
        $this->assertSame([
            'sys' => [
                'type' => 'Link',
                'linkType' => 'Asset',
                'id' => 'SQOIQ1rZMQQUeyoyGiEUq',
            ],
        ], $entry->getPicture());
    }
}
