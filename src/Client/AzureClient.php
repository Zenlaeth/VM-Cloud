<?php

namespace Client;

use Exception\RetryableException;
use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

abstract class AzureClient
{
    const API_BASE_URL = 'https://management.azure.com/';
    const VM_API_VERSION = '2022-11-01';
    const LOCATIONS_API_VERSION = '2018-09-01';
    const RESOURCEGROUPS_API_VERSION = '2017-05-10';
    // const NETWORK_INTERFACE_API_VERSION = '2017-10-01';
    const NETWORK_INTERFACE_API_VERSION = '2022-09-01';
    const IMAGES_API_VERSION = '2017-12-01';
    const RESOURCE_API_VERSION = '2017-03-30';
    const SKU_API_VERSION = '2017-09-01';
    const VM_SIZES_VERSION = '2018-10-01';

    /**
     * @var string
     */
    protected $subscriptionId;

    /**
     * @var string
     */
    private $subscriptionUrl;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $authenticationToken;

    /**
     * @var integer
     */
    protected $guzzleVersion;

    /**
     * @var string
     */
    private $tenantId;

    /**
     * @var string
     */
    private $appId;

    /**
     * @var string
     */
    private $password;

    /**
     * @var array
     */
    protected $locations = [];

    /**
     * AzureVMClient constructor.
     *
     * @param string $subscriptionId
     * @param string $tenantId
     * @param string $appId
     * @param string $password
     *
     * @throws \Exception
     */
    public function __construct($subscriptionId, $tenantId = null, $appId = null, $password = null)
    {
        $this->guzzleVersion = (version_compare(Client::VERSION, 6) === 1) ? 6 : 5;
        $this->subscriptionId = $subscriptionId;
        $this->subscriptionUrl = self::API_BASE_URL.'subscriptions/'.$subscriptionId.'/';
        $this->tenantId = $tenantId;
        $this->appId = $appId;
        $this->password = $password;
    }

    /**
     * GET Wrapper
     *
     * @param $url
     * @return mixed
     * @throws \Exception
     */
    public function get($url)
    {
        $url = ltrim($url, '/');
        $client = $this->getClient();
        try{
            $r = $client->get($url);
            $body = $this->parseResponse($r);
            return $body;
        }
        catch (\Exception $e)
        {
            $error = json_decode($e->getResponse()->getBody()->getContents(), true);
            throw new \Exception($error['error']['message']);
        }
    }

    /**
     * DELETE wrapper
     *
     * @param $url
     * @return mixed
     * @throws \Exception
     */
    public function delete($url)
    {
        $url = ltrim($url, '/');
        $client = $this->getClient();
        try{
            $promise = $client->deleteAsync($url
            )->then(function ($result) {
                sleep(600); 
                return $result->getStatusCode();
            });

            return $promise;

            // $r = $client->delete($url);
            // return $r->getStatusCode();
        }
        catch (\Exception $e)
        {
            $error = json_decode($e->getResponse()->getBody()->getContents(), true);
            throw new \Exception($error['error']['message']);
        }
    }

    /**
     * PUT Wrapper
     *
     * @param $url
     * @return mixed
     * @throws \Exception
     * @throws RetryableException
     */
    public function put($url, $params = [])
    {
        $url = ltrim($url, '/');
        $client = $this->getClient();
        $options['json'] = $params;

        try{
            // $r = $client->put($url, $options);
            $promise = $client->putAsync($url, $options
            )->then(function ($result) {
                sleep(3); 
                return $result;
            })->wait();

            $body = $this->parseResponse($promise);

            return $body;
        }
        catch (\Exception $e)
        {
            dump($e->getResponse()->getBody()->getContents());
            die();

            $error = json_decode($e->getResponse()->getBody()->getContents(), true);
            if (stripos($error['error']['message'], 'retryable error') > 0) {
                throw new RetryableException($error['error']['message']);
            }

            throw new \Exception($error['error']['message']);
        }
    }

    /**
     * POST wrapper
     *
     * @param $url
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function post($url, $params = [])
    {
        $url = ltrim($url, '/');
        $client = $this->getClient();
        $options['json'] = $params;
        try{
            $r = $client->post($url, $options);
            $body = $this->parseResponse($r) ?: 'ok';
            return $body;
        }
        catch (\Exception $e)
        {
            $error = json_decode($e->getResponse()->getBody()->getContents(), true);
            if (stripos($error['error']['message'], 'retryable error') > 0) {
                throw new RetryableException($error['error']['message']);
            }
            throw new \Exception($error['error']['message']);
        }
    }


    /**
     * Create a new resource Group
     *
     * @see https://docs.microsoft.com/en-us/rest/api/resources/resourcegroups/createorupdate
     *
     * @param $name
     * @param string $location
     * @param string $tags
     * @return int
     */
    public function createResourceGroup($name, $location = 'westeurope', $tags = '')
    {
        $this->validateLocation($location);
        $url = 'resourceGroups/'. $name . '?api-version=' . static::RESOURCEGROUPS_API_VERSION;
        $options = [
            'name' => $name,
            'location' => $location,
            'tags' => $tags,
        ];

        return $this->put($url, $options);
    }

    /**
     * Delete a resource Group
     *
     * @see https://docs.microsoft.com/en-us/rest/api/resources/resourcegroups/delete
     *
     * @param string $name
     * @return int
     */
    public function deleteResourceGroup($name)
    {
        $url = 'resourceGroups/'. $name . '?api-version=' . static::RESOURCEGROUPS_API_VERSION;
        $client = $this->getClient();
        $r = $client->delete($url);

        return $r->getStatusCode();
    }

    /**
     * Get a list of Resources by Tag.
     *
     * @param string $tagName
     * @param string $tagValue
     * @return array
     */
    public function getTaggedResources($tagName, $tagValue)
    {
        $body = $this->get('resources?$filter=tagname eq \''. $tagName .'\' and tagvalue eq \'' . $tagValue .'\'&api-version=' . static::RESOURCEGROUPS_API_VERSION);

        return $body->value;
    }

    /**
     * Delete a resource by Id.
     *
     * @see https://docs.microsoft.com/en-us/rest/api/resources/resources/delete
     *
     * @param string $id
     * @return int
     */
    public function deleteResource($id)
    {
        $url = static::API_BASE_URL . ltrim($id, '/') . '?api-version=' . static::RESOURCE_API_VERSION;
        $result = $this->client->delete($url);

        return $result->getStatusCode();
    }

    /**
     * Get a resource by Id.
     *
     * @see https://docs.microsoft.com/en-us/rest/api/resources/resources/getbyid
     *
     * @param string $id
     * @return object
     */
    public function getResource($id)
    {
        $parts = explode('/', $id);

        $url = static::API_BASE_URL . ltrim($id, '/'); // Other API Version needed for delete ( WTF?? )
        $api = static::RESOURCE_API_VERSION;

        switch (true) {
            case in_array('networkInterfaces', $parts):
            case in_array('publicIPAddresses', $parts):
                $api = static::NETWORK_INTERFACE_API_VERSION;
                break;
            case in_array('images', $parts):
                $api = static::IMAGES_API_VERSION;
                break;
        }

        $url .= '?api-version=' . $api;
        $result = $this->client->get($url);
        return $this->parseResponse($result);
    }

    /**
     * Subscriptions - List Locations
     *
     * @see https://docs.microsoft.com/en-us/rest/api/resources/subscriptions/listlocations
     *
     * @return array
     */
    public function listLocations()
    {
        if (!$this->locations) {
            $body = $this->get('locations?api-version=' . static::LOCATIONS_API_VERSION);
            $this->locations = $body->value;
        }

        return $this->locations;
    }

    /**
     * Fetch token from Microsoft Azure.
     * Check out README.md on how to obtain these credentials.
     *
     * @param string $tenantId
     * @param string $appId
     * @param string $password
     * @return string
     * @throws \Exception
     */
    public function authenticateClient()
    {
        if ($this->tenantId === null || $this->appId === null || $this->password === null) {
            throw new \Exception("Missing parameters to authenticate Client");
        }

        if (!is_null($this->authenticationToken)) {
            return;
        }

        $client = new Client();
        $body = [
            'resource' => 'https://management.core.windows.net/',
            'client_id' => $this->appId,
            'client_secret' => $this->password,
            'grant_type' => 'client_credentials',
        ];

        switch($this->guzzleVersion) {
            case 5:
                $r = $client->post(
                    "https://login.windows.net/".$this->tenantId."/oauth2/token",
                    ['body' => $body]
                );
                break;
            default:
                $r = $client->request(
                    'POST',
                    "https://login.windows.net/".$this->tenantId."/oauth2/token",
                    ['form_params' => $body]
                );
        }

        $body = $this->parseResponse($r);

        if (isset($body->token_type, $body->access_token) && $body->token_type === 'Bearer') {
            $this->authenticationToken = $body->access_token;

            return;
        }

        throw new \Exception('Unable to fetch Access Token for Azure.');
    }

    /**
     * @param $tenantId
     * @param $appId
     * @param $password
     */
    public function authenticate($tenantId, $appId, $password)
    {
        $this->tenantId = $tenantId;
        $this->appId = $appId;
        $this->password = $password;
        $this->authenticationToken = null;
    }

    /**
     * Helper to get body object from response.
     *
     * @param object $r
     * @return mixed
     * @throws \Exception
     */
    protected function parseResponse($r)
    {

        if (method_exists($r, 'getStatusCode') && 0 === stripos($r->getStatusCode(), '20')) {
            $s = json_decode($r->getBody()->getContents());

            return $s;
            // return json_decode($r->getBody(), true);
        }

        if (method_exists($r, 'getBody')) {
            throw new \Exception($r->getBody()->getContents());
        }

        throw new \Exception('Invalid responsetype');
    }

    /**
     * Get/create Guzzle Client for Azure API.
     *
     * @return Client
     */
    protected function getClient()
    {
        $this->authenticateClient();
        if (!isset($this->client)) {
            $config = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer '.$this->authenticationToken,
                    'Content-Type' => 'application/json',
                ],
            ];

            switch ($this->guzzleVersion) {
                case 5:
                    $config = [
                        'base_url' => $this->subscriptionUrl,
                        'defaults' => $config,
                    ];
                    break;
                case 6:
                    $config['base_uri'] = $this->subscriptionUrl;
                    break;
            }

            $this->client = new Client($config);
        }

        return $this->client;
    }

    /**
     * Validate if a given location exists.
     *
     * @param string $locationName
     * @throws \Exception
     * @return object
     */
    protected function validateLocation($locationName)
    {
        $locations = $this->listLocations();
        foreach ($locations as $location) {
            if ($locationName === $location->name) {
                return $location;
            }
        }
        throw new \Exception(sprintf('Unknown location: "%s"', $locationName));
    }
}
