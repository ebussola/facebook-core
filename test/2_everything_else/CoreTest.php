<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 17:07
 */

class CoreTest extends PHPUnit_Framework_TestCase {

    /**
     * @var \ebussola\facebook\core\Core
     */
    private $core;

    public function setUp() {
        global $config;
        $token = include(__DIR__.'/long_access_token.php');

        $app_id = $config['app_id'];
        $secret = $config['secret'];
        $redirect_uri = $config['redirect_uri'];

        $access_token_data = new AccessTokenData();
        $access_token_data->setLongAccessToken($token['access_token'], $token['expires']);
        $this->core = new \ebussola\facebook\core\Core($app_id, $secret, $redirect_uri, $access_token_data);
    }

    public function testLogoutUrl() {
        $logout_url = $this->core->getLogoutUrl(array('redirect_uri' => 'http://localhost'));

        $this->assertContains('https://www.facebook.com/logout.php', $logout_url);
    }

    public function testCurl() {
        $result = $this->core->curl(array(), '/me', 'get');
        $this->assertObjectHasAttribute('id', $result);
    }

    public function testCreateRequest() {
        $request = $this->core->createRequest(array(), '/me', 'get');
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('relative_url', $request);
        $this->assertEquals('/me', $request['relative_url']);
        $this->assertEquals('get', $request['method']);

        $data = array(
            'limit' => 5,
            'param1' => 'value'
        );
        $request = $this->core->createRequest($data, '/me/friends', 'get');
        $this->assertEquals('/me/friends?limit=5&param1=value', $request['relative_url']);

        $request = $this->core->createRequest(array(), '/me', 'post');
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('relative_url', $request);
        $this->assertArrayHasKey('body', $request);
        $this->assertEquals('post', $request['method']);
        $this->assertEquals('/me', $request['relative_url']);
        $this->assertEquals(array(), $request['body']);

        $data = array(
            'limit' => 5,
            'param1' => 'value'
        );
        $request = $this->core->createRequest($data, '/me/friends', 'post');
        $this->assertArrayHasKey('method', $request);
        $this->assertArrayHasKey('relative_url', $request);
        $this->assertArrayHasKey('body', $request);
        $this->assertEquals('post', $request['method']);
        $this->assertEquals('/me/friends', $request['relative_url']);
        $this->assertEquals($data, $request['body']);
    }

    public function testBatchRequest() {
        // small requests size
        $results = $this->core->batchRequest(array(
            $this->core->createRequest(array(), '/me', 'get'),
            $this->core->createRequest(array('limit' => 5), '/me/friends', 'get')
        ));
        $this->assertObjectHasAttribute('id', $results[0]);
        $this->assertCount(5, $results[1]->data);

        // big requests size
        $requests = array();
        for ($i=0 ; $i<200 ; $i++) {
            $requests[] = $this->core->createRequest(array(), '/me', 'get');
        }
        $results = $this->core->batchRequest($requests);

        $this->assertCount(200, $results);
        foreach ($results as $result) {
            $this->assertObjectHasAttribute('id', $result);
        }
    }

    public function testOAuthException() {
        $this->setExpectedException('\ebussola\facebook\core\exception\OAuthException');

        global $config;

        $app_id = $config['app_id'];
        $secret = $config['secret'];
        $redirect_uri = $config['redirect_uri'];

        $access_token_data = new AccessTokenData();
        $access_token_data->setLongAccessToken('foo', 5000);
        $this->core = new \ebussola\facebook\core\Core($app_id, $secret, $redirect_uri, $access_token_data);

        // any request, just to get the error
        $this->core->curl(array(), '/me', 'get');
    }

    public function testFql() {
        $profile = $this->core->fql('select id, name, url from profile where id = me()')[0];
        $this->assertNotNull($profile->id);
        $this->assertNotNull($profile->name);
        $this->assertNotNull($profile->url);
    }

    public function testPagination() {
        $friendlists = $this->core->fql('select count, flid from friendlist where owner = me()');
        usort($friendlists, function($a, $b) {
            if ($a->count == $b->count) {
                return 0;
            }

            return ($a->count > $b->count) ? -1 : 1;
        });

        $list = reset($friendlists);
        $page_size = ceil($list->count / 5);

        $test_result = $this->core->curl(array('limit' => $page_size), '/'.$list->flid.'/members', 'get');

        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertCount($list->count, $test_result->data);
    }

}