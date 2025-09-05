<?php
namespace LiveStream\Config;    

use LiveStream\Utils\ArrayHelpers;
use LiveStream\Traits\Makeable;

class Config
{

    use Makeable;

    /**
     * Constructor
     *
     * @param array<string, mixed> $data
     */
    public function __construct(protected array $data = [])
    {

    }

    /**
     * Retrieve all the items.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Retrieve a single item.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return ArrayHelpers::get($this->data, $key, $default);
    }

    /**
     * Overwrite the entire repository.
     *
     * @param array<string, mixed> $data
     * @return $this
     */
    public function set(string|array $key, mixed $value = null): static
    {
        $keys = is_array($key) ? $key : [$key => $value];

        foreach ($keys as $key => $value) {
            ArrayHelpers::set($this->data, $key, $value);
        }

        return $this;
    }

    /**
     * Merge in other arrays.
     *
     * @param array<string, mixed> ...$arrays
     * @return $this
     */
    public function merge(array ...$arrays): static
    {
        $this->data = array_merge($this->data, ...$arrays);

        return $this;
    }

    /**
     * Remove an item from the store.
     *
     * @return $this
     */
    public function remove(string $key): static
    {
        unset($this->data[$key]);

        return $this;
    }

    /**
     * Determine if the store is empty
     *
     * @phpstan-assert-if-false non-empty-array<array-key, mixed> $this->data
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Determine if the store is not empty
     *
     * @phpstan-assert-if-true non-empty-array<array-key, mixed> $this->data
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }
}