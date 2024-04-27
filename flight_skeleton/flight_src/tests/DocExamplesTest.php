<?php

declare(strict_types=1);

namespace tests;

use Exception;
use Flight;
use flight\Engine;
use PHPUnit\Framework\TestCase;
use Throwable;

class DocExamplesTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
        Flight::init();
        Flight::setEngine(new Engine());
    }

    protected function tearDown(): void
    {
        unset($_REQUEST);
        unset($_SERVER);
        Flight::clear();
    }

    public function testMapNotFoundMethod()
    {
        Flight::map('notFound', function () {
            Flight::json([], 404);
        });

        Flight::request()->url = '/not-found';

        Flight::route('/', function () {
            echo 'hello world!';
        });

        Flight::start();
        ob_get_clean();
        $this->assertEquals(404, Flight::response()->status());
        $this->assertEquals('[]', Flight::response()->getBody());
    }

    public function testMapErrorMethod()
    {
        Flight::map('error', function (Throwable $error) {
            // Handle error
            echo 'Custom: ' . $error->getMessage();
        });

        Flight::app()->handleException(new Exception('Error'));
        $this->expectOutputString('Custom: Error');
    }
}
