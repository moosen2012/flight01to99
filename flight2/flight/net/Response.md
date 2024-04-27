详细和具体地解释Response类
1. 类定义与属性
```php
class Response
```
Response类代表一个HTTP响应对象，包含HTTP响应的三个核心组成部分：状态码、响应头和响应体。
属性说明：
public bool $content_length = true;
表示是否应发送Content-Length头部。默认值为true，即在发送响应时包含Content-Length头部，其值为响应体的字节数。
public static array $codes = [...];
静态属性，存储了一个关联数组，键是HTTP状态码（整数），值是对应状态码的文字描述。例如，200 => 'OK'表示状态码200对应的描述为"OK"。此数组涵盖了常见的HTTP状态码及其描述。
protected int $status = 200;
HTTP状态码，默认值为200，表示请求成功。
protected array $headers = [];
存储HTTP响应头的关联数组。键是头字段名，值是对应的头字段值。支持多值头字段，通过数组形式存储。
protected string $body = '';
响应体，初始化为空字符串。
protected bool $sent = false;
标记响应是否已经发送，初始值为false。
2. 方法详解
   a. 设置与获取状态码：
```php
public function status(?int $code = null): int|self
```
如果未传入参数$code，则返回当前HTTP状态码。
若传入参数$code，则：
检查给定的状态码是否存在于$codes数组中。若存在，将当前响应的状态码设为指定值。
若不存在，抛出Exception，提示“Invalid status code”。
b. 添加响应头：
```php
public function header($name, ?string $value = null): self
```
接受一个参数$name和一个可选参数$value。
如果$name是一个关联数组，遍历数组并将其键值对添加到响应头数组$headers中。
如果$name是一个字符串且$value非空，将$name作为键，$value作为值添加到响应头数组$headers中。
返回当前Response对象，以支持链式调用。
c. 获取响应头：
```php
public function headers(): array
```
返回当前响应头数组$headers。
d. 向响应体写入内容：
```php
public function write(string $str): self
```
接收一个字符串参数$str，将其追加到响应体$body中。返回当前Response对象，支持链式调用。
e. 清除响应：
```php
public function clear(): self
```
将响应状态码重置为200，清空响应头数组$headers和响应体$body。返回当前Response对象。
f. 设置缓存控制头：
```php
public function cache($expires): self
```
根据传入的$expires参数，设置响应的缓存控制相关头字段：
若$expires为false，设置一系列禁用缓存的头字段（如Expires、Cache-Control、Pragma）。
若$expires为整数或可被strtotime()解析的字符串（表示过期时间），设置相应的Expires和Cache-Control头字段以启用缓存，并可能移除之前设置的Pragma: no-cache头。
g. 发送响应头：
```php
public function sendHeaders(): self
```
根据当前环境（CGI模式或非CGI模式），发送HTTP状态码对应的响应行。
遍历响应头数组$headers，发送所有头字段。
如果$content_length为true且响应体有内容，计算并发送Content-Length头字段。
返回当前Response对象。
h. 实际发送头部（主要用于测试）：
```php
public function setRealHeader(string $header_string, bool $replace = true, int $response_code = 0): self
```
使用PHP内置的header()函数发送指定的头部字符串$header_string。此方法主要为了方便单元测试时进行方法替换和模拟。
i. 计算响应体长度：
```php
public function getContentLength(): int
```
根据当前环境（是否加载了mbstring扩展），使用相应的方法计算响应体$body的长度（字节数），并返回结果。
j. 获取响应体：
```php
public function getBody(): string
```
直接返回响应体$body。
k. 检查响应是否已发送：
```php
public function sent(): bool
```
返回响应是否已发送的标记$sent。
l. 发送完整HTTP响应：
```php
public function send(): void
```
清除当前输出缓冲区（如有）。
如果响应头尚未发送，调用sendHeaders()方法发送。
输出响应体$body。
将响应已发送标记$sent设为true。
总之，Response类提供了一系列方法来创建、配置、发送HTTP响应，涵盖了状态码、响应头、响应体以及缓存控制等功能，便于在Web应用中统一管理和操作HTTP响应。

```php

<?php

declare(strict_types=1);

namespace flight\net;

use Exception;

/**
 * Response类代表一个HTTP响应。该对象包含响应头，HTTP状态码和响应正文。
 */
class Response
{
    /**
     * Content-Length头部。
     */
    public bool $content_length = true;

    /**
     * HTTP状态码及其对应文本。
     *
     * @var array<int, ?string> $codes
     */
    public static array $codes = [
        // 状态码和对应信息
    ];

    /**
     * HTTP响应状态。
     */
    protected int $status = 200;

    /**
     * HTTP响应头。
     *
     * @var array<string,int|string|array<int,string>> $headers
     */
    protected array $headers = [];

    /**
     * HTTP响应正文。
     */
    protected string $body = '';

    /**
     * HTTP响应是否已发送。
     */
    protected bool $sent = false;

    /**
     * 设置HTTP响应的状态码。
     *
     * @param ?int $code HTTP状态码。
     *
     * @throws Exception 如果状态码无效。
     *
     * @return int|$this 自身引用。
     */
    public function status(?int $code = null)
    {
        // 设置或获取HTTP状态码
    }

    /**
     * 添加一个响应头。
     *
     * @param array<string, int|string>|string $name  头部名称或名称和值的数组
     * @param ?string  $value 头部值
     *
     * @return $this 自身引用。
     */
    public function header($name, ?string $value = null): self
    {
        // 添加响应头
    }

    /**
     * 从响应中返回头部信息。
     *
     * @return array<string, int|string|array<int, string>>
     */
    public function headers(): array
    {
        // 返回响应头信息
    }

    /**
     * 向响应正文写入内容。
     *
     * @param string $str 响应内容。
     *
     * @return $this 自身引用。
     */
    public function write(string $str): self
    {
        // 写入响应正文
    }

    /**
     * 清除响应信息。
     *
     * @return $this 自身引用。
     */
    public function clear(): self
    {
        // 清除响应状态、头和正文
    }

    /**
     * 为响应设置缓存头部。
     *
     * @param int|string|false $expires 过期时间，可以是时间戳、strtotime() 可解析字符串或关闭缓存。
     *
     * @return $this 自身引用。
     */
    public function cache($expires): self
    {
        // 设置缓存头部
    }

    /**
     * 发送HTTP头部。
     *
     * @return $this 自身引用。
     */
    public function sendHeaders(): self
    {
        // 发送HTTP头部
    }

    /**
     * 获取响应正文。
     *
     * @return string
     */
    public function getBody(): string
    {
        // 返回响应正文
    }

    /**
     * 获取响应是否已发送。
     *
     * @return bool
     */
    public function sent(): bool
    {
        // 返回响应是否已发送
    }

    /**
     * 发送HTTP响应。
     */
    public function send(): void
    {
        // 发送HTTP响应
    }
}


```