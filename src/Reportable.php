<?php

namespace Intrfce\LaravelReportable;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Storage;
use Intrfce\LaravelReportable\Enums\FilterComparator;
use Intrfce\LaravelReportable\Models\ReportExport;
use League\Csv\Writer;
use stdClass;

abstract class Reportable
{
    /**
     * The filters to apply to the query.
     *
     * @var array<int, Filter>
     */
    protected array $filters = [];

    /**
     * The storage disk to use for the export.
     */
    protected ?string $disk = null;

    /**
     * The output path for the CSV file.
     */
    protected ?string $outputPath = null;

    /**
     * Whether to use chunked processing.
     */
    protected bool $chunked = true;

    /**
     * Define the query for this report.
     */
    abstract public function query(): QueryBuilder|EloquentBuilder;

    /**
     * Dispatch this reportable as a queued export.
     */
    public function dispatch(): ReportExport
    {
        return ReportExport::fromReportable($this);
    }

    /**
     * Get the built query with filters applied.
     * This is public so the ReportExport model can access it.
     */
    public function getBuiltQuery(): QueryBuilder|EloquentBuilder
    {
        return $this->buildQuery();
    }

    /**
     * Process the export (called by the job).
     */
    public function processExport(ReportExport $export): void
    {
        $query = $this->buildQuery();

        $this->export($query, $export);
    }

    /**
     * Get the filename for the export.
     * Override this method to customize the filename.
     */
    public function filename(): string
    {
        return 'export.csv';
    }

    /**
     * Map column names to human-readable header names.
     * Override this method to provide custom header labels.
     * Return an array where keys are column names and values are header labels.
     *
     * @return array<string, string>
     */
    public function mapHeaders(): array
    {
        return [];
    }

    /**
     * Get the chunk size for processing.
     */
    public function chunkSize(): int
    {
        return config('laravel-reportable.chunk_size', 1000);
    }

    /**
     * Set the storage disk for the export.
     */
    public function toDisk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Get the storage disk for the export.
     */
    public function disk(): string
    {
        return $this->disk ?? config('laravel-reportable.disk', 'local');
    }

    /**
     * Set the output path for the CSV file.
     */
    public function toPath(string $path): static
    {
        $this->outputPath = $path;

        return $this;
    }

    /**
     * Get the full output path for the CSV file.
     */
    public function outputPath(): string
    {
        $basePath = $this->outputPath ?? config('laravel-reportable.output_path', 'reports');

        return rtrim($basePath, '/') . '/' . $this->filename();
    }

    /**
     * Enable chunked processing.
     */
    public function chunked(): static
    {
        $this->chunked = true;

        return $this;
    }

    /**
     * Disable chunked processing (process all at once).
     */
    public function allAtOnce(): static
    {
        $this->chunked = false;

        return $this;
    }

    /**
     * Set the filters to apply to the query.
     *
     * @param  array<int, Filter>|FilterCollection  $filters
     */
    public function withFilters(array|FilterCollection $filters): static
    {
        $this->filters = $filters instanceof FilterCollection
            ? $filters->all()
            : $filters;

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
     * Get the filters as a FilterCollection.
     */
    public function getFilterCollection(): FilterCollection
    {
        return new FilterCollection($this->filters);
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
     * Export the query results to CSV.
     */
    protected function export(QueryBuilder|EloquentBuilder $query, ReportExport $export): void
    {
        $storage = Storage::disk($this->disk());
        $path = $this->outputPath();

        // Ensure the directory exists
        $directory = dirname($path);
        if ($directory !== '.' && ! $storage->exists($directory)) {
            $storage->makeDirectory($directory);
        }

        // Create a temporary file for writing
        $tempFile = tempnam(sys_get_temp_dir(), 'reportable_');
        $csv = Writer::createFromPath($tempFile, 'w+');

        $headersWritten = false;
        $headerMap = $this->mapHeaders();

        if ($this->chunked) {
            $this->exportChunked($query, $csv, $headerMap, $headersWritten, $export);
        } else {
            $this->exportAllAtOnce($query, $csv, $headerMap, $headersWritten, $export);
        }

        // Move the temp file to the final destination
        $storage->put($path, file_get_contents($tempFile));
        unlink($tempFile);
    }

    /**
     * Convert a row to an array.
     *
     * @param  array|Model|stdClass  $row
     */
    protected function rowToArray(mixed $row): array
    {
        if ($row instanceof Model) {
            return $row->toArray();
        }

        return (array) $row;
    }

    /**
     * Build the header row from column names and the header map.
     *
     * @param  array<string>  $columns
     * @param  array<string, string>  $headerMap
     * @return array<string>
     */
    protected function buildHeaders(array $columns, array $headerMap): array
    {
        return array_map(
            fn (string $column) => $headerMap[$column] ?? $column,
            $columns
        );
    }

    /**
     * Export using chunked processing.
     *
     * @param  array<string, string>  $headerMap
     */
    protected function exportChunked(
        QueryBuilder|EloquentBuilder $query,
        Writer $csv,
        array $headerMap,
        bool &$headersWritten,
        ReportExport $export
    ): void {
        $rowsProcessed = 0;

        $query->chunk($this->chunkSize(), function ($rows) use ($csv, $headerMap, &$headersWritten, $export, &$rowsProcessed) {
            foreach ($rows as $row) {
                $data = $this->rowToArray($row);

                if (! $headersWritten) {
                    $csv->insertOne($this->buildHeaders(array_keys($data), $headerMap));
                    $headersWritten = true;
                }

                $csv->insertOne(array_values($data));
                $rowsProcessed++;
            }

            $export->updateProgress($rowsProcessed);
        });
    }

    /**
     * Export all rows at once.
     *
     * @param  array<string, string>  $headerMap
     */
    protected function exportAllAtOnce(
        QueryBuilder|EloquentBuilder $query,
        Writer $csv,
        array $headerMap,
        bool &$headersWritten,
        ReportExport $export
    ): void {
        $rows = $query->get();
        $totalRows = $rows->count();
        $rowsProcessed = 0;

        $export->updateProgress(0, $totalRows);

        foreach ($rows as $row) {
            $data = $this->rowToArray($row);

            if (! $headersWritten) {
                $csv->insertOne($this->buildHeaders(array_keys($data), $headerMap));
                $headersWritten = true;
            }

            $csv->insertOne(array_values($data));
            $rowsProcessed++;
        }

        $export->updateProgress($rowsProcessed, $totalRows);
    }
}
