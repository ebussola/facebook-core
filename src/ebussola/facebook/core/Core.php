<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 16/10/13
 * Time: 16:22
 */

namespace ebussola\facebook\core;


use Doctrine\Common\Cache\FilesystemCache;
use ebussola\facebook\core\exception\OAuthException;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\DefaultCacheStorage;
use Guzzle\Service\Client;

class Core extends \BaseFacebook {

    /**
     * @see https://developers.facebook.com/docs/reference/api/batch/#limits
     */
    const FB_BATCH_LIMIT = 50;

    const MAX_REQUEST_ATTEMPTS = 10;

    /**
     * @var AccessTokenData
     */
    private $access_token_data;

    /**
     * @var string
     */
    private $scope;

    /**
     * @var string
     */
    private $redirect_uri;

    /**
     * @var bool
     */
    private $is_logged;

    /**
     * @var Client
     */
    private $guzzle_client;

    public function __construct($app_id, $secret, $redirect_uri, AccessTokenData $access_token_data, $scope='ads_management', $guzzle_client=null) {
        $this->scope = $scope;
        $this->redirect_uri = rtrim($redirect_uri, '/') . '/';
        $this->access_token_data = $access_token_data;

        if ($guzzle_client === null) {
            $guzzle_client = new Client();
        }
        $this->guzzle_client = $guzzle_client;

        parent::__construct(array(
            'appId' => $app_id,
            'secret' => $secret
        ));
    }

    /**
     * @return string
     */
    public function getLoginUrl($params = array()) {
        $params['scope'] = $this->scope;
        $params['redirect_uri'] = $this->redirect_uri;

        return parent::getLoginUrl($params);
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function getLogoutUrl($params = array()) {
        $this->checkFacebookConnection();

        if (isset($params['redirect_uri'])) {
            $params['next'] = $params['redirect_uri'];
        }

        return parent::getLogoutUrl($params);
    }

    /**
     * Exchanges the code -> short life token -> long life token
     */
    public function authenticate($code) {
        $params = $this->getShortLifeAccessToken($code);

        $this->setPersistentData('access_token', $params);
    }

    /**
     * @return boolean
     */
    public function isLogged() {
        if (is_null($this->is_logged)) {
            if ($this->access_token_data->getExpirationDateTime() <= new \DateTime('now')) {
                $this->is_logged = false;

            } else {
                $this->is_logged = ($this->getUser() === 0) ? false : true;
            }
        }

        return $this->is_logged;
    }

    /**
     * @param array $data
     * Data that will be sent to the Graph
     *
     * @param string|null $path
     * The path of the request. Some operations like Batch don't need it.
     * If the $path is complete, with all query parameters, $data will be ignored
     *
     * @return \stdClass[]
     */
    public function curl($data=array(), $path='', $method='post') {
        $fails = 0;
        $this->guzzle_client->setBaseUrl('https://graph.facebook.com');

        if (substr($path, 0, 26) != 'https://graph.facebook.com') {
            $data['access_token'] = $this->getAccessToken();
        }

        do {
            if ($fails > 0) {
                usleep($fails*20000 + rand(0, 1000000));
            }

            $result = null;

            switch ($method) {
                case 'get' :
                    $path = $this->buildQuery($data, $path);
                    $request = $this->guzzle_client->get($path);
                    break;

                case 'post' :
                    foreach ($data as &$data_param) {
                        if (is_array($data_param)) {
                            $data_param = json_encode($data_param);
                        }
                    }

                    $request = $this->guzzle_client->post($path, array(), $data);
                    break;

                default :
                    throw new \Exception('Available methods: post or get');
                    break;
            }

            try {
                $response = $request->send();
            } catch (ClientErrorResponseException $e) {
                throw new OAuthException('Error', 0, $e);
            }

            if ($fails > self::MAX_REQUEST_ATTEMPTS) {
                throw new \Exception('Failed to connect to server '.self::MAX_REQUEST_ATTEMPTS.' times');
            }
            $fails++;

            $result = json_decode($response->getBody(true), false, 512, JSON_BIGINT_AS_STRING);

        } while ($this->isNotValidResponse($result));

        if (isset($result->paging) && isset($result->paging->next) && $result->paging->next != null) {
            //$next_result = $this->curl(array(), $result->paging->next, 'get');
            usleep(1000);
            $next_result = $this->curl([], $result->paging->next, 'get');
            /** @noinspection PhpUndefinedFieldInspection */
            $result->data = array_merge($result->data, $next_result->data);
        }

        return $result;
    }

    /**
     * @param string $query
     *
     * @return mixed
     */
    public function fql($query) {
        $result = $this->curl(array('q' => $query), '/fql', 'get');

        return $result->data;
    }

    /**
     * @param array $requests
     * @return array
     */
    public function batchRequest(array $requests) {
        $count = count($requests);
        $results = array();
        $offset = 0;

        while ($offset < $count) {
            $part = array_slice($requests, $offset, self::FB_BATCH_LIMIT);
            $results_with_header = $this->curl(array('batch' => json_encode($part)));

            if (!empty($results_with_header)) {
                foreach ($results_with_header as $r) {
                    if ($r instanceof \stdClass && isset($r->body)) {
                        if ($r->code == 200) {
                            $results[] = json_decode($r->body, false, 512, JSON_BIGINT_AS_STRING);
                            $offset++;
                        } else {
                            throw new \Exception($r->body);
                        }
                    } else {
                        break;
                    }
                }
            } else {
                throw new \Exception('An error ocurred when making a batch request');
            }
        }

        return $results;
    }

    /**
     * @param array  $data
     * @param string $path
     * @param string $method
     *
     * @return array
     */
    public function createRequest($data, $path='', $method='post') {
        switch ($method) {
            case 'post' :
                return array(
                    'method' => 'post',
                    'relative_url' => $path,
                    'body' => $data
                );

            case 'get' :
                return array(
                    'method' => 'get',
                    'relative_url' => $this->buildQuery($data, $path)
                );
        }

        return null;
    }

    /**
     * @param $result
     *
     * @return bool
     */
    private function isNotValidResponse($result) {
        $is_class = $result instanceof \stdClass;
        //$not_valid = (!$is_class && $result === null);

        $not_valid = (
            (!$is_class && $result === null)
            || ($is_class && isset($result->error))
        );

        /** @noinspection PhpUndefinedFieldInspection */
        if ($not_valid && $result->error->type === "OAuthException") {
            /** @noinspection PhpUndefinedFieldInspection */
            throw new OAuthException($result->error->message);
        }

        return $not_valid;
    }

    /**
     * @throws \Exception
     */
    private function checkFacebookConnection() {
        if (!$this->isLogged()) {
            throw new \Exception('User not logged | Login Url: '.$this->getLoginUrl());
        }
    }

    /**
     * @param string $access_token
     *
     * @return string
     */
    private function getLongLifeAccessToken($access_token) {
        $url = "https://graph.facebook.com/oauth/access_token?client_id={$this->getAppId()}&client_secret={$this->getAppSecret()}&grant_type=fb_exchange_token&fb_exchange_token={$access_token}";

        return $this->processAccessTokenResponse($url);
    }

    /**
     * @param string $code
     *
     * @return string
     */
    private function getShortLifeAccessToken($code) {
        $url = "https://graph.facebook.com/oauth/access_token?client_id={$this->getAppId()}&redirect_uri={$this->redirect_uri}&client_secret={$this->getAppSecret()}&code={$code}";

        return $this->processAccessTokenResponse($url);
    }

    /**
     * @param string $url
     * @return string
     */
    private function processAccessTokenResponse($url) {
        $contents = file_get_contents($url);
        if ($contents === false) {
            throw new \Exception('Server communication problem');
        }
        if (!strstr($contents, '=')) {
            throw new \Exception('It\'s look like the Facebook API changed... something is wrong.');
        }

        $params = array();
        parse_str($contents, $params);

        return $params;
    }

    /**
     * Stores the given ($key, $value) pair, so that future calls to
     * getPersistentData($key) return $value. This call may be in another request.
     *
     * @param string $key
     * @param array  $value
     *
     * @return void
     */
    protected function setPersistentData($key, $value) {
        switch ($key) {
            case 'access_token' :
                $long_access_token = $this->getLongLifeAccessToken($value['access_token']);
                if (!isset($long_access_token['expires'])) {
                    // in case of API do not response the expire time, we set the default (60 days)
                    $long_access_token['expires'] = 5184000;
                }
                $this->access_token_data->setLongAccessToken($long_access_token['access_token'], $long_access_token['expires']);
                break;
        }
    }

    /**
     * Get the data for $key, persisted by BaseFacebook::setPersistentData()
     *
     * @param string  $key     The key of the data to retrieve
     * @param boolean $default The default value to return if $key is not found
     *
     * @return mixed
     */
    protected function getPersistentData($key, $default = false) {
        switch ($key) {
            case 'access_token':
                $value = $this->access_token_data->getLongAccessToken();
                break;

            default:
                $value = $default;
                break;
        }

        return $value;
    }

    /**
     * Clear the data with $key from the persistent storage
     *
     * @param string $key
     *
     * @return void
     */
    protected function clearPersistentData($key) {
        switch ($key) {
            case 'access_token' :
                $this->access_token_data->clearLongAccessToken();
                break;
        }
    }

    /**
     * Clear all data from the persistent storage
     *
     * @return void
     */
    protected function clearAllPersistentData() {
        $this->access_token_data->clearLongAccessToken();
    }

    /**
     * @param $data
     * @param $url
     *
     * @return string
     */
    private function buildQuery($data, $url) {
        if (count($data) > 0) {
            $url = $url . '?' . http_build_query($data);

            return $url;
        }

        return $url;
    }

}