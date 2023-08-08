<?php

namespace Dfumagalli\Getresponse;

use Exception;
use Getresponse\Sdk\Client\Exception\InvalidDomainException;
use Getresponse\Sdk\Client\Exception\MalformedResponseDataException;
use Getresponse\Sdk\Client\GetresponseClient;
use Getresponse\Sdk\Client\Operation\OperationResponse;
use Getresponse\Sdk\Client\Operation\QueryOperation;
use Getresponse\Sdk\Client\Operation\Pagination;
use Getresponse\Sdk\Operation\Campaigns\CreateCampaign\CreateCampaign;
use Getresponse\Sdk\Operation\Contacts\CreateContact\CreateContact;
use Getresponse\Sdk\Operation\Contacts\GetContact\GetContact;
use Getresponse\Sdk\Operation\Contacts\GetContact\GetContactFields;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContactsAdditionalFlags;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContactsSortParams;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContactsSearchQuery;
use Getresponse\Sdk\GetresponseClientFactory;
use Getresponse\Sdk\Operation\Accounts\GetAccounts\GetAccounts;
use Getresponse\Sdk\Operation\Accounts\GetAccounts\GetAccountsFields;
use Getresponse\Sdk\Operation\Campaigns\GetCampaign\GetCampaign;
use Getresponse\Sdk\Operation\Campaigns\GetCampaigns\GetCampaigns;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContacts;
use Getresponse\Sdk\Operation\Contacts\GetContacts\GetContactsFields;
use Getresponse\Sdk\Operation\Contacts\UpdateContact\UpdateContact;
use Getresponse\Sdk\Operation\CustomFields\GetCustomField\GetCustomField;
use Getresponse\Sdk\Operation\CustomFields\GetCustomField\GetCustomFieldFields;
use Getresponse\Sdk\Operation\CustomFields\GetCustomFields\GetCustomFieldsFields;
use Getresponse\Sdk\Operation\CustomFields\GetCustomFields\GetCustomFields;
use Getresponse\Sdk\Operation\CustomFields\GetCustomFields\GetCustomFieldsSearchQuery;
use Getresponse\Sdk\Operation\CustomFields\GetCustomFields\GetCustomFieldsSortParams;
use Getresponse\Sdk\Operation\Model\CampaignReference;
use Getresponse\Sdk\Operation\Model\NewCampaign;
use Getresponse\Sdk\Operation\Model\CampaignOptinTypes;
use Getresponse\Sdk\Operation\Model\CampaignProfile;
use Getresponse\Sdk\Operation\Model\NewContact;
use Getresponse\Sdk\Operation\Model\NewContactCustomFieldValue;
use Getresponse\Sdk\Operation\Model\NewContactTag;
use Getresponse\Sdk\Operation\Model\NewTag;
use Getresponse\Sdk\Operation\Tags\CreateTag\CreateTag;
use Getresponse\Sdk\Operation\Tags\DeleteTag\DeleteTag;
use Getresponse\Sdk\Operation\Tags\GetTag\GetTag;
use Getresponse\Sdk\Operation\Tags\GetTag\GetTagFields;
use Getresponse\Sdk\Operation\Tags\GetTags\GetTags;
use Getresponse\Sdk\Operation\Tags\GetTags\GetTagsFields;
use Getresponse\Sdk\Operation\Tags\GetTags\GetTagsSearchQuery;
use Getresponse\Sdk\Operation\Tags\GetTags\GetTagsSortParams;

class GetResponse
{
    // Factories used to create a GetResponse manager instance

    /**
     * Create a GetResponse instance from the settings stored in the configuration file
     *
     * @return GetResponse
     */
    public static function fromConfig(): GetResponse
    {
        $apiKey                 = config('getresponse.api_key', '');
        $accessToken            = config('getresponse.accessToken', '');
        $useTokenAuthentication = config('getresponse.use_access_token_authentication');
        $isEnterprise           = config('getresponse.is_enterprise');
        $domain                 = config('getresponse.enterprise_domain', '');
        $maxServer              = config('getresponse.max_server', 'US');

        return static::fromParams($apiKey, $accessToken, $useTokenAuthentication, $isEnterprise, $domain, $maxServer);
    }

    /**
     * Create a GetResponse instance from specified parameters
     *
     * @param string $apiKey
     * @param string $accessToken
     * @param bool $useTokenAuthentication
     * @param bool $isEnterprise
     * @param string $domain
     * @param bool $maxServer
     *
     * @return GetResponse
     */
    public static function fromParams(
        string $apiKey,
        string $accessToken,
        bool $useTokenAuthentication,
        bool $isEnterprise,
        string $domain,
        bool $maxServer
    ): GetResponse {
        return new static($apiKey, $accessToken, $useTokenAuthentication, $isEnterprise, $domain, $maxServer);
    }

    /**
     * Create a non enterprise GetResponse instance that uses API key authentication. Used for (unit) testing.
     *
     * @return GetResponse
     */
    public static function forcePersonalAndAPIKey(): GetResponse
    {
        $apiKey = config('getresponse.api_key');

        return new static($apiKey, '', false, false, '', '');
    }

    // Factory used to create a Getresponse REST client instance

    /**
     * Create the appropriate Getresponse client connection
     *
     * @throws InvalidDomainException
     */
    public function newGetresponseClient(): GetresponseClient
    {
        if ($this->isEnterprise) {
            switch ($this->domain) {
                case 'US':
                    if ($this->useTokenAuthentication) {
                        return GetresponseClientFactory::createEnterpriseUSWithAccessToken($this->accessToken, $this->domain);
                    } else {
                        return GetresponseClientFactory::createEnterpriseUSWithApiKey($this->apiKey, $this->domain);
                    }

                    // no break
                case 'PL':
                    if ($this->useTokenAuthentication) {
                        return GetresponseClientFactory::createEnterprisePLWithAccessToken($this->accessToken, $this->domain);
                    } else {
                        return GetresponseClientFactory::createEnterprisePLWithApiKey($this->apiKey, $this->domain);
                    }

                    // no break
                default:
                    throw new InvalidDomainException();
            }
        } else {
            if ($this->useTokenAuthentication) {
                return GetresponseClientFactory::createWithAccessToken($this->accessToken);
            }
        }

        return GetresponseClientFactory::createWithApiKey($this->apiKey);
    }

    // Some common GetResponse functionality. For the full list,
    // @see https://github.com/GetResponse/sdk-php/blob/master/docs

    /**
     * Return true if the GetResponse service can be contacted and queried
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     *
     * @return bool
     */
    public function ping(GetresponseClient $client): bool
    {
        $accountsOperation = new GetAccounts();
        $response = $client->call($accountsOperation);
        return $response->isSuccess();
    }

    /**
     * Get the account data fields. The result can be extracted by @see responseDataAsArray or @see responseDataAsJSON.
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient();
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     * @throws InvalidDomainException
     */
    public function getAccounts(GetresponseClient $client, array $fieldsToGet = []): OperationResponse
    {
        if (empty($fieldsToGet)) {
            $fieldsToGet = (new GetAccountsFields())->getAllowedValues();
        }

        $accountsFields = new GetAccountsFields(...$fieldsToGet);

        $accountsOperation = new GetAccounts();
        $accountsOperation->setFields($accountsFields);

        return $client->call($accountsOperation);
    }

    /**
     * Get the list of campaigns found on the GetResponse service
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param int $campaignsPerPage How many campaign rows to fetch per paginated request
     *
     * @return array List of campaigns stored on GetResponse
     * @throws MalformedResponseDataException
     */
    public function getCampaigns(GetresponseClient $client, int $campaignsPerPage = 10): array
    {
        $campaignsOperation = new GetCampaigns();

        return $this->responseUnsplitPaginatedDataAsArray($client, $campaignsOperation, $campaignsPerPage);
    }

    /**
     * Get information about a campaign, given its GetResponse id
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $campaignId Campaign id, possibly fetched by @getCampaigns()
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     */
    public function getCampaign(GetresponseClient $client, string $campaignId): OperationResponse
    {
        $campaignOperation = new GetCampaign($campaignId);

        return $client->call($campaignOperation);
    }

    /**
     * Create a new campaign
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $campaignName Campaign name, Getresponse naming limitations apply
     * @param CampaignProfile $campaignProfile Campaign profile
     * @param CampaignOptinTypes|null $optinTypes Campaign optin types. Null = all optin types are set to 'single'
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     * @throws MalformedResponseDataException
     */
    public function createCampaign(
        GetresponseClient $client,
        string $campaignName,
        CampaignProfile $campaignProfile,
        CampaignOptinTypes $optinTypes = null
    ) {
        $newCampaign = new NewCampaign($campaignName);

        if ($optinTypes === null) {
            $optinTypes = new CampaignOptinTypes();
            $optinTypes->setApi('single');
            $optinTypes->setEmail('single');
            $optinTypes->setImport('single');
            $optinTypes->setWebform('single');
        }

        $newCampaign->setOptinTypes($optinTypes);
        $newCampaign->setProfile($campaignProfile);
        $createCampaignOperation = new CreateCampaign($newCampaign);

        return $client->call($createCampaignOperation);
    }

    /**
     * Get the list of contacts matching the given query and parameters
     *
     * @param GetresponseClient $client $client Getresponse client instance, created by @newGetresponseClient()
     * @param GetContactsSearchQuery|null $query Optional query, to filter the contacts to fetch
     * @param GetContactsSortParams|null $sort Optional contacts sort order
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     * @param array $additionalFlags Array of additional flags. Pass an empty array to skip the assignment
     * @param int $contactsPerPage How many contact rows to fetch per paginated request
     *
     * @return array $additionalFlags List of contacts stored on GetResponse
     * @throws MalformedResponseDataException
     */
    public function getContacts(
        GetresponseClient $client,
        GetContactsSearchQuery $query = null,
        GetContactsSortParams $sort = null,
        array $fieldsToGet = [],
        array $additionalFlags = [],
        int $contactsPerPage = 10
    ): array {
        $getContactsOperation = new GetContacts();

        if ($query !== null) {
            $getContactsOperation->setQuery($query);
        }

        if ($sort != null) {
            $getContactsOperation->setSort($sort);
        }

        if (!empty($fieldsToGet)) {
            $contactsFields = new GetContactsFields(...$fieldsToGet);
            $getContactsOperation->setFields($contactsFields);
        }

        if (!empty($additionalFlags)) {
            $getContactsAdditionalFlags = new GetContactsAdditionalFlags(...$additionalFlags);
            $getContactsOperation->setAdditionalFlags($getContactsAdditionalFlags);
        }

        return $this->responseUnsplitPaginatedDataAsArray($client, $getContactsOperation, $contactsPerPage);
    }

    /**
     * Get the list of contacts matching the given query and parameters, one page at a time
     *
     * @param GetresponseClient $client $client Getresponse client instance, created by @newGetresponseClient()
     * @param GetContactsSearchQuery|null $query Optional query, to filter the contacts to fetch
     * @param GetContactsSortParams|null $sort Optional contacts sort order
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     * @param array $additionalFlags Array of additional flags. Pass an empty array to skip the assignment
     * @param int $contactsPerPage How many contact rows to fetch per paginated request
     * @param int $pageNumber Results page to display
     * @param int $finalPage Data set's last page number, returned by inner pagination calls
     *
     * @return array $additionalFlags List of contacts stored on GetResponse
     * @throws MalformedResponseDataException
     */
    public function getPaginatedContacts(
        GetresponseClient $client,
        GetContactsSearchQuery $query = null,
        GetContactsSortParams $sort = null,
        array $fieldsToGet = [],
        array $additionalFlags = [],
        int $contactsPerPage = 10,
        int $pageNumber = 1,
        int &$finalPage = 1
    ): array {
        $getContactsOperation = new GetContacts();

        if ($query !== null) {
            $getContactsOperation->setQuery($query);
        }

        if ($sort != null) {
            $getContactsOperation->setSort($sort);
        }

        if (!empty($fieldsToGet)) {
            $contactsFields = new GetContactsFields(...$fieldsToGet);
            $getContactsOperation->setFields($contactsFields);
        }

        if (!empty($additionalFlags)) {
            $getContactsAdditionalFlags = new GetContactsAdditionalFlags(...$additionalFlags);
            $getContactsOperation->setAdditionalFlags($getContactsAdditionalFlags);
        }

        return $this->responsePaginatedDataAsArray(
            $client,
            $getContactsOperation,
            $contactsPerPage,
            $pageNumber,
            $finalPage
        );
    }

    /**
     * Get information about a contact, given its contact id
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $contactId Contact id, possibly fetched by @getContacts()
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     */
    public function getContact(GetresponseClient $client, string $contactId, array $fieldsToGet = []): OperationResponse
    {
        if (empty($fieldsToGet)) {
            $fieldsToGet = (new GetContactFields())->getAllowedValues();
        }

        $contactFields = new GetContactFields(...$fieldsToGet);

        $contactOperation = new GetContact($contactId);
        $contactOperation->setFields($contactFields);

        return $client->call($contactOperation);
    }

    /**
     * Create a new contact, with optional custom fields and tags
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $campaignId Campaign id, as returned by @getCampaigns()
     * @param string $name Contact name
     * @param string $emailAddress Contact email address
     * @param int|null $dayOfCycle Contact autoresponder day of cycle. Null = not in the cycle
     * @param float|null $scoring Contact scoring. Null = contact with no score.
     * @param string $ipAddress Contact IP address. Must pass a valid, non local address
     * @param array $tagsIds Contact array of tags ids (tags must exist already). Empty array = no tags
     * @param array $customFieldsIdsAndValues Contact array of custom fields. Empty array = no custom fields
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     * @throws MalformedResponseDataException
    */
    public function createContact(
        GetresponseClient $client,
        string $campaignId,
        string $name,
        string $emailAddress,
        ?int $dayOfCycle = null,
        ?float $scoring = null, // N.B. only supported by advanced GetResponse accounts! If not, error 400 is returned.
        string $ipAddress = '',
        array $tagsIds = [],
        array $customFieldsIdsAndValues = []
    ) {
        $newContact = new NewContact(
            new CampaignReference($campaignId),
            $emailAddress
        );

        $newContact->setName($name);

        if ($dayOfCycle !== null) {
            $newContact->setDayOfCycle($dayOfCycle);
        }

        if ($scoring !== null) {
            $newContact->setScoring($scoring);
        }

        $newContact->setIpAddress($ipAddress);

        if (!empty($tagsIds)) {
            foreach ($tagsIds as $tagId) {
                $tagsCollection[] = new NewContactTag($tagId);
            }

            $newContact->setTags($tagsCollection);
        }

        if (!empty($customFieldsIdsAndValues)) {
            foreach ($customFieldsIdsAndValues as $customFieldsIdsAndValue) {
                $customFieldsCollection[] = new NewContactCustomFieldValue(
                    $customFieldsIdsAndValue['customFieldId'],
                    $customFieldsIdsAndValue['values']
                );
            }

            $newContact->setCustomFieldValues($customFieldsCollection);
        }

        $createContactOperation = new CreateContact($newContact);
        return $client->call($createContactOperation);
    }

    /**
     * Update a contact, given its id, with optional custom fields and tags
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $contactId Contact id, possibly fetched by @getContacts()
     * @param string $campaignId Campaign id, as returned by @getCampaigns(). If not empty, assign to new campaign
     * @param string $name Contact name
     * @param string $emailAddress Contact email address
     * @param int|null $dayOfCycle Contact autoresponder day of cycle. Null = not in the cycle
     * @param float|null $scoring Contact scoring. Null = contact with no score.
     * @param array $tagsIds Contact array of tags ids (tags must exist already). Empty array = no tags
     * @param array $customFieldsIdsAndValues Contact array of custom fields. Empty array = no custom fields
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     * @throws MalformedResponseDataException
     */
    public function updateContact(
        GetresponseClient $client,
        string $contactId,
        string $campaignId = '',
        string $name = '',
        string $emailAddress = '',
        ?int $dayOfCycle = null,
        ?float $scoring = null, // N.B. only supported by advanced GetResponse accounts! If not, error 400 is returned.
        array $tagsIds = [],
        array $customFieldsIdsAndValues = []
    ) {
        $updateContact = new \Getresponse\Sdk\Operation\Model\UpdateContact();

        if (!empty($campaignId)) {
            $campaignReference = new CampaignReference($campaignId);
            $updateContact->setCampaign($campaignReference);
        }

        if (!empty($name)) {
            $updateContact->setName($name);
        }

        if (!empty($emailAddress)) {
            $updateContact->setEmail($emailAddress);
        }

        if ($dayOfCycle !== null) {
            $updateContact->setDayOfCycle($dayOfCycle);
        }

        if ($scoring !== null) {
            $updateContact->setScoring($scoring);
        }

        if (!empty($tagsIds)) {
            foreach ($tagsIds as $tagId) {
                $tagsCollection[] = new NewContactTag($tagId);
            }

            $updateContact->setTags($tagsCollection);
        }

        if (!empty($customFieldsIdsAndValues)) {
            foreach ($customFieldsIdsAndValues as $customFieldsIdsAndValue) {
                $customFieldsCollection[] = new NewContactCustomFieldValue(
                    $customFieldsIdsAndValue['customFieldId'],
                    $customFieldsIdsAndValue['values']
                );
            }

            $updateContact->setCustomFieldValues($customFieldsCollection);
        }

        $updateContactOperation = new UpdateContact($updateContact, $contactId);
        return $client->call($updateContactOperation);
    }

    /**
     * Create a new tag
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $name Tag name
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     */
    public function createTag(
        GetresponseClient $client,
        string $name
    ) {
        $newTag = new NewTag($name);

        $createTag = new CreateTag($newTag);
        return $client->call($createTag);
    }

    /**
     * Delete a tag given its id
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $tagId Tag id
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     * @throws MalformedResponseDataException
     */
    public function deleteTag(
        GetresponseClient $client,
        string $tagId
    ) {
        $deleteTag = new DeleteTag($tagId);
        return $client->call($deleteTag);
    }

    /**
     * Get the list of custom fields matching the given query and parameters
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param GetCustomFieldsSearchQuery|null $query Optional query, to filter the custom fields to fetch
     * @param GetCustomFieldsSortParams|null $sort Optional custom fields sort order
     * @param int $fieldsPerPage How many custom fields to fetch per paginated request
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     *
     * @return array List of custom fields stored on GetResponse
     * @throws MalformedResponseDataException
     */
    public function getCustomFields(
        GetresponseClient $client,
        GetCustomFieldsSearchQuery $query = null,
        GetCustomFieldsSortParams $sort = null,
        array $fieldsToGet = [],
        int $fieldsPerPage = 10
    ): array {
        $getCustomFieldsOperation = new GetCustomFields();

        if ($query !== null) {
            $getCustomFieldsOperation->setQuery($query);
        }

        if ($sort != null) {
            $getCustomFieldsOperation->setSort($sort);
        }

        if (!empty($fieldsToGet)) {
            $getCustomFieldsFields = new GetCustomFieldsFields(...$fieldsToGet);
            $getCustomFieldsOperation->setFields($getCustomFieldsFields);
        }

        return $this->responseUnsplitPaginatedDataAsArray($client, $getCustomFieldsOperation, $fieldsPerPage);
    }

    /**
     * Get information about a custom field, given its custom field id
     *
     * @param GetresponseClient $client $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $customFieldId Custom field id, possibly fetched by @getContacts()
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     */
    public function getCustomField(GetresponseClient $client, string $customFieldId, array $fieldsToGet = []): OperationResponse
    {
        if (empty($fieldsToGet)) {
            $fieldsToGet = (new GetCustomFieldFields())->getAllowedValues();
        }

        $customFieldsFields = new GetCustomFieldFields(...$fieldsToGet);

        $customFieldOperation = new GetCustomField($customFieldId);
        $customFieldOperation->setFields($customFieldsFields);

        return $client->call($customFieldOperation);
    }

    /**
     * Get the list of tags matching the given query and parameters
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param GetTagsSearchQuery|null $query Optional query, to filter the custom fields to fetch
     * @param GetTagsSortParams|null $sort Optional custom fields sort order
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     * @param int $fieldsPerPage How many custom fields to fetch per paginated request
     *
     * @return array List of custom fields stored on GetResponse
     * @throws MalformedResponseDataException
     */
    public function getTags(
        GetresponseClient $client,
        GetTagsSearchQuery $query = null,
        GetTagsSortParams $sort = null,
        array $fieldsToGet = [],
        int $fieldsPerPage = 10
    ): array {
        $getTagsOperation = new GetTags();

        if ($query !== null) {
            $getTagsOperation->setQuery($query);
        }

        if ($sort != null) {
            $getTagsOperation->setSort($sort);
        }

        if (!empty($fieldsToGet)) {
            $tagsFields = new GetTagsFields(...$fieldsToGet);
            $getTagsOperation->setFields($tagsFields);
        }

        return $this->responseUnsplitPaginatedDataAsArray($client, $getTagsOperation, $fieldsPerPage);
    }

    /**
     * Get information about a tag, given its tag id
     *
     * @param GetresponseClient $client $client Getresponse client instance, created by @newGetresponseClient()
     * @param string $tagId Tag id, possibly fetched by @getTags()
     * @param array $fieldsToGet Array of fields names to get. Pass an empty array to fetch all the fields
     *
     * @return OperationResponse Response object, it can be unpacked by @responseDataAsArray or @responseDataAsJSON
     */
    public function getTag(GetresponseClient $client, string $tagId, array $fieldsToGet = []): OperationResponse
    {
        if (empty($fieldsToGet)) {
            $fieldsToGet = (new GetTagFields())->getAllowedValues();
        }

        $tagFields = new GetTagFields(...$fieldsToGet);

        $getTagOperation = new GetTag($tagId);
        $getTagOperation->setFields($tagFields);

        return $client->call($getTagOperation);
    }

    // Returned / response data manipulation functions. Data may be manipulated either as array or as JSON (never
    // both, because they share and fetch from a stream and it gets used up).

    /**
     * Return REST call response data as array. N.B. cannot be called after a previous call to @responseDataAsJSON!
     *
     * @param OperationResponse $response
     *
     * @return array
     *
     * @throws MalformedResponseDataException
     */
    public function responseDataAsArray(OperationResponse $response): array
    {
        return $response->getData();
    }

    /**
     * Return REST call response data as JSON. N.B. cannot be called after a previous call to @responseDataAsArray!
     *
     * @param OperationResponse $response
     *
     * @return string
     */
    public function responseDataAsJSON(OperationResponse $response): string
    {
        return $response->getResponse()->getBody()->getContents();
    }

    /**
     * Unsplit and return paginated data as one big array. Especially useful when returning lists of items
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param QueryOperation $clientOperation GetResponse operation { @see https://github.com/GetResponse/sdk-php/blob/master/docs }
     * @param int $resultsPerPage How many items to return per each page
     *
     * @return array Returned list of items
     * @throws MalformedResponseDataException
     * @throws Exception
     */
    public function responseUnsplitPaginatedDataAsArray(
        GetresponseClient $client,
        QueryOperation $clientOperation,
        int $resultsPerPage = 10
    ): array {
        $pageNumber = 1;
        $finalPage = 1;
        $results = [];

        while ($pageNumber <= $finalPage) {
            $subResults = $this->responsePaginatedDataAsArray(
                $client,
                $clientOperation,
                $resultsPerPage,
                $pageNumber++,
                $finalPage
            );

            foreach ($subResults as $subResult) {
                $results[] = $subResult;
            }
        }

        return $results;
    }

    /**
     * Return one page of paginated data as array. Especially useful when returning lists of items
     *
     * @param GetresponseClient $client Getresponse client instance, created by @newGetresponseClient()
     * @param QueryOperation $clientOperation GetResponse operation { @see https://github.com/GetResponse/sdk-php/blob/master/docs }
     * @param int $resultsPerPage How many items to return per each page
     * @param int $pageNumber Results page to display
     * @param int $finalPage Data set's last page number, returned by the REST call
     *
     * @return array Returned list of items
     * @throws MalformedResponseDataException
     * @throws Exception
     */
    public function responsePaginatedDataAsArray(
        GetresponseClient $client,
        QueryOperation $clientOperation,
        int $resultsPerPage = 10,
        int $pageNumber = 1,
        int &$finalPage = 1
    ): array {
        /**
         * There could be pagination, so we have to send requests for each page
         */
        $results = [];

        $clientOperation->setPagination(new Pagination($pageNumber, $resultsPerPage));

        $response = $client->call($clientOperation);

        if ($response->isSuccess()) {
            /**
             * Note: as operations are asynchronous, pagination data could change during the execution
             * of this code, so it is better to adjust finalPage every call
             */
            if ($response->isPaginated()) {
                $paginationValues = $response->getPaginationValues();
                $finalPage = $paginationValues->getTotalPages();
            }

            $responseDataArray = $response->getData();
            foreach ($responseDataArray as $responseDataRow) {
                $results[] = $responseDataRow;
                // var_dump($responseDataRow);
            }

            $pageNumber++;
        } else {
            $errorData = $response->getData();
            throw new Exception(
                __('Error fetching data from the mailing list service:' . ' ' . $errorData['message'])
            );
        }

        return $results;
    }

    protected function __construct(
        public string $apiKey,
        public string $accessToken,
        public bool $useTokenAuthentication,
        public bool $isEnterprise,
        public string $domain,
        public bool $maxServer
    ) {
        //
    }
}
