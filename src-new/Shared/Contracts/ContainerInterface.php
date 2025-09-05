<?php

declare(strict_types=1);

namespace LiveStream\Shared\Contracts;

/**
 * 依赖注入容器接口
 * 
 * 提供基本的依赖注入和服务定位功能
 */
interface ContainerInterface
{
    /**
     * 绑定抽象到具体实现
     *
     * @param string $abstract 抽象标识符（接口或类名）
     * @param callable|string $concrete 具体实现（闭包或类名）
     */
    public function bind(string $abstract, callable|string $concrete): void;

    /**
     * 绑定单例
     *
     * @param string $abstract 抽象标识符
     * @param callable|string $concrete 具体实现
     */
    public function singleton(string $abstract, callable|string $concrete): void;

    /**
     * 解析服务实例
     *
     * @param string $abstract 抽象标识符
     * @return object 解析的实例
     * @throws \LiveStream\Shared\Exceptions\ContainerException
     */
    public function make(string $abstract): object;

    /**
     * 注册已存在的实例
     *
     * @param string $abstract 抽象标识符
     * @param object $instance 实例对象
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * 检查是否已绑定
     *
     * @param string $abstract 抽象标识符
     * @return bool
     */
    public function bound(string $abstract): bool;
}