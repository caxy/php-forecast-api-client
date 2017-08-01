<?php

namespace Caxy\ForecastApi;

use GuzzleHttp\Client;

class ForecastClient
{
    /**
     * @var Client
     */
    protected $guzzle;

    /**
     * ForecastClient constructor.
     */
    public function __construct($accountId, $apiToken, $baseUri = 'https://api.forecastapp.com')
    {
        $this->guzzle = new Client(
            array(
                'base_uri' => $baseUri,
                'headers'  => array(
                    'Forecast-Account-Id' => $accountId,
                    'Authorization'       => 'Bearer '.$apiToken,
                ),
            )
        );
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

    protected function request($method, $uri, array $options = array())
    {
        $response = $this->guzzle->request($method, $uri, $options);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 400) {
            throw new ForecastException('Forecast API error: ' . $response->getReasonPhrase(), $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}
