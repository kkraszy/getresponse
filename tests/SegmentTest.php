<?php

namespace Dfumagalli\GetResponse\Tests;

use Dfumagalli\Getresponse\GetResponse;
use Getresponse\Sdk\Client\Exception\InvalidCommandDataException;
use Getresponse\Sdk\Client\Exception\InvalidDomainException;
use Getresponse\Sdk\Client\Exception\MalformedResponseDataException;
use Getresponse\Sdk\Operation\FromFields\GetFromFields\GetFromFieldsSearchQuery;
use Getresponse\Sdk\Operation\Model\NewSearchContacts;
use Getresponse\Sdk\Operation\Model\NewsletterAttachment;
use Getresponse\Sdk\Operation\Model\NewsletterSendSettings;
use Getresponse\Sdk\Operation\Newsletters\GetNewsletters\GetNewslettersSearchQuery;
use Getresponse\Sdk\Operation\Newsletters\GetNewsletters\GetNewslettersSortParams;
use Getresponse\Sdk\Operation\Newsletters\Statistics\GetNewsletterStatistics\GetNewsletterStatisticsSearchQuery;
use Getresponse\Sdk\Operation\SearchContacts\GetSearchContacts\GetSearchContactsSearchQuery;
use Getresponse\Sdk\Operation\SearchContacts\GetSearchContacts\GetSearchContactsSortParams;
use Tests\CreatesApplication;
use Tests\TestCase;

class SegmentTest extends TestCase
{
    use CreatesApplication;
    public const UNIT_TEST_CAMPAIGN_ID = 'MDct2';
    public const UNIT_TEST_CONTACT_NAME = 'Dario';
    public const UNIT_TEST_CONTACT_ID = 'aUeUu'; // dario.fumagalli@dftechnosolutions.com
    public const UNIT_TEST_REPLY_TO_ID = 'dN2Vq8'; //
    public const UNIT_TEST_TAG_ID = 'Vumth'; // "unit_test" tag
    public const UNIT_TEST_SEARCH_CONTACTS_NAME = 'Unit test segment'; // "Unit test segment"
    public const UNIT_TEST_SEARCH_CONTACTS_ID = 'afSp2'; // "Unit test default segment"
    public const UNIT_TEST_SEARCH_CONTACTS_NAME_PREFIX = 'Unit test segment';

    public function test_facade()
    {
        $getResponse = \Dfumagalli\Getresponse\Facades\GetResponse::getFacadeRoot();
        $this->assertEquals(GetResponse::class, $getResponse::class);
    }

    /**
     * @return string
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @throws InvalidCommandDataException
     */
    public function test_create_search_contacts(): string
    {
        // $this->markTestSkipped('Only enable to check search contacts creation.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        /*
        // Used to retrieve all tags
        $tags = $getResponse->getTags($client);
        */
        $tagId = self::UNIT_TEST_TAG_ID;

        // Set up the components of the search contact creation's parameter
        $name = self::UNIT_TEST_SEARCH_CONTACTS_NAME_PREFIX . ' ' . uniqid();
        $subscribersType = ['subscribed'];
        $sectionLogicOperator = 'or';
        //$conditionType
        $section = [
            'campaignIdsList' => [self::UNIT_TEST_CAMPAIGN_ID],
            'logicOperator' => 'or',
            'subscriberCycle' => ['receiving_autoresponder', 'not_receiving_autoresponder'],
            'conditions' => [
                [
                    'conditionType' => 'tag',
                    'operatorType' => 'exists',
                    'operator' => 'exists',
                    'value' => $tagId
                ]
            ],
            'subscriptionDate' => 'all_time'
        ];
        $newSearchContacts = new NewSearchContacts($subscribersType, $sectionLogicOperator, $section, $name);

        $response = $getResponse->createSearchContact($client, $newSearchContacts);

        if (!$response->isSuccess()) {
            $this->assertNotEmpty($response->getData());
            $x = var_export($response->getData(), true);
        }

        $this->assertTrue($response->isSuccess());

        return $response->getData()['searchContactId'];
    }

    /**
     * @return array
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     */
    public function test_get_search_contacts(): array
    {
        // $this->markTestSkipped('Only enable to check search contacts lookup.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $query = new GetSearchContactsSearchQuery();
        // $query->whereName(static::UNIT_TEST_SEARCH_CONTACTS_NAME);
        $sort = new GetSearchContactsSortParams();
        $sort->sortAscBy('name');
        $searchContacts = $getResponse->getPaginatedSearchContacts($client, $query, $sort);
        $this->assertStringContainsString('Unit test default segment', $searchContacts[0]['name']);

        return $searchContacts;
    }

    /**
     * @param array $searchContacts
     *
     * @return array
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_get_search_contacts
     */
    public function test_get_search_contacts_by_search_contacts_id(array $searchContacts): array
    {
        // $this->markTestSkipped('Only enable to check search contacts by search contacts id lookup.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $searchContact = $searchContacts[0]['searchContactId'];
        $response = $getResponse->getSearchContact($client, $searchContact);

        if (!$response->isSuccess()) {
            $this->assertNotEmpty($response->getData());
        }

        $this->assertTrue($response->isSuccess());
        $searchContactDetails = $response->getData();

        $this->assertContains($searchContactDetails['section'][0]['conditions'][0]['conditionType'], ['tag', 'name']);

        return $searchContacts;
    }

    /**
     * @param array $searchContacts
     *
     * @return void
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_get_search_contacts
     */
    public function test_delete_search_contacts(array $searchContacts): void
    {
        // $this->markTestSkipped('Only enable to check search contacts deletion.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        foreach ($searchContacts as $searchContact) {
            // Only delete search contacts that have the "Unit test" prefix
            if (str_contains($searchContact['name'], self::UNIT_TEST_SEARCH_CONTACTS_NAME_PREFIX)) {
                $response = $getResponse->deleteSearchContact($client, $searchContact['searchContactId']);

                if (!$response->isSuccess()) {
                    $this->assertNotEmpty($response->getData());
                }

                $this->assertTrue($response->isSuccess());
            }
        }
    }
}
