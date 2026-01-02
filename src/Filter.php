<?php

namespace Intrfce\LaravelReportable;

use Intrfce\LaravelReportable\Enums\FilterComparator;

class Filter
{
    public function __construct(
        public readonly string $column,
        public readonly FilterComparator $comparator,
        public readonly mixed $value = null,
    ) {}

    public static function make(string $column, FilterComparator $comparator, mixed $value = null): self
    {
        return new self($column, $comparator, $value);
    }

    public static function equals(string $column, mixed $value): self
    {
        return new self($column, FilterComparator::Equals, $value);
    }

    public static function notEquals(string $column, mixed $value): self
    {
        return new self($column, FilterComparator::NotEquals, $value);
    }

    public static function greaterThan(string $column, mixed $value): self
    {
        return new self($column, FilterComparator::GreaterThan, $value);
    }

    public static function greaterThanOrEqual(string $column, mixed $value): self
    {
        return new self($column, FilterComparator::GreaterThanOrEqual, $value);
    }

    public static function lessThan(string $column, mixed $value): self
    {
        return new self($column, FilterComparator::LessThan, $value);
    }

    public static function lessThanOrEqual(string $column, mixed $value): self
    {
        return new self($column, FilterComparator::LessThanOrEqual, $value);
    }

    public static function contains(string $column, string $value): self
    {
        return new self($column, FilterComparator::Contains, $value);
    }

    public static function doesNotContain(string $column, string $value): self
    {
        return new self($column, FilterComparator::DoesNotContain, $value);
    }

    public static function startsWith(string $column, string $value): self
    {
        return new self($column, FilterComparator::StartsWith, $value);
    }

    public static function endsWith(string $column, string $value): self
    {
        return new self($column, FilterComparator::EndsWith, $value);
    }

    public static function in(string $column, array $values): self
    {
        return new self($column, FilterComparator::In, $values);
    }

    public static function notIn(string $column, array $values): self
    {
        return new self($column, FilterComparator::NotIn, $values);
    }

    public static function between(string $column, mixed $min, mixed $max): self
    {
        return new self($column, FilterComparator::Between, [$min, $max]);
    }

    public static function isNull(string $column): self
    {
        return new self($column, FilterComparator::IsNull);
    }

    public static function isNotNull(string $column): self
    {
        return new self($column, FilterComparator::IsNotNull);
    }

    /**
     * Convert the filter to an array representation.
     *
     * @return array{column: string, operator: string, value: mixed}
     */
    public function toArray(): array
    {
        return [
            'column' => $this->column,
            'operator' => $this->comparator->value,
            'value' => $this->value,
        ];
    }

    /**
     * Create a filter from an array representation.
     *
     * @param  array{column: string, operator: string, value?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $comparator = FilterComparator::from($data['operator']);

        return new self(
            column: $data['column'],
            comparator: $comparator,
            value: $data['value'] ?? null,
        );
    }
}
