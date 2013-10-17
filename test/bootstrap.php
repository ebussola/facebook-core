<?php
/**
 * Created by JetBrains PhpStorm.
 * User: usuario
 * Date: 17/10/13
 * Time: 15:13
 */

// simulate HTTP_HOST
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

require(__DIR__.'/../vendor/autoload.php');
require(__DIR__.'/stubs/AccessTokenData.php');

$config = include('config.php');