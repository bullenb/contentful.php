<?php
/**
 * @copyright 2015-2017 Contentful GmbH
 * @license   MIT
 */

namespace Contentful\Tests\E2E;

use Contentful\Delivery\Client;
use Contentful\Log\ArrayLogger;
use Contentful\ResourceArray;
use Contentful\Delivery\DynamicEntry;
use Contentful\Delivery\Asset;
use Contentful\Delivery\Query;

/**
 * Test that objects can be constructed successfullly in various scenarios.
 */
class EntryBasicTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = new Client('b4c0n73n7fu1', 'cfexampleapi');
    }

    /**
     * @vcr e2e_entry_get_all_locale_all.json
     */
    public function testGetAll()
    {
        $query = (new Query())
            ->setLocale('*');
        $assets = $this->client->getEntries($query);

        $this->assertInstanceOf(ResourceArray::class, $assets);
    }

    /**
     * @vcr e2e_entry_get_all_locale_default.json
     */
    public function testGetAllDefaultLocale()
    {
        $assets = $this->client->getEntries();

        $this->assertInstanceOf(ResourceArray::class, $assets);
    }

    /**
     * @vcr e2e_entry_get_one_locale_all.json
     */
    public function testGetOne()
    {
        $entry = $this->client->getEntry('nyancat', '*');

        $this->assertInstanceOf(DynamicEntry::class, $entry);
        $this->assertEquals('nyancat', $entry->getId());
    }

    /**
     * @vcr e2e_entry_get_one_locale_default.json
     */
    public function testGetOneDefaultLocale()
    {
        $entry = $this->client->getEntry('nyancat');

        $this->assertInstanceOf(DynamicEntry::class, $entry);
        $this->assertEquals('nyancat', $entry->getId());
    }

    /**
     * @vcr e2e_entry_lazy_loading.json
     */
    public function testLazyLoading()
    {
        $entry = $this->client->getEntry('nyancat');
        $bestFriend = $entry->getBestFriend();

        $this->assertInstanceOf(DynamicEntry::class, $entry);
        $this->assertEquals('happycat', $bestFriend->getId());

        $image = $entry->getImage();

        $this->assertInstanceOf(Asset::class, $image);
        $this->assertEquals('nyancat', $image->getId());
    }

    /**
     * @vcr e2e_entry_lazy_loading_cached.json
     */
    public function testLazyLoadIsCached()
    {
        $nyancat = $this->client->getEntry('nyancat');
        $bestFriend = $nyancat->getBestFriend();

        // Locally it's cached
        $this->assertEquals('happycat', $bestFriend->getId());
        $this->assertSame($bestFriend, $nyancat->getBestFriend());

        // but not globally
        $happycat = $this->client->getEntry('happycat');
        $this->assertEquals($bestFriend->getId(), $happycat->getId());
        $this->assertNotSame($bestFriend, $happycat);
    }

    /**
     * @vcr e2e_entry_withing_graph_identical.json
     */
    public function testEntriesWithinGraphAreIdentical()
    {
        $query = (new Query)
            ->where('sys.id', 'nyancat');
        $nyancat = $this->client->getEntries($query)[0];
        $bestFriend = $nyancat->getBestFriend();
        $bestFriendsBestFriend = $bestFriend->getBestFriend();

        $this->assertEquals('nyancat', $bestFriendsBestFriend->getId());
        $this->assertSame($nyancat, $bestFriendsBestFriend);
    }

    /**
     * @vcr e2e_entry_assets_resolved_from_includes.json
     */
    public function testAssetsResolvedFromIncludes()
    {
        $logger = new ArrayLogger;
        $client = new Client('b4c0n73n7fu1', 'cfexampleapi', false, null, [
            'logger' => $logger
        ]);

        $query = (new Query)
            ->where('sys.id', 'nyancat');
        $nyancat = $client->getEntries($query)[0];
        $image = $nyancat->getImage();

        $this->assertEquals('nyancat', $image->getId());

        // There should be 4 and only 4 requests: the entries with the query, the space and the cat content type
        $this->assertCount(3, $logger->getLogs());
    }
}
