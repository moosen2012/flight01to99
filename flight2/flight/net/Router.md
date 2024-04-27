
Router 类是 Flight PHP 微型框架中的核心组件之一，负责将接收到的 HTTP 请求与预先定义的一系列 URL 模式进行匹配，并将请求分派给对应的回调函数进行处理。这个类实现了路由的基本功能，如添加、删除、分组和查找路由，以及支持多种 HTTP 方法和中间件。
属性
case_sensitive: 布尔值，表示在匹配 URL 时是否区分大小写，默认为 false（不区分）。
routes: 数组，存储已映射的所有路由对象（类型为 Route），每个元素的键为整数，值为对应的路由实例。
index: 整数，表示当前指向的路由索引，用于遍历 routes 数组。
group_prefix: 字符串，存储当前路由组的前缀。在使用路由组时，此前缀会自动添加到组内所有路由的 URL 前面。
group_middlewares: 数组，存储当前路由组应用的中间件。中间件是一系列可复用的函数或对象，用于对请求进行预处理、后处理或异常处理。
allowed_methods: 数组，包含所有允许的 HTTP 方法（如 GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS），这些方法可用于定义路由。
方法
getRoutes(): 返回当前已映射的所有路由对象组成的数组。
clear(): 清除路由器中的所有路由，即清空 routes 数组。
map(): 将一个 URL 模式与一个回调函数关联起来，定义一个新的路由。接收以下参数：
$pattern: 要匹配的 URL 模式字符串。
$callback: 回调函数，当 URL 与模式匹配时执行。
$pass_route: 布尔值，指示是否将匹配的路由对象作为参数传递给回调函数，默认为 false。
$route_alias: 为路由设置别名，便于通过别名而非 URL 访问路由。
此方法首先处理 URL 前缀（若有路由组），然后解析 URL 中可能包含的 HTTP 方法列表。接着，它创建一个新的 Route 对象，将中间件（若有路由组）添加到该对象，并将其添加到 routes 数组中。最后返回创建的 Route 对象。
get(), post(), put(), patch(), delete(): 分别对应五种常见的 HTTP 方法，它们都是 map() 方法的快捷方式，只需传入 URL 模式、回调函数及可选参数即可。
group(): 定义一组具有共同前缀和/或中间件的路由。接收以下参数：
$group_prefix: 组的 URL 前缀，如 /api/v1。
$callback: 回调函数，在其中定义属于该组的路由。
$group_middlewares: 可选的中间件数组，应用于该组内的所有路由。
在执行回调函数期间，临时修改路由器的 group_prefix 和 group_middlewares 属性，确保新定义的路由继承组的设置。回调函数执行完毕后，恢复原来的组设置。
route(): 根据给定的 Request 对象，查找并返回匹配的 Route 对象。若未找到匹配的路由，则返回 false。该方法依次检查每个路由是否匹配请求的 URL 和 HTTP 方法。
getUrlByAlias(): 根据给定的路由别名和参数，生成并返回对应的 URL。若找不到与别名匹配的路由，抛出异常。若找到了近似的别名，会在异常消息中建议使用该近似别名。
迭代器方法:
rewind(): 重置路由指针，使其指向数组的第一个元素。
valid(): 检查是否有更多路由可供迭代，返回布尔值。
current(): 返回当前路由（数组索引为 index 的元素）。
next(): 移动到下一个路由，更新 index。
reset(): 重置路由指针至第一个元素，等同于 rewind()。
总结：Router 类实现了 Flight 框架中的路由功能，包括定义、分组、查找和管理路由，支持多种 HTTP 方法和中间件，以及通过别名生成 URL。这些功能使得开发者能够灵活地组织和处理应用程序中的 URL 请求。

```php

<?php

declare(strict_types=1);

namespace flight\net;

use Exception;
use flight\net\Route;

/**
 * 负责将HTTP请求路由到指定的回调函数。
 * 路由器尝试将请求的URL与一系列URL模式进行匹配。
 */
class Router
{
    /**
     * 是否区分大小写匹配。
     */
    public bool $case_sensitive = false;

    /**
     * 映射的路由。
     *
     * @var array<int,Route> 路由数组
     */
    protected array $routes = [];

    /**
     * 当前路由的索引。
     */
    protected int $index = 0;

    /**
     * 当使用分组时，此变量用于所有路由的前缀。
     */
    protected string $group_prefix = '';

    /**
     * 分组中间件
     *
     * @var array<int,mixed> 分组中间件数组
     */
    protected array $group_middlewares = [];

    /**
     * 允许的HTTP方法。
     *
     * @var array<int, string> HTTP方法数组
     */
    protected array $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /**
     * 获取映射的路由。
     *
     * @return array<int,Route> 路由数组
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * 清除路由器中的所有路由。
     */
    public function clear(): void
    {
        $this->routes = [];
    }

    /**
     * 将URL模式映射到回调函数。
     *
     * @param string $pattern URL模式。
     * @param callable $callback 回调函数。
     * @param bool $pass_route 是否将匹配的路由对象传递给回调。
     * @param string $route_alias 路由别名。
     * @return Route 映射的路由对象。
     */
    public function map(string $pattern, callable $callback, bool $pass_route = false, string $route_alias = ''): Route
    {
        // 处理路由在分组中定义的情况
        if ($this->group_prefix !== '') {
            $url = ltrim($pattern);
        } else {
            $url = trim($pattern);
        }

        $methods = ['*'];

        // 从URL中解析HTTP方法
        if (false !== strpos($url, ' ')) {
            [$method, $url] = explode(' ', $url, 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        // 完成路由URL的处理
        if ($this->group_prefix !== '') {
            $url = rtrim($this->group_prefix . $url);
        }

        $route = new Route($url, $callback, $methods, $pass_route, $route_alias);

        // 添加分组中间件
        foreach ($this->group_middlewares as $gm) {
            $route->addMiddleware($gm);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * 创建一个GET路由。
     *
     * @param string   $pattern    URL模式。
     * @param callable $callback   回调函数。
     * @param bool     $pass_route 是否将匹配的路由对象传递给回调。
     * @param string   $alias      路由别名。
     * @return Route GET路由对象。
     */
    public function get(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('GET ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * 创建一个POST路由。
     *
     * @param string   $pattern    URL模式。
     * @param callable $callback   回调函数。
     * @param bool     $pass_route 是否将匹配的路由对象传递给回调。
     * @param string   $alias      路由别名。
     * @return Route POST路由对象。
     */
    public function post(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('POST ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * 创建一个PUT路由。
     *
     * @param string   $pattern    URL模式。
     * @param callable $callback   回调函数。
     * @param bool     $pass_route 是否将匹配的路由对象传递给回调。
     * @param string   $alias      路由别名。
     * @return Route PUT路由对象。
     */
    public function put(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('PUT ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * 创建一个PATCH路由。
     *
     * @param string   $pattern    URL模式。
     * @param callable $callback   回调函数。
     * @param bool     $pass_route 是否将匹配的路由对象传递给回调。
     * @param string   $alias      路由别名。
     * @return Route PATCH路由对象。
     */
    public function patch(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('PATCH ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * 创建一个DELETE路由。
     *
     * @param string   $pattern    URL模式。
     * @param callable $callback   回调函数。
     * @param bool     $pass_route 是否将匹配的路由对象传递给回调。
     * @param string   $alias      路由别名。
     * @return Route DELETE路由对象。
     */
    public function delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = ''): Route
    {
        return $this->map('DELETE ' . $pattern, $callback, $pass_route, $alias);
    }

    /**
     * 将一组路由分组在一起。
     *
     * @param string $group_prefix 分组的URL前缀。
     * @param callable $callback 调用的回调函数，持有Router类。
     * @param array<int, callable|object> $group_middlewares 分组中间件。
     */
    public function group(string $group_prefix, callable $callback, array $group_middlewares = []): void
    {
        $old_group_prefix = $this->group_prefix;
        $old_group_middlewares = $this->group_middlewares;
        $this->group_prefix .= $group_prefix;
        $this->group_middlewares = array_merge($this->group_middlewares, $group_middlewares);
        $callback($this);
        $this->group_prefix = $old_group_prefix;
        $this->group_middlewares = $old_group_middlewares;
    }

    /**
     * 处理当前请求的路由。
     *
     * @param Request $request 当前请求对象。
     * @return false|Route 匹配的路由对象或在无匹配时返回false。
     */
    public function route(Request $request)
    {
        $url_decoded = urldecode($request->url);
        while ($route = $this->current()) {
            if ($route->matchMethod($request->method) && $route->matchUrl($url_decoded, $this->case_sensitive)) {
                return $route;
            }
            $this->next();
        }

        return false;
    }

    /**
     * 根据别名获取路由的URL。
     *
     * @param string $alias 路由别名。
     * @param array<string,mixed> $params 传递给路由的参数。
     * @return string 路由的URL。
     * @throws Exception 当找不到具有给定别名的路由时抛出异常。
     */
    public function getUrlByAlias(string $alias, array $params = []): string
    {
        $potential_aliases = [];
        foreach ($this->routes as $route) {
            $potential_aliases[] = $route->alias;
            if ($route->matchAlias($alias)) {
                return $route->hydrateUrl($params);
            }
        }

        // 使用levenshtein距离找到最接近的匹配，并给出建议
        $closest_match = '';
        $closest_match_distance = 0;
        foreach ($potential_aliases as $potential_alias) {
            $levenshtein_distance = levenshtein($alias, $potential_alias);
            if ($levenshtein_distance > $closest_match_distance) {
                $closest_match = $potential_alias;
                $closest_match_distance = $levenshtein_distance;
            }
        }

        $exception_message = 'No route found with alias: \'' . $alias . '\'.';
        if ($closest_match !== '') {
            $exception_message .= ' Did you mean \'' . $closest_match . '\'?';
        }

        throw new Exception($exception_message);
    }

    /**
     * 重置当前路由索引。
     */
    public function rewind(): void
    {
        $this->index = 0;
    }

    /**
     * 检查是否可以迭代更多路由。
     *
     * @return bool 是否存在更多路由。
     */
    public function valid(): bool
    {
        return isset($this->routes[$this->index]);
    }

    /**
     * 获取当前路由。
     *
     * @return false|Route 当前路由对象或在无路由时返回false。
     */
    public function current()
    {
        return $this->routes[$this->index] ?? false;
    }

    /**
     * 获取下一个路由。
     */
    public function next(): void
    {
        $this->index++;
    }

    /**
     * 重置路由到第一个。
     */
    public function reset(): void
    {
        $this->index = 0;
    }
}



```