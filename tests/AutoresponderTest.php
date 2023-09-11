<?php

namespace Dfumagalli\GetResponse\Tests;

use Dfumagalli\Getresponse\GetResponse;
use Getresponse\Sdk\Client\Exception\InvalidCommandDataException;
use Getresponse\Sdk\Client\Exception\InvalidDomainException;
use Getresponse\Sdk\Client\Exception\MalformedResponseDataException;
use Getresponse\Sdk\Operation\Autoresponders\GetAutoresponders\GetAutorespondersSearchQuery;
use Getresponse\Sdk\Operation\Autoresponders\GetAutoresponders\GetAutorespondersSortParams;
use Getresponse\Sdk\Operation\FromFields\GetFromFields\GetFromFieldsSearchQuery;
use Getresponse\Sdk\Operation\Model\NewsletterAttachment;
use Getresponse\Sdk\Operation\Model\NewsletterSendSettings;
use Getresponse\Sdk\Operation\Newsletters\GetNewsletters\GetNewslettersSearchQuery;
use Getresponse\Sdk\Operation\Newsletters\GetNewsletters\GetNewslettersSortParams;
use Getresponse\Sdk\Operation\Newsletters\Statistics\GetNewsletterStatistics\GetNewsletterStatisticsSearchQuery;
use Tests\CreatesApplication;
use Tests\TestCase;

class AutoresponderTest extends TestCase
{
    use CreatesApplication;
    public const UNIT_TEST_CAMPAIGN_ID = 'MDct2';
    public const UNIT_TEST_CONTACT_NAME = 'Dario';
    public const UNIT_TEST_CONTACT_ID = 'aUeUu'; // dario.fumagalli@dftechnosolutions.com
    public const UNIT_TEST_REPLY_TO_ID = 'dN2Vq8'; //
    public const UNIT_TEST_CAMPAIGN_AUTORESPONDER_ID = '5ybP8'; // "Unit test autoresponder campaign"
    public const UNIT_TEST_AUTORESPONDER_CONTACT_01_ID = 'VWyxDWp';
    public const UNIT_TEST_AUTORESPONDER_CONTACT_02_ID = 'VWyxDZj';
    public const UNIT_TEST_AUTORESPONDER_CONTACT_03_ID = 'VWyxDCl';
    public const UNIT_TEST_AUTORESPONDER_CONTACT_04_ID = 'VWyxDt2';
    public const UNIT_TEST_AUTORESPONDER_CONTACT_05_ID = 'VWyxDvg';

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
    /*
    public function test_create_newsletter()
    {
        // $this->markTestSkipped('Only enable to check newsletter creation.');
        //return static::UNIT_TEST_CAMPAIGN_NEWSLETTER_ID;

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        // Get the default from field id
        $query = new GetFromFieldsSearchQuery();
        $query->whereIsActive('true');
        $responseUnsplitPaginatedDataAsArray = $getResponse->getFromFields($client, $query);
        $this->assertStringContainsString('true', $responseUnsplitPaginatedDataAsArray[0]['isActive']);
        $fromFieldId = $responseUnsplitPaginatedDataAsArray[0]['fromFieldId'];

        // If there are 2+ from fields, use another as reply to field
        if (count($responseUnsplitPaginatedDataAsArray) > 1) {
            $replyToFieldId = $responseUnsplitPaginatedDataAsArray[1]['fromFieldId'];
        } else {
            $replyToFieldId = $fromFieldId;
        }

        $newsletterSendSettings = new NewsletterSendSettings();

        // Setup 4 contacts as destination
        $newsletterSendSettings->setSelectedContacts([
            self::UNIT_TEST_AUTORESPONDER_CONTACT_01_ID,
            self::UNIT_TEST_AUTORESPONDER_CONTACT_02_ID,
            self::UNIT_TEST_AUTORESPONDER_CONTACT_03_ID,
            self::UNIT_TEST_AUTORESPONDER_CONTACT_04_ID
        ]);

        // Newsletter attachments are optional. If not used, just pass "[]" or omit the parameter in the API call
        $newsletterAttachment = new NewsletterAttachment();

        // Attachment contents must be Base 64 encoded and not exceed 400KB in size.
        $newsletterAttachment->setContent('VW5pdCB0ZXN0IHRleHQgZmlsZS4='); // "Unit test text file."
        $newsletterAttachment->setFileName('test.txt');
        $newsletterAttachment->setMimeType('text/plain');

        $response = $getResponse->createNewsletter(
            $client,
            self::UNIT_TEST_CAMPAIGN_AUTORESPONDER_ID,
            'Unit test newsletter ' . uniqid(),
            'Newsletter subject ' . uniqid(),
            $fromFieldId,
            $replyToFieldId,
            'Plain content',
            '<p>HTML content</p>',
            null,
            $newsletterSendSettings,
            '', // Check API reference for the exact kind of date / time strings accepted
            [ $newsletterAttachment ]
        );

        if (!$response->isSuccess()) {
            $this->assertNotEmpty($response->getData());
            $x = var_export($response->getData(), true);
        }

        $this->assertTrue($response->isSuccess());

        return $response->getData()['newsletterId'];
    }
    */

    /**
     * @return array
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     */
    public function test_get_autoresponders(): array
    {
        // $this->markTestSkipped('Only enable to check autoresponders lookup.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $query = new GetAutorespondersSearchQuery();
        // $query->whereSubject('Autoresponder subject');
        $sort = new GetAutorespondersSortParams();
        $sort->sortAscBy('createdOn');
        $responseUnsplitPaginatedDataAsArray = $getResponse->getAutoresponders($client, $query, $sort);
        self::assertGreaterThan(0, count($responseUnsplitPaginatedDataAsArray));

        $this->assertStringContainsString('https://api.getresponse.com/v3/autoresponders', $responseUnsplitPaginatedDataAsArray[0]['href']);
        return $responseUnsplitPaginatedDataAsArray;
    }

    /**
     * @param array $autoresponders
     *
     * @return array
     *
     * @throws InvalidDomainException|MalformedResponseDataException
     * @depends test_get_autoresponders
     */
    public function test_get_autoresponder(array $autoresponders): array
    {
        // $this->markTestSkipped('Only enable to check autoresponder details.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $response = $getResponse->getAutoresponder($client, $autoresponders[0]['autoresponderId']);

        if (!$response->isSuccess()) {
            $this->assertNotEmpty($response->getData());
        }

        $this->assertTrue($response->isSuccess());
        $autoresponderDetails = $response->getData();
        $this->assertStringContainsString('Plain content', $autoresponderDetails['content']['plain']);

        return $autoresponders;
    }

    /**
     * @param string $discarded
     * @param array $newsletters
     *
     * @return bool
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_create_newsletter
     * @depends test_get_autoresponder
     */
    /*
    public function test_get_newsletter_statistics(string $discarded, array $newsletters): bool
    {
        // $this->markTestSkipped('Only enable to check newsletter statistics.');
        // Make sure GetResponse created the newsletter above and made it visible to the API
        sleep(5);

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        $query = new GetNewsletterStatisticsSearchQuery();
        $query->whereGroupByIsTotal();
        $responseUnsplitPaginatedDataAsArray = $getResponse->getNewsletterStatistics(
            $client,
            $newsletters[0]['newsletterId'],
            $query
        );
        self::assertGreaterThan(0, count($responseUnsplitPaginatedDataAsArray));
        self::assertGreaterThan(1, count($responseUnsplitPaginatedDataAsArray[0]));

        $this->assertEquals(4, $responseUnsplitPaginatedDataAsArray[0]['sent']);

        return true;
    }
    */

    /**
     * @param array $newsletters
     * @param bool $discarded
     *
     * @return void
     *
     * @throws InvalidDomainException
     * @throws MalformedResponseDataException
     * @depends test_get_newsletter
     * @depends test_get_newsletter_statistics
     */
    /*
    public function test_delete_newsletter(array $newsletters, bool $discarded): void
    {
        // $this->markTestSkipped('Only enable to check newsletter deletion.');

        $getResponse = GetResponse::forcePersonalAndAPIKey();
        $client = $getResponse->newGetresponseClient();

        foreach ($newsletters as $newsletter) {
            // Only delete newsletters that already have sent their messages
            if ($newsletter['sendMetrics']['status'] == 'finished') {
                $response = $getResponse->deleteNewsletter($client, $newsletter['newsletterId']);

                if (!$response->isSuccess()) {
                    $this->assertNotEmpty($response->getData());
                }

                $this->assertTrue($response->isSuccess());
            }
        }
    }
    */
}
