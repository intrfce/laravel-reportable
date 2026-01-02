<?php

namespace Intrfce\LaravelReportable;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @implements ArrayAccess<int, Filter>
 * @implements IteratorAggregate<int, Filter>
 * @implements Arrayable<int, array>
 */
class FilterCollection implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * The default query string parameter name for filters.
     */
    public static string $queryParameter = 'filters';

    /**
     * @param  array<int, Filter>  $filters
     * @param  string|null  $group  The group name for URL serialization
     */
    public function __construct(
        protected array $filters = [],
        protected ?string $group = null
    ) {}

    /**
     * Create a new filter collection.
     *
     * @param  array<int, Filter>  $filters
     * @param  string|null  $group  The group name for URL serialization
     */
    public static function make(array $filters = [], ?string $group = null): self
    {
        return new self($filters, $group);
    }

    /**
     * Create a collection from a query string.
     *
     * @param  string|null  $group  The group name to look for in the query string
     */
    public static function fromQueryString(string $queryString, ?string $group = null): self
    {
        parse_str($queryString, $parsed);

        $parameter = $group ?? self::$queryParameter;

        if (! isset($parsed[$parameter]) || ! is_array($parsed[$parameter])) {
            return new self([], $group);
        }

        return self::fromArray($parsed[$parameter], $group);
    }

    /**
     * Create a collection from an array of filter arrays.
     *
     * @param  array<int, array{column: string, operator: string, value?: mixed}>  $data
     * @param  string|null  $group  The group name for this collection
     */
    public static function fromArray(array $data, ?string $group = null): self
    {
        $filters = [];

        foreach ($data as $filterData) {
            if (isset($filterData['column'], $filterData['operator'])) {
                $filters[] = Filter::fromArray($filterData);
            }
        }

        return new self($filters, $group);
    }

    /**
     * Create a collection from a Laravel request.
     *
     * @param  Request|null  $request  The request to extract filters from
     * @param  string|null  $group  The group name to look for in the request
     */
    public static function fromRequest(?Request $request = null, ?string $group = null): self
    {
        $request = $request ?? request();

        $parameter = $group ?? self::$queryParameter;
        $data = $request->input($parameter, []);

        if (! is_array($data)) {
            return new self([], $group);
        }

        return self::fromArray($data, $group);
    }

    /**
     * Create a collection from JSON.
     *
     * @param  string|null  $group  The group name for this collection
     */
    public static function fromJson(string $json, ?string $group = null): self
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return new self([], $group);
        }

        return self::fromArray($data, $group);
    }

    /**
     * Set the group name for this collection.
     */
    public function withGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Get the group name for this collection.
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * Add a filter to the collection.
     */
    public function add(Filter $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Add multiple filters to the collection.
     *
     * @param  array<int, Filter>  $filters
     */
    public function addMany(array $filters): self
    {
        foreach ($filters as $filter) {
            $this->add($filter);
        }

        return $this;
    }

    /**
     * Get all filters.
     *
     * @return array<int, Filter>
     */
    public function all(): array
    {
        return $this->filters;
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->filters);
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get filters for a specific column.
     *
     * @return array<int, Filter>
     */
    public function forColumn(string $column): array
    {
        return array_values(
            array_filter($this->filters, fn (Filter $filter) => $filter->column === $column)
        );
    }

    /**
     * Check if a filter exists for a specific column.
     */
    public function hasColumn(string $column): bool
    {
        return ! empty($this->forColumn($column));
    }

    /**
     * Convert the collection to an array of filter arrays.
     *
     * @return array<int, array{column: string, operator: string, value: mixed}>
     */
    public function toArray(): array
    {
        return array_map(fn (Filter $filter) => $filter->toArray(), $this->filters);
    }

    /**
     * Convert the collection to a query string.
     */
    public function toQueryString(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return http_build_query([$this->getQueryParameter() => $this->toArray()]);
    }

    /**
     * Convert the collection to an array suitable for URL generation.
     *
     * @return array<string, array<int, array{column: string, operator: string, value: mixed}>>
     */
    public function toQueryArray(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        return [$this->getQueryParameter() => $this->toArray()];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Append filters to a URL.
     */
    public function appendToUrl(string $url): string
    {
        if ($this->isEmpty()) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . $this->toQueryString();
    }

    /**
     * Merge with another collection.
     * The resulting collection uses this collection's group name.
     */
    public function merge(FilterCollection $collection): self
    {
        return new self(array_merge($this->filters, $collection->all()), $this->group);
    }

    /**
     * Get the count of filters.
     */
    public function count(): int
    {
        return count($this->filters);
    }

    /**
     * Get an iterator for the filters.
     *
     * @return Traversable<int, Filter>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->filters);
    }

    /**
     * Check if an offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->filters[$offset]);
    }

    /**
     * Get a filter at an offset.
     */
    public function offsetGet(mixed $offset): ?Filter
    {
        return $this->filters[$offset] ?? null;
    }

    /**
     * Set a filter at an offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->filters[] = $value;
        } else {
            $this->filters[$offset] = $value;
        }
    }

    /**
     * Unset a filter at an offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->filters[$offset]);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<int, array{column: string, operator: string, value: mixed}>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the query parameter name (group or default).
     */
    protected function getQueryParameter(): string
    {
        return $this->group ?? self::$queryParameter;
    }
}
