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

    /**
     * Step 1
     * You must execute only this test first to get the auth-login
     *
     * You can open an server on the auth_test_server to receive the code from the server
     * eg.: test/auth_test_server$ php -S localhost:8080
     */
    public function testGetLoginUrl() {
        if (!$this->core->isLogged()) {
            $login = $this->core->getLoginUrl();
            echo $login;
        } else {
            $this->fail();
        }
    }

    /**
     * Step 2
     * Once you have the code, paste it on code.php and execute this one.
     *
     * Again, it will retrieve the valid token.
     * Copy and paste on test/everything_else/long_access_token.php
     *
     * Now you can execute all the tests inside the everything_else folder
     */
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