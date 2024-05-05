Flight 类是 Flight PHP 框架的静态表现形式。它提供了启动和停止框架、路由、渲染视图以及处理 HTTP
请求和响应的方法。此外，还允许注册和注销类及回调函数，用于框架的各种方法。Flight 类作为访问和控制框架功能的中心点。

Flight 类详述
概述： Flight 类是 Flight PHP 框架的核心静态类，它封装了一系列方法来启动、配置、路由、渲染视图以及处理 HTTP
请求与响应。该类的设计遵循面向过程的风格，通过静态方法提供框架的主要功能。
核心方法
启动与停止框架
start()：启动框架，开始处理请求。
stop(?int $code = null)：停止框架并发送响应，可选地指定状态码。
halt(int $code = 200, string $message = '')：立即停止框架，并可选地设置状态码和消息。
路由管理
route(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')：为URL模式绑定回调函数，支持所有HTTP方法。
group(string $pattern, callable $callback, callable[] $group_middlewares = [])：定义一组具有共同前缀的路由。
post/put/patch/delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
：分别为POST/PUT/PATCH/DELETE请求绑定特定的URL处理函数。
router()：返回 Router 实例，用于更细粒度的路由控制。
getUrl(string $alias, array $params = [])：根据别名生成URL。
自定义框架方法与过滤器
map(string $name, callable $callback)：创建自定义框架方法。
before/after(string $name, Closure $callback)：在框架方法执行前后添加过滤器（中间件）。
变量管理
set(string|array $key, mixed $value)：设置变量。
get(?string $key)：获取变量。
has(string $key)：检查变量是否存在。
clear(?string $key = null)：清除变量。
视图渲染
render(string $file, ?array $data = null, ?string $key = null)：渲染模板文件。
view()：返回 View 实例，用于视图操作。
请求与响应处理
request/response()：分别返回 Request 和 Response 实例，用于处理HTTP请求和响应。
redirect(string $url, int $code = 303)：重定向到另一个URL。
json/jsonp(mixed $data, ...)：发送JSON或JSONP响应。
error(Throwable $exception)：发送HTTP 500错误响应。
notFound()：发送HTTP 404未找到响应。
HTTP缓存
etag/lastModified(...)：实现ETag和Last-Modified HTTP缓存机制。
内部机制
构造函数与克隆：均为私有，防止外部实例化或克隆。
register/unregister：注册或注销类到框架方法，允许框架使用依赖注入。
__callStatic：处理静态方法调用，通过 Dispatcher 转发到实际的 Engine 实例处理。
初始化与引擎
在首次访问 app() 方法时，会初始化框架引擎 (Engine) 实例，并进行必要的设置。
综上所述，Flight 类提供了简洁而全面的API，使得开发者能够快速构建Web应用，从路由配置到视图渲染，再到请求响应处理，都集成在这一核心类中。

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

require_once __DIR__ . '/autoload.php';

/**
 * Flight 类是框架的静态表示形式。
 *
 * 它提供了一个中心点，用于访问框架的功能，包括路由、视图渲染和请求响应处理。
 * 通过插件和自定义方法，Flight 可以进行扩展。
 */
class Flight
{
    /** 框架引擎。 */
    private static Engine $engine;

    /** 应用程序是否已初始化。 */
    private static bool $initialized = false;

    /**
     * 禁止对象实例化。
     *
     * 此方法确保类不能被实例化，因为它是一个静态工具类。
     */
    private function __construct()
    {
    }

    /**
     * 禁止克隆类。
     *
     * 此方法确保类实例不能被克隆，保持其静态性质。
     */
    private function __clone()
    {
    }

    /**
     * 注册一个类到框架方法。
     *
     * 这允许将类轻松别名或创建复杂实例化过程的快捷方式。
     *
     * @param string $name 要注册的类/方法名称。
     * @param class-string<T> $class 要注册的完全限定类名。
     * @param array<int, mixed> $params 类的构造函数参数。
     * @param ?Closure(T $instance): void $callback 实例化后可选的回调函数，用于操作类实例。
     *
     * @template T of object
     */
    public static function register($name, $class, $params = [], $callback = null): void
    {
        static::__callStatic('register', [$name, $class, $params, $callback]);
    }

    /** 注销一个类。 */
    public static function unregister(string $methodName): void
    {
        static::__callStatic('unregister', [$methodName]);
    }

    /**
     * 处理静态方法调用。
     *
     * 此方法充当静态方法调用的调度器，将它们转发给适当的引擎组件。
     *
     * @param string $name 要调用的方法名称。
     * @param array<int, mixed> $params 方法调用的参数。
     *
     * @return mixed 调用方法的结果。
     * @throws Exception 如果方法调用不支持或执行时出现错误。
     */
    public static function __callStatic(string $name, array $params)
    {
        return Dispatcher::invokeMethod([self::app(), $name], $params);
    }

    /** @return Engine 应用程序实例 */
    public static function app(): Engine
    {
        // 如果尚未初始化框架引擎，则进行初始化。
        if (!self::$initialized) {
            require_once __DIR__ . '/autoload.php';

            self::setEngine(new Engine());
            self::$initialized = true;
        }

        return self::$engine;
    }

    /**
     * 设置引擎实例。
     *
     * 此方法允许设置 Engine 类的实例，它是 Flight 框架的核心组件。
     *
     * @param Engine $engine 要设置的引擎实例。
     */
    public static function setEngine(Engine $engine): void
    {
        self::$engine = $engine;
    }
}

```