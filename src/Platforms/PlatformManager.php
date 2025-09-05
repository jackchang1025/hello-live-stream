<?php

declare(strict_types=1);

namespace LiveStream\Platforms;

use Closure;
use LiveStream\Contracts\PlatformInterface;
use LiveStream\PlatformFactory;

class PlatformManager
{
    public function __construct(private PlatformFactory $platformFactory) {}

    /**
     * The registered custom driver creators.
     */
    protected array $customCreators = [];

    /**
     * The array of created drivers, keyed by URL.
     */
    protected array $drivers = [];

    /**
     * Get a driver instance for the given URL.
     */
    public function driver(string $url): PlatformInterface
    {
        if (!isset($this->drivers[$url])) {
            $this->drivers[$url] = $this->createDriver($url);
        }

        return $this->drivers[$url];
    }

    /**
     * Create a new driver instance for the given URL.
     */
    protected function createDriver(string $url): PlatformInterface
    {
        return $this->platformFactory->createPlatform($url);
    }

    /**
     * Register a custom driver creator.
     */
    public function extend(string $name, Closure $callback): self
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    /**
     * Get all of the created drivers.
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Forget all of the resolved driver instances.
     */
    public function forgetDrivers(): self
    {
        $this->drivers = [];

        return $this;
    }
}
