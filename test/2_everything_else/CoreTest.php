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
        $logout_url = $this->core->getLogoutUrl('http://localhost');

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
        $results = $this->core->batchRequest(array(
            $this->core->createRequest(array(), '/me', 'get'),
            $this->core->createRequest(array('limit' => 5), '/me/friends', 'get')
        ));


        $this->assertObjectHasAttribute('id', $results[0]);

        $this->assertCount(5, $results[1]->data);
    }

}