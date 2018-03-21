<?php

namespace Caxy\ForecastApi;

use GuzzleHttp\Client;

class ForecastClient
{
    const BASE_URI = 'https://api.forecastapp.com';
    const AUTH_BASE_URI = 'https://id.getharvest.com';

    /**
     * @var Client
     */
    protected $guzzle;

    /**
     * @var string
     */
    protected $apiToken;

    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var string|int
     */
    protected $accountId;

    /**
     * ForecastClient constructor.
     */
    public function __construct($accountId, $apiToken = null, $baseUri = self::BASE_URI)
    {
        $this->accountId = $accountId;
        $this->apiToken = $apiToken;
        $this->baseUri = $baseUri;
        $this->guzzle = $this->makeClient();
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return null|string the API access token.
     * @throws \Exception
     */
    public function authenticate($username, $password)
    {
        $authClient = $this->makeAuthClient();
        // Parse CSRF token from login form.
        $loginFormResponse = $authClient->get('/forecast/sign_in');
        $loginForm = $loginFormResponse->getBody()->getContents();
        $matches = [];
        $regexp = '/<input type="hidden" name="authenticity_token" value="(.*)"/';
        preg_match_all($regexp, $loginForm, $matches);
        $csrfToken = $matches[1][0];
        if (empty($csrfToken)) {
            throw new \Exception('CSRF token error - no token');
        }
        $data = [
            'authenticity_token' => $csrfToken,
            'email' => $username,
            'password' => $password,
            'product' => 'forecast'
        ];
        $sessionResponse = $authClient->post('/sessions', array('form_params' => $data));
        // Don't follow redirects on this one?
        $tokenRequestResponse = $authClient->get(sprintf('/accounts/%s', $this->accountId), array(
            'allow_redirects' => false,
        ));
        $tokenRequest = $tokenRequestResponse->getBody()->getContents();
        $matches = [];
        $regexp = '/access_token\/(.*)\?"/';
        preg_match_all($regexp, $tokenRequest, $matches);
        if (isset($matches[1][0]) && !empty($matches[1][0])) {
            $this->apiToken = $matches[1][0];

            // Remake the client so that it uses the api token authorization header.
            $this->guzzle = $this->makeClient();

            return $this->apiToken;
        } else {
            throw new \Exception('Could not find token. Check your login, password and APP ID');
        }
    }

    public function whoAmI()
    {
        return $this->request('GET', '/whoami');
    }

    public function getClients()
    {
        return $this->request('GET', '/clients')['clients'];
    }

    public function getPeople()
    {
        return $this->request('GET', '/people')['people'];
    }

    public function getProjects()
    {
        return $this->request('GET', '/projects')['projects'];
    }

    public function getAssignments($startDate = null, $endDate = null, $state = 'active')
    {
        $options = $this->addDateQueryToOptions($startDate, $endDate);
        $options['query']['state'] = $state;

        return $this->request('GET', '/assignments', $options)['assignments'];
    }

    public function postAssignment($data, array $options = array())
    {
        $options['json'] = $data;

        return $this->request('POST', '/assignments', $options);
    }

    public function putAssignment($id, $data, array $options = array())
    {
        $options['json'] = $data;

        return $this->request('PUT', '/assignments/' . $id, $options);
    }

    public function deleteAssignment($id, array $options = array())
    {
        return $this->request('DELETE', '/assignments/' . $id, $options);
    }

    public function getMilestones($startDate = null, $endDate = null)
    {
        $options = $this->addDateQueryToOptions($startDate, $endDate);

        return $this->request('GET', '/milestones', $options)['milestones'];
    }

    protected function addDateQueryToOptions($startDate = null, $endDate = null, $options = array())
    {
        if (!array_key_exists('query', $options)) {
            $options['query'] = array();
        }
        if ($startDate !== null) {
            $options['query']['start_date'] = $startDate;
        }

        if ($endDate !== null) {
            $options['query']['end_date'] = $endDate;
        }

        return $options;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     *
     * @return array
     * @throws ForecastException
     */
    protected function request($method, $uri, array $options = array())
    {
        $response = $this->guzzle->request($method, $uri, $options);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new ForecastException('Forecast API error: ' . $response->getReasonPhrase(), $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @return Client
     */
    protected function makeClient()
    {
        $headers = array('Forecast-Account-Id' => $this->accountId);

        if ($this->apiToken) {
            $headers['Authorization'] = sprintf('Bearer %s', $this->apiToken);
        }

        return new Client(
            array(
                'base_uri' => $this->baseUri,
                'headers'  => $headers,
            )
        );
    }

    /**
     * @return Client
     */
    protected function makeAuthClient()
    {
        return new Client(
            array(
                'base_uri' => self::AUTH_BASE_URI,
                'cookies' => true,
            )
        );
    }
}
