<?php

namespace Intrfce\LaravelReportable;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intrfce\LaravelReportable\Enums\FilterComparator;

abstract class Reportable implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The filters to apply to the query.
     *
     * @var array<int, Filter>
     */
    protected array $filters = [];

    /**
     * Define the query for this report.
     */
    abstract public function query(): QueryBuilder|EloquentBuilder;

    /**
     * Set the filters to apply to the query.
     *
     * @param  array<int, Filter>  $filters
     */
    public function withFilters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Add a single filter to the query.
     */
    public function addFilter(Filter $filter): static
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Get the filters for this report.
     *
     * @return array<int, Filter>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Apply filters to the query.
     * Override this method to customize filter application.
     */
    protected function applyFilters(QueryBuilder|EloquentBuilder $query): QueryBuilder|EloquentBuilder
    {
        foreach ($this->filters as $filter) {
            $query = $this->applyFilter($query, $filter);
        }

        return $query;
    }

    /**
     * Apply a single filter to the query.
     */
    protected function applyFilter(QueryBuilder|EloquentBuilder $query, Filter $filter): QueryBuilder|EloquentBuilder
    {
        return match ($filter->comparator) {
            FilterComparator::Equals => $query->where($filter->column, '=', $filter->value),
            FilterComparator::NotEquals => $query->where($filter->column, '!=', $filter->value),
            FilterComparator::GreaterThan => $query->where($filter->column, '>', $filter->value),
            FilterComparator::GreaterThanOrEqual => $query->where($filter->column, '>=', $filter->value),
            FilterComparator::LessThan => $query->where($filter->column, '<', $filter->value),
            FilterComparator::LessThanOrEqual => $query->where($filter->column, '<=', $filter->value),
            FilterComparator::Contains => $query->where($filter->column, 'LIKE', '%' . $filter->value . '%'),
            FilterComparator::DoesNotContain => $query->where($filter->column, 'NOT LIKE', '%' . $filter->value . '%'),
            FilterComparator::StartsWith => $query->where($filter->column, 'LIKE', $filter->value . '%'),
            FilterComparator::EndsWith => $query->where($filter->column, 'LIKE', '%' . $filter->value),
            FilterComparator::In => $query->whereIn($filter->column, $filter->value),
            FilterComparator::NotIn => $query->whereNotIn($filter->column, $filter->value),
            FilterComparator::Between => $query->whereBetween($filter->column, $filter->value),
            FilterComparator::IsNull => $query->whereNull($filter->column),
            FilterComparator::IsNotNull => $query->whereNotNull($filter->column),
        };
    }

    /**
     * Build the final query with filters applied.
     */
    protected function buildQuery(): QueryBuilder|EloquentBuilder
    {
        return $this->applyFilters($this->query());
    }

    /**
     * Execute the report job.
     */
    public function handle(): void
    {
        $query = $this->buildQuery();

        $this->export($query);
    }

    /**
     * Export the query results.
     * Override this method to customize the export process.
     */
    protected function export(QueryBuilder|EloquentBuilder $query): void
    {
        // To be implemented - CSV export logic
    }
}
