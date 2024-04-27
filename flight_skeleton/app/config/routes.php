<?php

use app\controllers\ApiExampleController;
use flight\Engine;
use flight\net\Router;

/** 
 * @var Router $router 
 * @var Engine $app
 */
$router->get('/', function() use ($app) {
	$app->render('welcome', [ 'message' => 'You are gonna do great things!' ]);
});

$router->get('/hallo/@name', function($name) {
	echo '<h1>Hello world! Oh hey '.$name.'!</h1>';
});

$router->group('/api', function() use ($router, $app) {
	$Api_Example_Controller = new ApiExampleController($app);
	$router->get('/users', [ $Api_Example_Controller, 'getUsers' ]);
	$router->get('/users/@id:[0-9]', [ $Api_Example_Controller, 'getUser' ]);
	$router->post('/users/@id:[0-9]', [ $Api_Example_Controller, 'updateUser' ]);
});

Flight::group('/cms', function(){
    Flight::route('GET /', function(){
        echo 'Admin home page';
    });
    Flight::route('GET /settings', function(){
        echo 'Admin settings';
    });
});

// 如果只提供一个匿名函数，它将在路由回调之前执行。
// 除了类（详见下文）之外，没有“后置”中间件函数
Flight::route('/hi', function() { echo ' Here I am!'; })->addMiddleware(function() {
    echo 'Middleware first!';
});

