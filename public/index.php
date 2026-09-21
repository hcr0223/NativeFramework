<?php

require_once __DIR__.'/../vendor/autoload.php';

use Core\Router;

$router = new Router();

// Routes Format: $router->add('HTTP_METHOD', '/url-pattern', 'Controller@method');
$router->add('GET', '/', 'HomeController@index');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

$router->dispatch($requestUri, $requestMethod);