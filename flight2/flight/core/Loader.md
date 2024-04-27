Loader 类是一个用于管理和加载对象的工具类，其主要功能包括：
注册和管理类：
register() 方法用于注册一个类。它接受四个参数：
$name：注册名称，作为类在 Loader 中的唯一标识。
$class：要注册的类名或闭包，闭包返回一个对象实例。
$params：类初始化参数数组，用于创建类实例时传入构造函数。
$callback：可选的回调函数，当类实例被创建后立即调用，接收新创建的实例作为参数。此回调可用于对实例进行额外的初始化操作。
unregister() 方法用于注销一个已注册的类。传入注册名称 $name，从 Loader 的内部存储中移除对应类信息。
get() 方法返回指定注册名称 $name 对应的类信息（包括类名、参数和回调），如果未注册则返回 null。
reset() 方法清空 Loader 内部的所有类注册信息和已创建的实例。
实例化类：
load() 方法根据注册名称 $name 加载已注册的类。若 $shared 参数为 true，则返回共享实例（即单例模式）。首次加载时创建实例并保存到内部缓存，后续调用直接返回缓存中的实例。若 $shared 为 false，每次都创建新的实例。加载过程中会执行注册时提供的回调函数（如存在）。
getInstance() 方法返回指定注册名称 $name 对应的共享实例，如果没有创建过则返回 null。
newInstance() 方法用于创建一个新的类实例。传入类名或闭包 $class 和初始化参数数组 $params。若 $class 是闭包，则直接调用并返回结果；否则使用 $params 调用类的构造函数创建实例并返回。
自动加载类：
autoload() 方法控制自动加载器的启用/禁用状态以及添加自动加载目录。传入两个参数：
$enabled：布尔值，表示是否启用自动加载。
$dirs：字符串或包含目录路径的可迭代对象，表示要添加到自动加载目录列表的路径。
loadClass() 方法实现类的自动加载逻辑。当尝试访问尚未被定义的类时，PHP 会调用此静态方法。方法接收类名 $class 作为参数，将其转换为文件路径（替换命名空间分隔符为斜线，添加 .php 扩展名），然后遍历自动加载目录列表，查找并引入存在的类文件。
addDirectory() 方法用于向自动加载目录列表中添加一个或多个目录。接受一个字符串或可迭代对象 $dir 作为参数，递归处理其中的每个路径，确保每个路径只添加一次。
综上所述，Loader 类提供了一套完整的机制来注册、管理类及其实例，支持单例模式、自定义初始化参数和回调，以及类的自动加载功能，大大简化了对象的创建与使用过程，增强了代码的组织性和可维护性

```php

<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use Exception;

/**
 * Loader类负责加载对象。它维护一个可重用类实例的列表，并且能够使用自定义初始化参数生成新的类实例。它也执行类自动加载。
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Loader
{
    /**
     * 已注册的类。
     *
     * @var array<string, array{class-string|Closure(): object, array<int, mixed>, ?callable}> $classes
     */
    protected array $classes = [];

    /**
     * 类实例。
     *
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * 自动加载目录。
     *
     * @var array<int, string>
     */
    protected static array $dirs = [];

    /**
     * 注册一个类。
     *
     * @param string $name 注册名称
     * @param class-string<T>|Closure(): T $class 类名或用于实例化类的函数
     * @param array<int, mixed> $params 类初始化参数
     * @param ?Closure(T $instance): void $callback 对象实例化后调用的函数
     *
     * @template T of object
     */
    public function register(string $name, $class, array $params = [], ?callable $callback = null): void
    {
        unset($this->instances[$name]);

        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * 注销一个类。
     *
     * @param string $name 注册名称
     */
    public function unregister(string $name): void
    {
        unset($this->classes[$name]);
    }

    /**
     * 加载一个已注册的类。
     *
     * @param string $name 方法名称
     * @param bool $shared 共享实例
     *
     * @throws Exception
     *
     * @return ?object 类实例
     */
    public function load(string $name, bool $shared = true): ?object
    {
        $obj = null;

        if (isset($this->classes[$name])) {
            [0 => $class, 1 => $params, 2 => $callback] = $this->classes[$name];

            $exists = isset($this->instances[$name]);

            if ($shared) {
                $obj = ($exists) ?
                    $this->getInstance($name) :
                    $this->newInstance($class, $params);

                if (!$exists) {
                    $this->instances[$name] = $obj;
                }
            } else {
                $obj = $this->newInstance($class, $params);
            }

            if ($callback && (!$shared || !$exists)) {
                $ref = [&$obj];
                \call_user_func_array($callback, $ref);
            }
        }

        return $obj;
    }

    /**
     * 获取一个类的单个实例。
     *
     * @param string $name 实例名称
     *
     * @return ?object 类实例
     */
    public function getInstance(string $name): ?object
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * 获取一个类的新实例。
     *
     * @param class-string<T>|Closure(): class-string<T> $class 类名或用于实例化类的回调函数
     * @param array<int, string> $params 类初始化参数
     *
     * @template T of object
     *
     * @throws Exception
     *
     * @return T 类实例
     */
    public function newInstance($class, array $params = [])
    {
        if (\is_callable($class)) {
            return \call_user_func_array($class, $params);
        }

        return new $class(...$params);
    }

    /**
     * 获取一个已注册的可调用对象。
     *
     * @param string $name 注册名称
     *
     * @return mixed 类信息或未注册时的null
     */
    public function get(string $name)
    {
        return $this->classes[$name] ?? null;
    }

    /**
     * 重置对象到初始状态。
     */
    public function reset(): void
    {
        $this->classes = [];
        $this->instances = [];
    }

    // 自动加载函数

    /**
     * 启用/禁用自动加载器。
     *
     * @param bool $enabled 启用/禁用自动加载
     * @param string|iterable<int, string> $dirs 自动加载目录
     */
    public static function autoload(bool $enabled = true, $dirs = []): void
    {
        if ($enabled) {
            spl_autoload_register([__CLASS__, 'loadClass']);
        } else {
            spl_autoload_unregister([__CLASS__, 'loadClass']); // @codeCoverageIgnore
        }

        if (!empty($dirs)) {
            self::addDirectory($dirs);
        }
    }

    /**
     * 自动加载类。
     *
     * 类名中不允许有下划线。
     *
     * @param string $class 类名
     */
    public static function loadClass(string $class): void
    {
        $classFile = str_replace(['\\', '_'], '/', $class) . '.php';

        foreach (self::$dirs as $dir) {
            $filePath = "$dir/$classFile";

            if (file_exists($filePath)) {
                require_once $filePath;

                return;
            }
        }
    }

    /**
     * 为自动加载添加一个目录。
     *
     * @param string|iterable<int, string> $dir 目录路径
     */
    public static function addDirectory($dir): void
    {
        if (\is_array($dir) || \is_object($dir)) {
            foreach ($dir as $value) {
                self::addDirectory($value);
            }
        } elseif (\is_string($dir)) {
            if (!\in_array($dir, self::$dirs, true)) {
                self::$dirs[] = $dir;
            }
        }
    }
}



```