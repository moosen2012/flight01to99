FlightPHP 是一个微型 PHP 框架，专注于简化 Web 应用程序的开发。它的设计思路主要围绕着以下几个方面：

1. **简洁性**：
   
   - FlightPHP 的设计非常简洁，整个框架只有一个 PHP 文件，使得它非常容易理解和学习。
   - 框架的核心思想是“只做一件事并且尽量做好”，它不包含多余的功能，专注于路由和处理 HTTP 请求。

2. **无依赖**：
   
   - FlightPHP 是一个无依赖的框架，不依赖于其他任何库或扩展，这使得它非常轻量级且易于部署和使用。

3. **灵活的路由系统**：
   
   - FlightPHP 提供了灵活且易于使用的路由系统，使得开发者可以轻松地定义 URL 与处理逻辑之间的映射关系。
   - 路由系统支持 RESTful 风格的路由，允许开发者定义 GET、POST、PUT、DELETE 等不同类型的路由。

4. **无限制的自由度**：
   
   - FlightPHP 框架本身没有强加任何规范，开发者可以根据自己的需求和喜好组织代码结构，选择合适的模板引擎、ORM 等组件来配合使用。

5. **易于扩展**：
   
   - 尽管 FlightPHP 本身非常精简，但它提供了扩展的接口，允许开发者通过编写插件来扩展框架的功能，从而满足特定项目的需求。

6. **自定义 HTTP 响应**：
   
   - FlightPHP 允许开发者完全控制 HTTP 响应，包括设置状态码、设置响应头、输出响应体等，这使得开发者可以轻松地实现自定义的错误处理、跨域请求等功能。

总体而言，FlightPHP 的设计思路是简单、灵活、易于扩展，并且尽量减少不必要的复杂性和依赖，使得开发者可以快速地构建轻量级且高性能的 Web 应用程序。

二、源码分析

Flight.php

```php
<?php

declare(strict_types=1);

use flight\core\Dispatcher;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use flight\net\Route;

/**
 * The Flight class is a static representation of the framework.
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 *
 * # Core methods
 * @method  static void start() Starts the framework.
 * @method  static void path(string $path) Adds a path for autoloading classes.
 * @method  static void stop() Stops the framework and sends a response.
 * @method  static void halt(int $code = 200, string $message = '')
 * Stop the framework with an optional status code and message.
 *
 * # Routing
 * @method  static Route route(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Maps a URL pattern to a callback with all applicable methods.
 * @method  static void  group(string $pattern, callable $callback, array $group_middlewares = [])
 * Groups a set of routes together under a common prefix.
 * @method  static Route post(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a POST URL to a callback function.
 * @method  static Route put(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a PUT URL to a callback function.
 * @method  static Route patch(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a PATCH URL to a callback function.
 * @method  static Route delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a DELETE URL to a callback function.
 * @method  static Router router() Returns Router instance.
 * @method  static string getUrl(string $alias) Gets a url from an alias
 *
 * @method  static void map(string $name, callable $callback) Creates a custom framework method.
 *
 * @method  static void before($name, $callback) Adds a filter before a framework method.
 * @method  static void after($name, $callback) Adds a filter after a framework method.
 *
 * @method  static void set($key, $value) Sets a variable.
 * @method  static mixed get($key) Gets a variable.
 * @method  static bool has($key) Checks if a variable is set.
 * @method  static void clear($key = null) Clears a variable.
 *
 * # Views
 * @method  static void render($file, array $data = null, $key = null) Renders a template file.
 * @method  static View view() Returns View instance.
 *
 * # Request-Response
 * @method  static Request request() Returns Request instance.
 * @method  static Response response() Returns Response instance.
 * @method  static void redirect($url, $code = 303) Redirects to another URL.
 * @method  static void json($data, $code = 200, $encode = true, $charset = "utf8", $encodeOption = 0, $encodeDepth = 512) Sends a JSON response.
 * @method  static void jsonp($data, $param = 'jsonp', $code = 200, $encode = true, $charset = "utf8", $encodeOption = 0, $encodeDepth = 512) Sends a JSONP response.
 * @method  static void error($exception) Sends an HTTP 500 response.
 * @method  static void notFound() Sends an HTTP 404 response.
 *
 * # HTTP caching
 * @method  static void etag($id, $type = 'strong') Performs ETag HTTP caching.
 * @method  static void lastModified($time) Performs last modified HTTP caching.
 */
// phpcs:ignoreFile Generic.Files.LineLength.TooLong, PSR1.Classes.ClassDeclaration.MissingNamespace
class Flight 
{
    /**
     * Framework engine.
     *
     * @var Engine $engine
     */
    private static Engine $engine;

    /**
     * Whether or not the app has been initialized
     *
     * @var boolean
     */
    private static bool $initialized = false;

    /**
     * Don't allow object instantiation
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Forbid cloning the class
     *
     * @codeCoverageIgnore
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Registers a class to a framework method.
     * @template T of object
     * @param  string $name Static method name
     * ```
     * Flight::register('user', User::class);
     *
     * Flight::user(); # <- Return a User instance
     * ```
     * @param  class-string<T> $class Fully Qualified Class Name
     * @param  array<int, mixed>  $params   Class constructor params
     * @param  ?Closure(T $instance): void $callback Perform actions with the instance
     * @return void
     */
    public static function register($name, $class, $params = [], $callback = null)
    {
        static::__callStatic('register', func_get_args());
    }

    /** Unregisters a class. */
    public static function unregister(string $methodName): void
    {
        static::__callStatic('unregister', func_get_args());
    }

    /**
     * Handles calls to static methods.
     *
     * @param string $name   Method name
     * @param array<int, mixed>  $params Method parameters
     *
     * @throws Exception
     *
     * @return mixed Callback results
     */
    public static function __callStatic(string $name, array $params)
    {
        $app = self::app();

        return Dispatcher::invokeMethod([$app, $name], $params);
    }

    /**
     * @return Engine Application instance
     */
    public static function app(): Engine
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/autoload.php';

            self::setEngine(new Engine());

            self::$initialized = true;
        }

        return self::$engine;
    }

    /**
     * Set the engine instance
     *
     * @param Engine $engine Vroom vroom!
     */
    public static function setEngine(Engine $engine): void
    {
        self::$engine = $engine;
    }
}
```

说明：

FlightPHP 的 `flight.php` 文件是整个框架的核心文件，它包含了 `Flight` 类的定义以及一些静态方法和静态属性的声明。下面是对该文件的主要内容进行分析：

1. `Flight` 类的声明：
   
   - `Flight` 类是框架的静态表示，它包含了一些静态方法和静态属性，用于管理和控制框架的运行。
   - 类中定义了私有的静态属性 `$engine`，用于存储框架的引擎实例。
   - 类中定义了一个私有的静态属性 `$initialized`，用于标识应用是否已经初始化。

2. 构造方法和克隆方法：
   
   - `Flight` 类的构造方法和克隆方法都被设置为私有，防止类被实例化和复制。

3. `register()` 方法和 `unregister()` 方法：
   
   - `register()` 方法用于将类注册到框架的静态方法中，以便通过静态方法调用该类。
   - `unregister()` 方法用于取消注册已注册的类。

4. 魔术方法 `__callStatic()`：
   
   - `__callStatic()` 方法用于处理对静态方法的调用，将调用转发给框架引擎的相应方法进行处理。

5. `app()` 方法：
   
   - `app()` 方法用于获取框架的引擎实例。
   - 如果框架尚未初始化，则会加载框架所需的自动加载文件，并创建框架引擎实例。

6. `setEngine()` 方法：
   
   - `setEngine()` 方法用于设置框架的引擎实例。

总体来说，`flight.php` 文件定义了 `Flight` 类以及一些静态方法和静态属性，这些方法和属性用于管理和控制 FlightPHP 框架的运行。通过该文件，可以了解 FlightPHP 框架的基本架构和运行机制。
