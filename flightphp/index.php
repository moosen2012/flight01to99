<?php

// If you're using Composer, require the autoloader.
require 'vendor/autoload.php';
// if you're not using Composer, load the framework directly
// require 'flight/Flight.php';
require 'app/quickstart/article/Article.php';
// Then define a route and assign a function to handle the request.
Flight::route('/', function () {
    echo 'hello world!';
});
class Greeting {
    public static function hello() {
        echo 'hallo!';
    }
}
Flight::route('/qs', array("Greeting","hello"));

// Finally, start the framework.
Flight::start();