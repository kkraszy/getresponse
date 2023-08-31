<?php

namespace Dfumagalli\GetResponse\Tests;

use Dfumagalli\Getresponse\GetResponse;
use Getresponse\Sdk\Client\Exception\InvalidDomainException;
use Getresponse\Sdk\Client\Exception\MalformedResponseDataException;
use Getresponse\Sdk\Operation\Campaigns\GetCampaigns\GetCampaigns;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContactsSearchQuery;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContactsSortParams;
use Getresponse\Sdk\Operation\FromFields\GetFromFields\GetFromFieldsSearchQuery;
use Getresponse\Sdk\Operation\FromFields\GetFromFields\GetFromFieldsSortParams;
use Getresponse\Sdk\Operation\Model\CampaignProfile;
use Getresponse\Sdk\Operation\Model\NewContactCustomFieldValue;
use Getresponse\Sdk\Operation\Model\NewContactTag;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Tests\CreatesApplication;
use Tests\TestCase;

class BasicTest extends TestCase
{
    use CreatesApplication;
    public const UNIT_TEST_CAMPAIGN_ID = 'MDct2';
    public const UNIT_TEST_CONTACT_NAME = 'DF Test';
    public const UNIT_TEST_TAG_ID = 'Vumth'; // "unit_test"
    public const UNIT_TEST_CUSTOM_FIELD_1_ID = 'VZSuSU'; // Birth date
    public const UNIT_TEST_CUSTOM_FIELD_2_ID = 'VZSuvt'; // City
    public const UNIT_TEST_CUSTOM_FIELD_3_ID = 'VZSuUu'; // Country
    public const UNIT_TEST_CUSTOM_FIELD_4_ID = 'VZSudG'; // Gender
    public const UNIT_TEST_UPDATE_CONTACT_ID = 'Vyv1tui'; // Unit test update contact

    public function test_facade()
    {
        $getResponse = \Dfumagalli\Getresponse\Facades\GetResponse::getFacadeRoot();
        $this->assertEquals(GetResponse::class, $getResponse::class);
    }

    /**
     * @throws InvalidDomainException
     */
    public function test_basic_functionality()
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $campaignsOperation = new GetCampaigns();
        $response = $client->call($campaignsOperation);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->getResponse()->getStatusCode());
    }

    /**
     * @throws InvalidDomainException
     */
    public function test_ping()
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $result = $getResponse->ping($client);
        $this->assertTrue($result);
    }

    /**
     * @throws MalformedResponseDataException
     */
    public function test_get_accounts()
    {
        // All fields
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $response = $getResponse->getAccounts($client);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->getResponse()->getStatusCode());
        $responseAsArray = $getResponse->responseDataAsArray($response);
        $this->assertCount(16, $responseAsArray);

        // 3 fields
        $response = $getResponse->getAccounts($client, ['accountId', 'email', 'href']);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals(200, $response->getResponse()->getStatusCode());
        $responseAsJSON = $getResponse->responseDataAsJSON($response);
        $this->assertJson($responseAsJSON);
        $responseAsArray = json_decode($responseAsJSON, true);
        $this->assertCount(3, $responseAsArray);
        $this->assertEquals('https://api.getresponse.com/v3/accounts', $responseAsArray['href']);
    }

    /**
     * @throws MalformedResponseDataException|InvalidDomainException
     */
    public function test_get_from_fields()
    {
        // All campaigns
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $query = new GetFromFieldsSearchQuery();
        $query->whereIsActive('true');
        $sort = new GetFromFieldsSortParams();
        $sort->sortAscBy('createdOn');
        $responseUnsplitPaginatedDataAsArray = $getResponse->getFromFields($client, $query, $sort);
        $this->assertStringContainsString('true', $responseUnsplitPaginatedDataAsArray[0]['isActive']);
        return $responseUnsplitPaginatedDataAsArray[0]['isActive'];
    }

    /**
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     */
    public function test_get_campaigns_and_single_campaign()
    {
        // All fields
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $responseUnsplitPaginatedDataAsArray = $getResponse->getCampaigns($client);
        $this->assertContains($responseUnsplitPaginatedDataAsArray[0]['languageCode'], ['ES', 'IT', 'EN']);

        // Test a single campaign. Take the first campaign id from the results above
        $singleCampaignObject = $responseUnsplitPaginatedDataAsArray[0];
        $campaignId = $singleCampaignObject['campaignId'];

        $response = $getResponse->getCampaign($client, $campaignId);
        $this->assertTrue($response->isSuccess());
        $singleCampaignAsArray = $getResponse->responseDataAsArray($response);
        $this->assertContains($singleCampaignAsArray['languageCode'], ['ES', 'IT', 'EN']);
        return $singleCampaignAsArray;
    }

    /**
     * @param array $campaignId
     *
     * @return string
     *
     * @throws InvalidDomainException
     * @depends test_get_campaigns_and_single_campaign
     */
    public function test_get_contacts(array $campaignId)
    {
        // All campaigns
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $finalPage = 1;
        $responseDataAsArray = $getResponse->getPaginatedContacts(
            $client,
            null,
            null,
            [],
            [],
            5,
            1,
            $finalPage
        );
        $this->assertStringContainsString('https://api.getresponse.com/v3/contacts', $responseDataAsArray[0]['href']);
        $this->assertGreaterThan(1, $finalPage);

        $query = new GetContactsSearchQuery();
        $query->whereName(static::UNIT_TEST_CONTACT_NAME);
        $sort = new GetContactsSortParams();
        $sort->sortAscBy('name');
        $responseUnsplitPaginatedDataAsArray = $getResponse->getContacts($client, $query, $sort);
        $this->assertStringContainsString('https://api.getresponse.com/v3/contacts', $responseUnsplitPaginatedDataAsArray[0]['href']);
        return $responseUnsplitPaginatedDataAsArray[0]['contactId'];
    }

    /**
     * @param string $contactId
     *
     * @return void
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_get_contacts
     */
    public function test_get_contact(string $contactId)
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        // 3 fields
        $response = $getResponse->getContact($client, $contactId, ['contactId', 'email', 'href']);
        $this->assertTrue($response->isSuccess());

        $this->assertEquals(200, $response->getResponse()->getStatusCode());
        $responseAsArray = $getResponse->responseDataAsArray($response);
        $this->assertCount(3, $responseAsArray);
    }

    /**
     * @return string
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     */
    public function test_create_campaign()
    {
        // $this->markTestSkipped('Only enable to check campaign creation.');
        return static::UNIT_TEST_CAMPAIGN_ID;
        /*
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $campaignName = 'Unit test campaign ' . uniqid();
        $campaignProfile = new CampaignProfile();
        $campaignProfile->setDescription('Unit test campaign description');
        $response = $getResponse->createCampaign($client, $campaignName, $campaignProfile);
        $this->assertTrue($response->isSuccess());
        // var_dump($response);
        return $response->getData()['campaignId'];
        */
    }

    /**
     * @return false|string
     *
     * @throws InvalidDomainException
     * @depends test_get_campaigns_and_single_campaign
     */
    public function test_get_custom_fields()
    {
        // All campaigns
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $responseUnsplitPaginatedDataAsArray = $getResponse->getCustomFields($client);
        $found = false;
        $arrayEntry = false;

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveArrayIterator($responseUnsplitPaginatedDataAsArray),
                RecursiveIteratorIterator::CATCH_GET_CHILD
            ) as $key => $value) {
            // Make sure that the $value['name'] to search is located at page 2+, else pagination won't be truly tested
            if (isset($value['name']) && ($value['name'] === 'state')) {
                $arrayEntry = $value;
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
        return $arrayEntry['customFieldId'];
    }

    /**
     * @param string $customFieldId
     *
     * @return void
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_get_custom_fields
     */
    public function test_get_custom_field(string $customFieldId)
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        // 3 fields
        $response = $getResponse->getCustomField($client, $customFieldId, ['customFieldId', 'name', 'fieldType']);
        $this->assertTrue($response->isSuccess());

        $this->assertEquals(200, $response->getResponse()->getStatusCode());
        $responseAsArray = $getResponse->responseDataAsArray($response);
        $this->assertCount(3, $responseAsArray);
    }

    public function test_get_tags()
    {
        // All Tags
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        $responseUnsplitPaginatedDataAsArray = $getResponse->getTags($client);
        $this->assertStringContainsString('rest_test', $responseUnsplitPaginatedDataAsArray[0]['name']);
        return $responseUnsplitPaginatedDataAsArray[0]['tagId'];
    }

    /**
     * @param string $tagId
     *
     * @return void
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_get_tags
     */
    public function test_get_tag(string $tagId)
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();
        // 2 fields
        $response = $getResponse->getTag($client, $tagId, ['tagId', 'name']);
        $this->assertTrue($response->isSuccess());

        $this->assertEquals(200, $response->getResponse()->getStatusCode());
        $responseAsArray = $getResponse->responseDataAsArray($response);
        $this->assertCount(2, $responseAsArray);
    }

    /**
     * @depends test_create_campaign
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     */
    public function test_create_contact(string $campaignId)
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        // Create a tag, if it does not exist
        $tagName = 'test_create_contact';
        $responseUnsplitPaginatedDataAsArray = $getResponse->getTags($client);
        $tagId = '';

        foreach ($responseUnsplitPaginatedDataAsArray as $tag) {
            if ($tag['name'] === $tagName) {
                $tagId = $tag['tagId'];
                break;
            }
        }

        // Tag not found, create it
        if (empty($tagId)) {
            $response = $getResponse->createTag($client, $tagName);
            $this->assertTrue($response->isSuccess());
            $tagId = $response->getData()['tagId'];
        }

        // Basic contact, no data
        $response = $getResponse->createContact(
            $client,
            $campaignId,
            'John Smith',
            'john.smith@gmail.com',
            null,
            null,
            $ipAddress = '154.3.66.2'
        );

        $this->assertTrue($response->isSuccess());
        // var_dump($response->getData());

        // Contact with a tag and custom fields
        $tags = [
            $tagId
        ];

        $customFields = [
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_1_ID, 'values' => ['1971-06-18']], // Birthdate
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_2_ID, 'values' => ['Toronto']], // City
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_3_ID, 'values' => ['Italia']], // Country
        ];

        $response = $getResponse->createContact(
            $client,
            $campaignId,
            'DF Test',
            'df' . rand(0, 9999) . '@test.it',
            0,
            null,
            $ipAddress = '156.54.69.' . rand(0, 255),
            $tags,
            $customFields
        );

        if (!$response->isSuccess()) {
            $this->assertNotEmpty($response->getData());
        }

        $this->assertTrue($response->isSuccess());
    }

    /**
     * @depends test_create_campaign
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     */
    public function test_update_contact(string $campaignId)
    {
        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        // Create a tag, if it does not exist
        $tagName = 'test_update_contact';
        $responseUnsplitPaginatedDataAsArray = $getResponse->getTags($client);
        $tagId = '';

        foreach ($responseUnsplitPaginatedDataAsArray as $tag) {
            if ($tag['name'] === $tagName) {
                $tagId = $tag['tagId'];
                break;
            }
        }

        // Tag not found, create it
        if (empty($tagId)) {
            $response = $getResponse->createTag($client, $tagName);
            $this->assertTrue($response->isSuccess());
            $tagId = $response->getData()['tagId'];
        }

        // Contact with a tag and custom fields
        $tags = [
            $tagId
        ];

        $customFields = [
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_1_ID, 'values' => ['1971-06-19']], // Birthdate
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_2_ID, 'values' => ['Roma']], // City
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_3_ID, 'values' => ['Italy']], // Country
            ['customFieldId' => static::UNIT_TEST_CUSTOM_FIELD_4_ID, 'values' => ['Male']] // Gender
        ];

        $response = $getResponse->updateContact(
            $client,
            static::UNIT_TEST_UPDATE_CONTACT_ID,
            $campaignId,
            'DF Test',
            'unitTestUpdate@gmail.com',
            0,
            null,
            $tags,
            $customFields
        );

        if (!$response->isSuccess()) {
            $this->assertNotEmpty($response->getData());
        }

        $this->assertTrue($response->isSuccess());
    }
}
