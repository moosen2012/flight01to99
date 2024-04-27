Dispatcher 类作为应用程序中事件管理的核心组件，负责触发并处理事件。事件实质上是类方法或函数的别名，通过该类，你可以将其他函数（称为“过滤器”）与特定事件关联，这些过滤器能够在事件主回调执行前后修改输入参数或输出结果。
以下是该类的详细功能分解：
常量
FILTER_BEFORE：表示“前置”过滤器类型，事件主回调执行前应用。
FILTER_AFTER：表示“后置”过滤器类型，事件主回调执行后应用。
FILTER_TYPES：包含FILTER_BEFORE和FILTER_AFTER的数组，用于验证过滤器类型的合法性。
属性
$events：一个关联数组，存储了事件名到其对应回调函数（匿名函数或类方法引用）的映射。当触发相应事件时，会执行这些回调函数。
$filters：一个关联数组，为每个事件存储过滤器。每个事件条目含有两个子数组（分别对应FILTER_BEFORE和FILTER_AFTER），内含过滤器回调数组。这些回调可以接收事件参数（前置过滤器）或事件输出（后置过滤器），并对其进行修改。
方法
事件调度与管理
run($name, $params)：触发事件的主要方法，执行流程如下：
运行与事件关联的所有“前置”过滤器，并传递输入参数。
执行事件的主回调函数，使用可能已被过滤器修改的参数。
运行所有“后置”过滤器，它们可以进一步修改事件输出。
返回最终的、可能经过过滤的事件输出。
事件设置与获取
set($name, $callback)：为事件分配回调函数。
get($name)：获取指定事件的回调函数。
has($name)：检查某个事件是否已设置回调。
clear($name = null)：清除指定事件及其过滤器，或不清除特定事件时清空所有事件及过滤器。
过滤器添加与管理
hook($name, $type, $callback)：向事件添加过滤器回调，$type需为FILTER_BEFORE或FILTER_AFTER，并将过滤器加入到$filters的相应分类中。
辅助方法
filter($filters, &$params, &$output)：执行过滤器链，遍历过滤器数组并调用每个过滤器，允许其修改参数和输出，若某过滤器返回false则停止后续过滤器的执行。
execute($callback, &$params)：执行回调函数，支持多种回调定义形式（函数名、匿名函数或类方法引用），根据回调类型分别调用callFunction()或invokeMethod()执行。
callFunction($func, &$params)：直接调用独立函数（以可调用形式传递）并传入参数。
invokeMethod($func, &$params)：调用类方法（以数组形式传递类实例/类名和方法名）并传入参数。
reset()：重置Dispatcher对象至初始状态，清空所有事件和过滤器配置。
综上所述，Dispatcher类让你能够定义并管理应用中的事件，通过附加过滤器来灵活控制事件行为，同时支持带参数的事件调用，并在过程中应用这些过滤逻辑。这种设计促进了代码的松耦合和模块化，不同代码部分可通过事件系统相互作用，而无需直接依赖。

```php

<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use TypeError;

/**
 * 事件分发器类负责分发事件。事件仅仅是类方法或函数的别名。分发器允许你将其他函数绑定到
 * 一个事件上，以修改输入参数和/或输出。
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Dispatcher
{
    public const FILTER_BEFORE = 'before'; // 执行事件前的过滤器
    public const FILTER_AFTER = 'after'; // 执行事件后的过滤器
    private const FILTER_TYPES = [self::FILTER_BEFORE, self::FILTER_AFTER]; // 过滤器类型

    /** @var array<string, Closure(): (void|mixed)> 映射的事件。 */
    protected array $events = [];

    /**
     * 方法过滤器。
     *
     * @var array<string, array<'before'|'after', array<int, Closure(array<int, mixed> &$params, mixed &$output): (void|false)>>>
     */
    protected array $filters = [];

    /**
     * 分发一个事件。
     *
     * @param string $name 事件名称
     * @param array<int, mixed> $params 回调参数。
     *
     * @return mixed 回调的输出
     * @throws Exception 如果事件名称不存在或事件抛出异常
     */
    public function run(string $name, array $params = [])
    {
        $this->runPreFilters($name, $params);
        $output = $this->runEvent($name, $params);

        return $this->runPostFilters($name, $output);
    }

    /**
     * 执行预过滤器。
     *
     * @param array<int, mixed> &$params
     *
     * @return $this
     * @throws Exception
     */
    protected function runPreFilters(string $eventName, array &$params): self
    {
        $thereAreBeforeFilters = !empty($this->filters[$eventName][self::FILTER_BEFORE]);

        if ($thereAreBeforeFilters) {
            $this->filter($this->filters[$eventName][self::FILTER_BEFORE], $params, $output);
        }

        return $this;
    }

    /**
     * 执行事件。
     *
     * @param array<int, mixed> &$params
     *
     * @return void|mixed
     * @throws Exception
     */
    protected function runEvent(string $eventName, array &$params)
    {
        $requestedMethod = $this->get($eventName);

        if ($requestedMethod === null) {
            throw new Exception("Event '$eventName' isn't found.");
        }

        return $requestedMethod(...$params);
    }

    /**
     * 执行后过滤器。
     *
     * @param mixed &$output
     *
     * @return mixed
     * @throws Exception
     */
    protected function runPostFilters(string $eventName, &$output)
    {
        static $params = [];

        $thereAreAfterFilters = !empty($this->filters[$eventName][self::FILTER_AFTER]);

        if ($thereAreAfterFilters) {
            $this->filter($this->filters[$eventName][self::FILTER_AFTER], $params, $output);
        }

        return $output;
    }

    /**
     * 将回调函数绑定到一个事件。
     *
     * @param string $name 事件名称
     * @param Closure(): (void|mixed) $callback 回调函数
     *
     * @return $this
     */
    public function set(string $name, callable $callback): self
    {
        $this->events[$name] = $callback;

        return $this;
    }

    /**
     * 获取一个事件绑定的回调函数。
     *
     * @param string $name 事件名称
     *
     * @return null|(Closure(): (void|mixed)) 回调函数
     */
    public function get(string $name): ?callable
    {
        return $this->events[$name] ?? null;
    }

    /**
     * 检查一个事件是否已被绑定。
     *
     * @param string $name 事件名称
     *
     * @return bool 事件状态
     */
    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * 清除一个事件的绑定。如果不指定名称，则清除所有事件的绑定。
     *
     * @param ?string $name 事件名称
     */
    public function clear(?string $name = null): void
    {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);

            return;
        }

        $this->events = [];
        $this->filters = [];
    }

    /**
     * 将回调函数绑定到一个事件的预处理或后处理。
     *
     * @param string $name 事件名称
     * @param 'before'|'after' $type 过滤器类型
     * @param Closure(array<int, mixed> &$params, string &$output): (void|false) $callback
     *
     * @return $this
     */
    public function hook(string $name, string $type, callable $callback): self
    {
        if (!in_array($type, self::FILTER_TYPES, true)) {
            $noticeMessage = "Invalid filter type '$type', use " . join('|', self::FILTER_TYPES);

            trigger_error($noticeMessage, E_USER_NOTICE);
        }

        $this->filters[$name][$type][] = $callback;

        return $this;
    }

    /**
     * 执行一系列方法过滤器。
     *
     * @param array<int, Closure(array<int, mixed> &$params, mixed &$output): (void|false)> $filters 过滤器数组
     * @param array<int, mixed> $params 方法参数
     * @param mixed $output 方法输出
     *
     * @throws Exception 如果事件抛出异常或 `$filters` 包含无效过滤器。
     */
    public static function filter(array $filters, array &$params, &$output): void
    {
        foreach ($filters as $key => $callback) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("Invalid callable \$filters[$key].");
            }

            $continue = $callback($params, $output);

            if ($continue === false) {
                break;
            }
        }
    }

    /**
     * 执行回调函数。
     *
     * @param callable-string|(Closure(): mixed)|array{class-string|object, string} $callback 回调函数
     * @param array<int, mixed> $params 函数参数
     *
     * @return mixed 函数结果
     * @throws Exception 如果 `$callback` 也抛出异常。
     */
    public static function execute($callback, array &$params = [])
    {
        $isInvalidFunctionName = (
            is_string($callback)
            && !function_exists($callback)
        );

        if ($isInvalidFunctionName) {
            throw new InvalidArgumentException('Invalid callback specified.');
        }

        if (is_array($callback)) {
            return self::invokeMethod($callback, $params);
        }

        return self::callFunction($callback, $params);
    }

    /**
     * 调用一个函数。
     *
     * @param callable $func 函数名
     * @param array<int, mixed> &$params 函数参数
     *
     * @return mixed 函数结果
     */
    public static function callFunction(callable $func, array &$params = [])
    {
        return call_user_func_array($func, $params);
    }

    /**
     * 调用一个方法。
     *
     * @param array{class-string|object, string} $func 类方法
     * @param array<int, mixed> &$params 类方法参数
     *
     * @return mixed 函数结果
     * @throws TypeError 如果类名不存在。
     */
    public static function invokeMethod(array $func, array &$params = [])
    {
        [$class, $method] = $func;

        if (is_string($class) && class_exists($class)) {
            $constructor = (new ReflectionClass($class))->getConstructor();
            $constructorParamsNumber = 0;

            if ($constructor !== null) {
                $constructorParamsNumber = count($constructor->getParameters());
            }

            if ($constructorParamsNumber > 0) {
                $exceptionMessage = "Method '$class::$method' cannot be called statically. ";
                $exceptionMessage .= sprintf(
                    "$class::__construct require $constructorParamsNumber parameter%s",
                    $constructorParamsNumber > 1 ? 's' : ''
                );

                throw new InvalidArgumentException($exceptionMessage, E_ERROR);
            }

            $class = new $class();
        }

        return call_user_func_array([$class, $method], $params);
    }

    /**
     * 将对象重置为初始状态。
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->events = [];
        $this->filters = [];

        return $this;
    }
}



```

