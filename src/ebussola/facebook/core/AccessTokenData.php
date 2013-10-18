<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 14:01
 */

namespace ebussola\facebook\core;


interface AccessTokenData {

    /**
     * @param string $access_token
     */
    public function setLongAccessToken($long_access_token, $expires);

    /**
     * @return string
     */
    public function getLongAccessToken();

    /**
     * @return \DateTime
     */
    public function getExpirationDateTime();

    /**
     * @return void
     */
    public function clearLongAccessToken();

}