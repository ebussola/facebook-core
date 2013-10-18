<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 15:10
 */

class AuthTest extends PHPUnit_Framework_TestCase {

    /**
     * @var \ebussola\facebook\core\Core
     */
    private $core;

    /**
     * @var \ebussola\facebook\core\AccessTokenData
     */
    private $access_token_data;

    public function setUp() {
        global $config;

        $app_id = $config['app_id'];
        $secret = $config['secret'];
        $redirect_uri = $config['redirect_uri'];

        $this->access_token_data = new AccessTokenData();
        $this->core = new \ebussola\facebook\core\Core($app_id, $secret, $redirect_uri, $this->access_token_data);
    }

    public function testGetLoginUrl() {
        if (!$this->core->isLogged()) {
            $login = $this->core->getLoginUrl();
            echo $login;
        } else {
            $this->fail();
        }
    }

    public function testAuthentication() {
        $code = include(__DIR__.'/code.php');
        $this->core->authenticate($code);

        echo 'Here is your token: '.serialize(array(
                'access_token' => $this->access_token_data->getLongAccessToken(),
                'expires' => 5000
            ));

        $this->assertTrue($this->core->isLogged());
    }

}