Route 类是基于 PHP 的 Web 应用程序路由系统中的一个核心组件，其主要职责是将传入的 HTTP 请求映射到合适的回调函数，确保应用程序能够对各种 URL 以定制化功能进行响应。此类封装了多个属性和方法来支持这一过程。
属性：
pattern：表示路由应匹配的 URL 模式字符串。该模式可能包含占位符、通配符或其他特殊语法，用于定义 URL 中的可变段。
callback：当路由匹配时将执行的可调用函数或对象方法。这是处理与定义的 URL 模式对应的请求的应用逻辑所在位置。
methods：包含此路由允许的 HTTP 方法（如 GET、POST、PUT、DELETE）的数组。可以使用通配符（*）接受任何 HTTP 方法。
params：存储从匹配 URL 提取的命名参数值的数组。这些参数通常在回调函数中使用，以便处理请求。
regex：由 pattern 属性生成的正则表达式，用于内部 URL 匹配和参数提取。
splat：捕获 URL 在主模式匹配后剩余部分的字符串，常用于实现类似通配符的行为。
pass：一个布尔值，指示是否应将 Route 实例自身作为参数传递给回调函数。
alias：为路由提供一个替代标识符的字符串，使开发者能用更简单的名称（如 'login' 而非 '/admin/login'）引用它。
middleware：包含应在此路由回调之前执行的中间件函数或类的数组。中间件可以执行身份验证、输入验证、日志记录等任务。
方法：
__construct：构造函数使用提供的 pattern、callback、methods、pass 和 alias 初始化一个新的 Route 实例。它将这些值设置为类属性，为后续的匹配和处理做好准备。
matchUrl：
给定 url 和可选的 case_sensitive 标志，此方法判断 URL 是否与路由的模式匹配。
如果模式是通配符（*）或与 URL 完全相同，则方法立即返回 true。
它处理类似通配符的行为（模式末尾的 *），从 URL 中提取 "splat" 内容。
该方法根据模式生成一个正则表达式，将占位符替换为命名捕获组，并考虑可选的尾部斜杠。
然后使用这个正则表达式匹配 URL 并提取命名参数。如果找到匹配项，参数将被存储在 params 属性中，生成的正则表达式将保存在 regex 属性中。方法在成功匹配时返回 true；否则返回 false。
matchMethod：给定一个 http_method，此方法检查该方法是否包含在路由的 methods 数组中，或者是否存在通配符（*）。如果存在匹配项，返回 true，表明路由接受指定的 HTTP 方法。
matchAlias：接收一个 alias 作为输入，并将其与路由的 alias 属性进行比较。如果它们相同，则返回 true，表明路由具有指定的别名。
hydrateUrl：给定一个 params 数组，此方法将路由的 pattern 中的占位符替换为它们在 params 数组中对应的值。它处理可选参数并修剪尾部斜杠，返回生成的已水合 URL 字符串。
setAlias：使用提供的 alias 字符串更新路由的 alias 属性，并返回当前 Route 实例（支持方法链式调用）。
addMiddleware：接受单个可调用或可调用数组作为 middleware。如果提供的是数组，它会将新的中间件与现有的 middleware 数组合并。否则，它将单个可调用追加到数组中。该方法返回当前 Route 实例，以支持方法链式调用。
总结来说，Route 类定义了 PHP 应用程序中的单一路由，包括其 URL 模式、关联的回调函数、接受的 HTTP 方法以及诸如参数、别名和中间件等辅助信息。此类提供了用于确定给定 URL 和 HTTP 方法是否匹配路由、提取和水合参数以及管理路由元数据的方法。

```php

<?php

declare(strict_types=1);

namespace flight\net;

/**
 * 负责将HTTP请求路由到指定的回调函数。路由器尝试将请求的URL与一系列URL模式进行匹配。
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Route
{
    /**
     * URL模式
     */
    public string $pattern;

    /**
     * 回调函数
     *
     * @var mixed
     */
    public $callback;

    /**
     * HTTP方法
     *
     * @var array<int, string>
     */
    public array $methods = [];

    /**
     * 路由参数
     *
     * @var array<int, ?string>
     */
    public array $params = [];

    /**
     * 匹配的正则表达式
     */
    public ?string $regex = null;

    /**
     * URL通配符内容
     */
    public string $splat = '';

    /**
     * 在回调参数中传递自身
     */
    public bool $pass = false;

    /**
     * 别名是一种通过简单名称（如'login'）来识别路由的方法，而不是像/admin/login这样的完整路径。
     */
    public string $alias = '';

    /**
     * 将要应用于该路由的中间件
     *
     * @var array<int,callable|object>
     */
    public array $middleware = [];

    /**
     * 构造函数。
     *
     * @param string $pattern URL模式
     * @param callable $callback 回调函数
     * @param array<int, string> $methods HTTP方法
     * @param bool $pass 在回调参数中传递自身
     * @param string $alias 路由别名
     */
    public function __construct(string $pattern, callable $callback, array $methods, bool $pass, string $alias = '')
    {
        $this->pattern = $pattern;
        $this->callback = $callback;
        $this->methods = $methods;
        $this->pass = $pass;
        $this->alias = $alias;
    }

    /**
     * 检查URL是否与路由模式匹配。同时解析URL中的命名参数。
     *
     * @param string $url 请求的URL
     * @param bool $case_sensitive 匹配时是否区分大小写
     *
     * @return bool 匹配状态
     */
    public function matchUrl(string $url, bool $case_sensitive = false): bool
    {
        // 通配符匹配或精确匹配
        if ('*' === $this->pattern || $this->pattern === $url) {
            return true;
        }

        $ids = [];
        $last_char = substr($this->pattern, -1);

        // 获取splat
        if ($last_char === '*') {
            $n = 0;
            $len = \strlen($url);
            $count = substr_count($this->pattern, '/');

            for ($i = 0; $i < $len; $i++) {
                if ($url[$i] === '/') {
                    $n++;
                }
                if ($n === $count) {
                    break;
                }
            }

            $this->splat = strval(substr($url, $i + 1));
        }

        // 构建匹配的正则表达式
        $regex = str_replace([')', '/*'], [')?', '(/?|/.*?)'], $this->pattern);

        $regex = preg_replace_callback(
            '#@([\w]+)(:([^/\(\)]*))?#',
            static function ($matches) use (&$ids) {
                $ids[$matches[1]] = null;
                if (isset($matches[3])) {
                    return '(?P<' . $matches[1] . '>' . $matches[3] . ')';
                }

                return '(?P<' . $matches[1] . '>[^/\?]+)';
            },
            $regex
        );

        if ('/' === $last_char) { // 修复尾部斜杠
            $regex .= '?';
        } else { // 允许尾部斜杠
            $regex .= '/?';
        }

        // 尝试匹配路由和命名参数
        if (preg_match('#^' . $regex . '(?:\?[\s\S]*)?$#' . (($case_sensitive) ? '' : 'i'), $url, $matches)) {
            foreach ($ids as $k => $v) {
                $this->params[$k] = (\array_key_exists($k, $matches)) ? urldecode($matches[$k]) : null;
            }

            $this->regex = $regex;

            return true;
        }

        return false;
    }

    /**
     * 检查HTTP方法是否与路由方法匹配。
     *
     * @param string $method HTTP方法
     *
     * @return bool 匹配状态
     */
    public function matchMethod(string $method): bool
    {
        return \count(array_intersect([$method, '*'], $this->methods)) > 0;
    }

    /**
     * 检查别名是否与路由别名匹配。
     */
    public function matchAlias(string $alias): bool
    {
        return $this->alias === $alias;
    }

    /**
     * 使用给定的参数为路由URL注水
     *
     * @param array<string,mixed> $params 传递给路由的参数
     */
    public function hydrateUrl(array $params = []): string
    {
        $url = preg_replace_callback("/(?:@([a-zA-Z0-9]+)(?:\:([^\/]+))?\)*)/i", function ($match) use ($params) {
            if (isset($match[1]) && isset($params[$match[1]])) {
                return $params[$match[1]];
            }
        }, $this->pattern);

        // 捕获潜在的可选参数
        $url = str_replace('(/', '/', $url);
        // 去除任何尾部斜杠
        if ($url !== '/') {
            $url = rtrim($url, '/');
        }
        return $url;
    }

    /**
     * 设置路由别名
     *
     * @return $this
     */
    public function setAlias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * 添加路由中间件
     *
     * @param array<int,callable>|callable $middleware
     *
     * @return self
     */
    public function addMiddleware($middleware): self
    {
        if (is_array($middleware) === true) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }
}


```
