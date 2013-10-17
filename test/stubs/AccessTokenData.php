<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 15:22
 */

class AccessTokenData implements \ebussola\facebook\AccessTokenData {

    private $long_access_token;

    /**
     * @var DateTime
     */
    private $expiration_datetime;

    /**
     * @param string $access_token
     */
    public function setLongAccessToken($long_access_token, $expires) {
        $token = array(
            'access_token' => $long_access_token,
            'expires' => $expires
        );

        $this->long_access_token = $token;
        $this->expiration_datetime = DateTime::createFromFormat('U', time()+$expires);
    }

    /**
     * @return string
     */
    public function getLongAccessToken() {
        return $this->long_access_token['access_token'];
    }

    /**
     * @return \DateTime
     */
    public function getExpirationDateTime() {
        return $this->expiration_datetime;
    }

    /**
     * @return void
     */
    public function clearLongAccessToken() {
        $this->long_access_token = null;
        $this->expiration_datetime = null;
    }
}