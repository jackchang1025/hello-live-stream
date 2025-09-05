<?php

declare(strict_types=1);

namespace LiveStream\Shared\Utils;

use LiveStream\Shared\Contracts\ContainerInterface;
use LiveStream\Shared\Exceptions\InfrastructureException;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * 简单的依赖注入容器实现
 * 
 * 支持自动依赖解析和构造函数注入
 */
final class SimpleContainer implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    public function make(string $abstract): object
    {
        // 检查是否有已缓存的单例实例
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 解析实例
        $object = $this->resolve($abstract);

        // 如果是单例，缓存实例
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * 解析服务实例
     *
     * @param string $abstract 抽象标识符
     * @return object
     * @throws ContainerException
     */
    private function resolve(string $abstract): object
    {
        // 如果有绑定，使用绑定的实现
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract];

            if (is_callable($concrete)) {
                return $concrete($this);
            }

            if (is_string($concrete)) {
                return $this->resolveClass($concrete);
            }
        }

        // 尝试直接解析类
        return $this->resolveClass($abstract);
    }

    /**
     * 解析类实例
     *
     * @param string $className 类名
     * @return object
     * @throws ContainerException
     */
    private function resolveClass(string $className): object
    {
        try {
            $reflectionClass = new ReflectionClass($className);

            if (!$reflectionClass->isInstantiable()) {
                throw new ContainerException("Class {$className} is not instantiable");
            }

            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null) {
                return $reflectionClass->newInstance();
            }

            $dependencies = $this->resolveDependencies($constructor->getParameters());

            return $reflectionClass->newInstanceArgs($dependencies);
        } catch (ReflectionException $e) {
            throw new ContainerException("Failed to resolve class {$className}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 解析构造函数依赖
     *
     * @param ReflectionParameter[] $parameters
     * @return array
     * @throws ContainerException
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException("Cannot resolve parameter {$parameter->getName()} without type hint");
                }
                continue;
            }

            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new ContainerException("Cannot resolve builtin parameter {$parameter->getName()}");
                }
                continue;
            }

            $typeName = $type->getName();
            $dependencies[] = $this->make($typeName);
        }

        return $dependencies;
    }
}

/**
 * 容器异常
 */
final class ContainerException extends InfrastructureException
{
    public function getErrorCode(): string
    {
        return 'CONTAINER_RESOLUTION_FAILED';
    }
}