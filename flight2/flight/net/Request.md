Request 类用于封装 PHP 应用程序中接收到的 HTTP 请求的详细信息，为访问请求属性及简化对 $_GET、$_POST、$_COOKIE 和 $_FILES 等超全局变量的操作提供便利。以下是该类及其属性和方法的详细中文描述：
类属性：
Request 类包含众多公共属性，每个属性代表请求的特定方面：
url：客户端请求的 URL。
base：URL 的父级子目录，根据脚本位置计算得出。
method：请求方法（如 GET、POST、PUT 或 DELETE）。
referrer：发起请求的页面 URL（如有）。
ip：发起请求的客户端 IP 地址。
ajax：一个布尔值，表示请求是否通过 AJAX 发送。
scheme：使用的服务器协议（http 或 https）。
user_agent：客户端发送的浏览器信息。
type：请求负载的内容类型（例如 "application/x-www-form-urlencoded"）。
length：请求负载长度（如有）。
query：一个 Collection 对象，包含查询字符串参数。
data：一个 Collection 对象，存储 POST 参数。
cookies：一个 Collection 对象，包含 cookie 参数。
files：一个 Collection 对象，表示上传的文件。
secure：一个布尔值，指示连接是否安全（HTTPS）。
accept：客户端发送的 HTTP 接受参数。
proxy_ip：反向代理看到的客户端 IP 地址（如有）。
host：HTTP 主机名。
此外，还有两个私有属性：
stream_path：一个字符串，指定读取请求体的路径（默认为 "php://input"）。
body：原始 HTTP 请求体，初始为空，需要时填充。
构造函数：
__construct 方法使用默认值或通过数组传递的自定义配置初始化 Request 对象。如果没有提供配置，则从超级全局变量和服务器变量中提取相关信息以填充类属性：
url：从 $_SERVER['REQUEST_URI'] 中派生，替换特殊字符（如 "@"）。
base：根据 $_SERVER['SCRIPT_NAME'] 计算，确保正确的目录分隔符和 URL 编码。
method：通过 self::getMethod() 获取，检查 $_SERVER['REQUEST_METHOD'] 及可能存在的覆盖项。
referrer、ip、ajax、scheme、user_agent、type、length、query、data、cookies、files、secure、accept、proxy_ip 和 host 分别从相应的服务器变量或基于它们计算得出。
填充这些属性后，构造函数调用 init 方法完成初始化过程。
方法：
init：接收一个请求属性数组，并将它们设置到当前实例。它还执行额外处理，如在 URL 中移除基目录（如果存在）、合并 URL 查询参数与 $_GET，以及在内容类型指示 JSON 负载时解析 JSON 输入。
getBody：获取原始 HTTP 请求体。首先检查 body 属性是否已填充。如果没有，针对非幂等方法（POST、PUT、DELETE、PATCH），从指定的流路径（默认为 "php://input"）读取请求体。检索到的请求体随后保存在 body 属性中供后续使用。
getMethod：返回请求方法。检查 $_SERVER['REQUEST_METHOD'] 及可能存在的覆盖项（通过 HTTP_X_HTTP_METHOD_OVERRIDE 和 _method 查询参数）。
getProxyIpAddress：从存在的转发头（如 HTTP_CLIENT_IP、HTTP_X_FORWARDED_FOR 等）中提取实际远程 IP 地址。如果这些头均未提供有效 IP 地址，则返回空字符串。
getVar：一个实用方法，从 $_SERVER 中检索变量，如果变量未设置则返回指定的 $default 值。
getHeader：从 $_SERVER 中检索特定的 HTTP 头，将头名称转换为适当的格式（添加 HTTP_ 前缀并转为大写且下划线分隔）。如果未找到该头，则返回默认值。
getHeaders：从 $_SERVER 中检索所有 HTTP 头，将它们的名称转换为更易读的格式（单词首字母大写并用连字符分隔）。
parseQuery：从给定 URL 解析查询参数为关联数组。
getScheme：根据多个服务器变量（包括 HTTPS、HTTP_X_FORWARDED_PROTO、HTTP_FRONT_END_HTTPS 和 REQUEST_SCHEME）确定请求方案（HTTP 或 HTTPS）。
总结来说，Request 类作为一个方便的容器，包含了与接收到的 HTTP 请求相关的所有重要信息。它简化了访问和操作请求属性的过程，处理查询字符串和 JSON 负载的解析，并提供了获取头信息和确定请求方案的实用方法。

```php

<?php

declare(strict_types=1);

namespace flight\net;

use flight\util\Collection;

/**
 * 表示一个HTTP请求的类。它将所有的超全局变量（$_GET, $_POST, $_COOKIE, 和 $_FILES）
 * 的数据存储并可以通过该对象访问。
 * 
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Request
{
    // 请求的URL
    public string $url;

    // URL的父级子目录
    public string $base;

    // 请求方法（GET, POST, PUT, DELETE）
    public string $method;

    // 引用URL
    public string $referrer;

    // 客户端的IP地址
    public string $ip;

    // 请求是否为AJAX请求
    public bool $ajax;

    // 服务器协议（http, https）
    public string $scheme;

    // 浏览器信息
    public string $user_agent;

    // 内容类型
    public string $type;

    // 内容长度
    public int $length;

    // 查询字符串参数
    public Collection $query;

    // POST参数
    public Collection $data;

    // Cookie参数
    public Collection $cookies;

    // 上传的文件
    public Collection $files;

    // 连接是否安全
    public bool $secure;

    // HTTP接受参数
    public string $accept;

    // 客户端代理IP地址
    public string $proxy_ip;

    // HTTP主机名
    public string $host;

    // 用于从何处拉取请求体的流路径
    private string $stream_path = 'php://input';

    // 原始HTTP请求体
    public string $body = '';

    /**
     * 构造函数。
     *
     * @param array<string, mixed> $config 请求配置
     */
    public function __construct(array $config = [])
    {
        // 默认属性
        if (empty($config)) {
            $config = [
                'url' => str_replace('@', '%40', self::getVar('REQUEST_URI', '/')),
                'base' => str_replace(['\\', ' '], ['/', '%20'], \dirname(self::getVar('SCRIPT_NAME'))),
                'method' => self::getMethod(),
                'referrer' => self::getVar('HTTP_REFERER'),
                'ip' => self::getVar('REMOTE_ADDR'),
                'ajax' => 'XMLHttpRequest' === self::getVar('HTTP_X_REQUESTED_WITH'),
                'scheme' => self::getScheme(),
                'user_agent' => self::getVar('HTTP_USER_AGENT'),
                'type' => self::getVar('CONTENT_TYPE'),
                'length' => intval(self::getVar('CONTENT_LENGTH', 0)),
                'query' => new Collection($_GET),
                'data' => new Collection($_POST),
                'cookies' => new Collection($_COOKIE),
                'files' => new Collection($_FILES),
                'secure' => 'https' === self::getScheme(),
                'accept' => self::getVar('HTTP_ACCEPT'),
                'proxy_ip' => self::getProxyIpAddress(),
                'host' => self::getVar('HTTP_HOST'),
            ];
        }

        $this->init($config);
    }

    /**
     * 初始化请求属性。
     *
     * @param array<string, mixed> $properties 请求属性数组
     *
     * @return self
     */
    public function init(array $properties = []): self
    {
        // 设置定义的属性
        foreach ($properties as $name => $value) {
            $this->$name = $value;
        }

        // 重写URL，去掉基础目录
        // 当公共URL和基础目录匹配时适用（例如，在web服务器的子目录下安装）
        if ('/' !== $this->base && '' !== $this->base && 0 === strpos($this->url, $this->base)) {
            $this->url = substr($this->url, \strlen($this->base));
        }

        // 默认URL为根路径
        if (empty($this->url)) {
            $this->url = '/';
        } else {
            // 合并URL查询参数和$_GET
            $_GET = array_merge($_GET, self::parseQuery($this->url));
            $this->query->setData($_GET);
        }

        // 检查JSON输入
        if (0 === strpos($this->type, 'application/json')) {
            $body = $this->getBody();
            if ('' !== $body) {
                $data = json_decode($body, true);
                if (is_array($data)) {
                    $this->data->setData($data);
                }
            }
        }

        return $this;
    }

    /**
     * 获取请求体。
     *
     * @return string 原始HTTP请求体
     */
    public function getBody(): string
    {
        $body = $this->body;

        if ('' !== $body) {
            return $body;
        }

        $method = self::getMethod();

        if ('POST' === $method || 'PUT' === $method || 'DELETE' === $method || 'PATCH' === $method) {
            $body = file_get_contents($this->stream_path);
        }

        $this->body = $body;

        return $body;
    }

    /**
     * 获取请求方法。
     *
     * @return string 请求方法
     */
    public static function getMethod(): string
    {
        $method = self::getVar('REQUEST_METHOD', 'GET');

        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        } elseif (isset($_REQUEST['_method'])) {
            $method = $_REQUEST['_method'];
        }

        return strtoupper($method);
    }

    /**
     * 获取真实的远程IP地址。
     *
     * @return string IP地址
     */
    public static function getProxyIpAddress(): string
    {
        $forwarded = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        $flags = \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;

        foreach ($forwarded as $key) {
            if (\array_key_exists($key, $_SERVER)) {
                sscanf($_SERVER[$key], '%[^,]', $ip);
                if (false !== filter_var($ip, \FILTER_VALIDATE_IP, $flags)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * 从$_SERVER中获取一个变量，如果未提供，则使用$default作为替代值。
     *
     * @param string $var     变量名
     * @param mixed  $default 默认替代值
     *
     * @return mixed 服务器变量值
     */
    public static function getVar(string $var, $default = '')
    {
        return $_SERVER[$var] ?? $default;
    }

    /**
     * 从请求中获取一个头信息。
     *
     * @param string $header 头信息名。可以是大写、小写或混合大小写。
     * @param string $default 如果头信息不存在，则返回的默认值。
     *
     * @return string
     */
    public static function getHeader(string $header, $default = ''): string
    {
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        return self::getVar($header, $default);
    }

    /**
     * 获取所有请求头信息。
     *
     * @return array<string, string|int>
     */
    public static function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                // 将头信息如HTTP_CUSTOM_HEADER转换为Custom-Header的形式
                $key = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$key] = $value;
            }
        }
        return $headers;
    }

    /**
     * 从URL中解析查询参数。
     *
     * @param string $url URL字符串
     *
     * @return array<string, int|string|array<int|string, int|string>>
     */
    public static function parseQuery(string $url): array
    {
        $params = [];

        $args = parse_url($url);
        if (isset($args['query'])) {
            parse_str($args['query'], $params);
        }

        return $params;
    }

    /**
     * 获取URL方案。
     *
     * @return string 'http'|'https'
     */
    public static function getScheme(): string
    {
        if (
            (isset($_SERVER['HTTPS']) && 'on' === strtolower($_SERVER['HTTPS']))
            ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'])
            ||
            (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && 'on' === $_SERVER['HTTP_FRONT_END_HTTPS'])
            ||
            (isset($_SERVER['REQUEST_SCHEME']) && 'https' === $_SERVER['REQUEST_SCHEME'])
        ) {
            return 'https';
        }

        return 'http';
    }
}



```