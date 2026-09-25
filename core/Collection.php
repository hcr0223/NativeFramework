<?php

namespace Core;

use ArrayIterator;
use IteratorAggregate;
use Countable;
use ArrayAccess;

class Collection implements IteratorAggregate, Countable, ArrayAccess {
    protected array $items = [];

    public function __construct(array $items = []) {
        $this->items = $items;
    }

    public function all(): array {
        return $this->items;
    }

    public function first(): mixed {
        return $this->items[0] ?? null;
    }

    public function count(): int {
        return count($this->items);
    }
    public function map(callable $callback): self {
        return new self(array_map($callback, $this->items));
    }
    /**
     * @inheritDoc
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function toArray(): array {
        return array_map(function($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return method_exists($item, 'all') ? $item->all() : $item;
        }, $this->items);
    }

    public function offsetExists(mixed $offset): bool {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void{
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->items[$offset]);
    }

    public function pluck(string $valueKey, ?string $indexKey = null): array {
        $getNestedValue = function (array $item, string $path) {
            foreach (explode('.', $path) as $segment) {
                if (is_array($item) && array_key_exists($segment, $item)) {
                    $item = $item[$segment];
                } else {
                    return null;
                }
            }
            return $item;
        };

        $result = [];

        foreach ($this->items as $item) {
            $itemArray = is_object($item) ? (array) $item->jsonSerialize() : $item;
            $value = $getNestedValue($itemArray, $valueKey);

            if ($indexKey !== null) {
                $key = $getNestedValue($itemArray, $indexKey);

                if ($key !== null) {
                    $result[$key] = $value;
                }
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }
}