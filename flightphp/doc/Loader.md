
```php
<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use Exception;

/**
 * The Loader class is responsible for loading objects. It maintains
 * a list of reusable class instances and can generate a new class
 * instances with custom initialization parameters. It also performs
 * class autoloading.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Loader
{
    /**
     * Registered classes.
     *
     * @var array<string, array{class-string|Closure(): object, array<int, mixed>, ?callable}> $classes
     */
    protected array $classes = [];

    /**
     * Class instances.
     *
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * Autoload directories.
     *
     * @var array<int, string>
     */
    protected static array $dirs = [];

    /**
     * Registers a class.
     *
     * @param string          $name     Registry name
     * @param class-string<T>|Closure(): T $class    Class name or function to instantiate class
     * @param array<int, mixed>           $params   Class initialization parameters
     * @param ?callable(T $instance): void   $callback $callback Function to call after object instantiation
     *
     * @template T of object
     *
     * @return void
     */
    public function register(string $name, $class, array $params = [], ?callable $callback = null): void
    {
        unset($this->instances[$name]);

        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregisters a class.
     *
     * @param string $name Registry name
     */
    public function unregister(string $name): void
    {
        unset($this->classes[$name]);
    }

    /**
     * Loads a registered class.
     *
     * @param string $name   Method name
     * @param bool   $shared Shared instance
     *
     * @throws Exception
     *
     * @return ?object Class instance
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
     * Gets a single instance of a class.
     *
     * @param string $name Instance name
     *
     * @return ?object Class instance
     */
    public function getInstance(string $name): ?object
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * Gets a new instance of a class.
     *
     * @param class-string<T>|Closure(): class-string<T> $class  Class name or callback function to instantiate class
     * @param array<int, string>           $params Class initialization parameters
     *
     * @template T of object
     *
     * @throws Exception
     *
     * @return T Class instance
     */
    public function newInstance($class, array $params = [])
    {
        if (\is_callable($class)) {
            return \call_user_func_array($class, $params);
        }

        return new $class(...$params);
    }

    /**
     * Gets a registered callable
     *
     * @param string $name Registry name
     *
     * @return mixed Class information or null if not registered
     */
    public function get(string $name)
    {
        return $this->classes[$name] ?? null;
    }

    /**
     * Resets the object to the initial state.
     */
    public function reset(): void
    {
        $this->classes = [];
        $this->instances = [];
    }

    // Autoloading Functions

    /**
     * Starts/stops autoloader.
     *
     * @param bool  $enabled Enable/disable autoloading
     * @param string|iterable<int, string> $dirs    Autoload directories
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
     * Autoloads classes.
     *
     * Classes are not allowed to have underscores in their names.
     *
     * @param string $class Class name
     */
    public static function loadClass(string $class): void
    {
        $class_file = str_replace(['\\', '_'], '/', $class) . '.php';

        foreach (self::$dirs as $dir) {
            $file = $dir . '/' . $class_file;
            if (file_exists($file)) {
                require $file;

                return;
            }
        }
    }

    /**
     * Adds a directory for autoloading classes.
     *
     * @param string|iterable<int, string> $dir Directory path
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

让我们逐个介绍Loader类的关键组件和方法：

属性:
$classes: 一个关联数组，存储注册的类以及它们的初始化参数和可选的回调函数。
$instances: 一个关联数组，维护类的实例。
$dirs: 一个数组，保存用于自动加载类的目录。
方法:
register(string $name, $class, array $params = [], ?callable $callback = null): void: 注册一个类，包括类名或回调函数、初始化参数以及可选的实例化后回调函数。

unregister(string $name): void: 注销一个通过名称识别的类。

load(string $name, bool $shared = true): ?object: 根据名称加载已注册的类实例。根据$shared参数，它可以生成一个新实例或返回一个现有的共享实例。

getInstance(string $name): ?object: 根据名称检索类的单个实例。

newInstance($class, array $params = []): 实例化一个新的类实例。它可以接受类名或回调函数作为输入。

get(string $name): 检索已注册类的信息。

reset(): void: 通过清除注册的类和实例将Loader对象重置为初始状态。

autoload(bool $enabled = true, $dirs = []): void: 启动或停止自动加载器。它还允许指定自动加载目录。

loadClass(string $class): void: 通过将类名转换为文件路径并引入相应的文件，执行类的自动加载。

addDirectory($dir): void: 添加一个或多个目录以用于自动加载类。

Loader类在Flight PHP框架中促进了高效的类加载和管理，增强了模块化和可扩展性。