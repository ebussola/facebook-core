<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 17:33
 */

class ExpiredLoginTest extends PHPUnit_Framework_TestCase {

    public function testExpiredToken() {
        global $config;
        $token = include(__DIR__.'/long_access_token.php');

        $access_token_data = new AccessTokenData();
        $access_token_data->setLongAccessToken($token['access_token'], 1);
        $core = new \ebussola\facebook\Core($config['app_id'], $config['secret'], $config['redirect_uri'], $access_token_data);

        sleep(2);
        $this->assertFalse($core->isLogged());
    }

}
