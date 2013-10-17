<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 17:07
 */

class CoreTest extends PHPUnit_Framework_TestCase {

    /**
     * @var \ebussola\facebook\Core
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
        $this->core = new \ebussola\facebook\Core($app_id, $secret, $redirect_uri, $access_token_data);
    }

    public function testLogoutUrl() {
        $logout_url = $this->core->getLogoutUrl('http://localhost');

        $this->assertContains('https://www.facebook.com/logout.php', $logout_url);
    }

}