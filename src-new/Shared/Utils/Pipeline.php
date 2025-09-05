<?php

declare(strict_types=1);

namespace LiveStream\Shared\Utils;

use Closure;
use LiveStream\Shared\Contracts\ContainerInterface;
use LiveStream\Shared\Exceptions\InfrastructureException;
use Throwable;

/**
 * 管道处理器 - 修复后的完整实现
 * 
 * 支持洋葱模型的中间件处理，修复了原版的carry()方法问题
 */
final class Pipeline
{
    /**
     * 传递的对象
     */
    protected mixed $passable = null;

    /**
     * 管道数组
     */
    protected array $pipes = [];

    /**
     * 调用的方法名
     */
    protected string $method = 'handle';

    /**
     * 最终回调
     */
    protected ?Closure $finally = null;

    public function __construct(
        private readonly ?ContainerInterface $container = null
    ) {}

    /**
     * 设置传递的对象
     *
     * @param mixed $passable
     * @return $this
     */
    public function send(mixed $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * 设置管道数组
     *
     * @param array|mixed $pipes
     * @return $this
     */
    public function through(array|mixed $pipes): self
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    /**
     * 添加管道
     *
     * @param array|mixed $pipes
     * @return $this
     */
    public function pipe(array|mixed $pipes): self
    {
        array_push($this->pipes, ...(is_array($pipes) ? $pipes : func_get_args()));
        return $this;
    }

    /**
     * 设置调用的方法名
     *
     * @param string $method
     * @return $this
     */
    public function via(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 执行管道并返回结果
     *
     * @param Closure $destination 最终处理器
     * @return mixed
     * @throws PipelineException
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes()),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        try {
            return $pipeline($this->passable);
        } finally {
            if ($this->finally) {
                ($this->finally)($this->passable);
            }
        }
    }

    /**
     * 执行管道并直接返回passable
     *
     * @return mixed
     */
    public function thenReturn(): mixed
    {
        return $this->then(fn($passable) => $passable);
    }

    /**
     * 设置最终回调
     *
     * @param Closure $callback
     * @return $this
     */
    public function finally(Closure $callback): self
    {
        $this->finally = $callback;
        return $this;
    }

    /**
     * 准备目标处理器
     *
     * @param Closure $destination
     * @return Closure
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function ($passable) use ($destination) {
            try {
                return $destination($passable);
            } catch (Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * 获取洋葱层处理器 - 修复后的完整实现
     *
     * @return Closure
     */
    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    if (is_callable($pipe)) {
                        // 如果是可调用的，直接调用
                        return $pipe($passable, $stack);
                    } elseif (is_string($pipe)) {
                        // 如果是字符串，解析并从容器创建实例
                        [$name, $parameters] = $this->parsePipeString($pipe);
                        
                        if ($this->container) {
                            $pipe = $this->container->make($name);
                        } else {
                            // 没有容器时，尝试直接实例化
                            if (!class_exists($name)) {
                                throw new PipelineException("Pipe class {$name} not found and no container available");
                            }
                            $pipe = new $name();
                        }
                        
                        $parameters = array_merge([$passable, $stack], $parameters);
                    } elseif (is_object($pipe)) {
                        // 如果已经是对象，直接使用
                        $parameters = [$passable, $stack];
                    } else {
                        throw new PipelineException('Pipe must be callable, string, or object');
                    }

                    $carry = method_exists($pipe, $this->method)
                        ? $pipe->{$this->method}(...$parameters)
                        : $pipe(...$parameters);

                    return $this->handleCarry($carry);
                } catch (Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    /**
     * 解析管道字符串
     *
     * @param string $pipe
     * @return array
     */
    protected function parsePipeString(string $pipe): array
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, null);

        if ($parameters !== null) {
            $parameters = explode(',', $parameters);
        } else {
            $parameters = [];
        }

        return [$name, $parameters];
    }

    /**
     * 获取管道数组
     *
     * @return array
     */
    protected function pipes(): array
    {
        return $this->pipes;
    }

    /**
     * 处理每个管道的返回值
     *
     * @param mixed $carry
     * @return mixed
     */
    protected function handleCarry(mixed $carry): mixed
    {
        return $carry;
    }

    /**
     * 处理异常
     *
     * @param mixed $passable
     * @param Throwable $e
     * @return mixed
     * @throws Throwable
     */
    protected function handleException(mixed $passable, Throwable $e): mixed
    {
        throw $e;
    }
}

/**
 * 管道异常
 */
final class PipelineException extends InfrastructureException
{
    public function getErrorCode(): string
    {
        return 'PIPELINE_EXECUTION_FAILED';
    }
}