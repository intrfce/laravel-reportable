# Laravel Reportable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/intrfce/laravel-reportable.svg?style=flat-square)](https://packagist.org/packages/intrfce/laravel-reportable)
[![Total Downloads](https://img.shields.io/packagist/dt/intrfce/laravel-reportable.svg?style=flat-square)](https://packagist.org/packages/intrfce/laravel-reportable)

Run large queries and export them to CSV files for reports in Laravel. Supports queued exports, chunked processing, filters, and automatic retry functionality.

## Installation

Install the package via composer:

```bash
composer require intrfce/laravel-reportable
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-reportable-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="laravel-reportable-config"
```

## Creating a Reportable

Generate a new reportable class:

```bash
php artisan make:reportable UserExport
```

This creates a class in `app/Reportables/UserExport.php`:

```php
<?php

namespace App\Reportables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Intrfce\LaravelReportable\Reportable;

class UserExport extends Reportable
{
    public function query(): QueryBuilder|EloquentBuilder
    {
        return User::query()->select(['id', 'name', 'email', 'created_at']);
    }

    public function filename(): string
    {
        return 'users-' . now()->format('Y-m-d') . '.csv';
    }

    public function mapHeaders(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Full Name',
            'email' => 'Email Address',
            'created_at' => 'Registered On',
        ];
    }
}
```

## Dispatching a Report

Dispatch a report to be processed in the background:

```php
use App\Reportables\UserExport;

$export = (new UserExport())->dispatch();

// $export is a ReportExport model instance
echo $export->id; // The export ID for tracking
```

The report is automatically queued and processed in the background.

## Using Filters

Apply filters to your report without modifying the reportable class:

```php
use App\Reportables\UserExport;
use Intrfce\LaravelReportable\Filter;

$export = (new UserExport())
    ->withFilters([
        Filter::equals('status', 'active'),
        Filter::greaterThan('created_at', now()->subDays(30)),
    ])
    ->dispatch();
```

### Available Filter Methods

```php
// Comparison
Filter::equals('column', 'value');
Filter::notEquals('column', 'value');
Filter::greaterThan('column', 100);
Filter::greaterThanOrEqual('column', 100);
Filter::lessThan('column', 100);
Filter::lessThanOrEqual('column', 100);

// String matching
Filter::contains('name', 'john');         // LIKE %john%
Filter::doesNotContain('name', 'test');   // NOT LIKE %test%
Filter::startsWith('email', 'admin');     // LIKE admin%
Filter::endsWith('email', '.com');        // LIKE %.com

// Array operations
Filter::in('status', ['active', 'pending']);
Filter::notIn('role', ['banned', 'suspended']);
Filter::between('price', 10, 100);

// Null checks
Filter::isNull('deleted_at');
Filter::isNotNull('verified_at');

// Generic
Filter::make('column', FilterComparator::Equals, 'value');
```

### Adding Filters Individually

```php
$report = (new UserExport())
    ->addFilter(Filter::equals('status', 'active'))
    ->addFilter(Filter::contains('email', '@company.com'));
```

### Custom Filter Application

Override the `applyFilters` method for custom filter logic:

```php
class UserExport extends Reportable
{
    protected function applyFilters(QueryBuilder|EloquentBuilder $query): QueryBuilder|EloquentBuilder
    {
        foreach ($this->filters as $filter) {
            // Custom logic here
            $query = $this->applyFilter($query, $filter);
        }

        return $query;
    }
}
```

## Constructor Arguments

Pass arguments to your reportable for dynamic queries:

```php
class OrderExport extends Reportable
{
    public function __construct(
        protected ?int $userId = null,
        protected ?string $status = null,
    ) {}

    public function query(): QueryBuilder|EloquentBuilder
    {
        $query = Order::query()->select(['id', 'order_number', 'total', 'status']);

        if ($this->userId) {
            $query->where('user_id', $this->userId);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query;
    }

    public function filename(): string
    {
        return 'orders-' . ($this->userId ?? 'all') . '.csv';
    }
}

// Usage
$export = (new OrderExport(userId: 123, status: 'completed'))->dispatch();
```

## Output Location

### Custom Disk

```php
$export = (new UserExport())
    ->toDisk('s3')
    ->dispatch();
```

### Custom Path

```php
$export = (new UserExport())
    ->toPath('exports/users')
    ->dispatch();

// File will be at: exports/users/users-2024-01-15.csv
```

### Both

```php
$export = (new UserExport())
    ->toDisk('s3')
    ->toPath('reports/daily')
    ->dispatch();
```

## Processing Modes

### Chunked Processing (Default)

Processes data in chunks to handle large datasets with minimal memory usage:

```php
$export = (new UserExport())
    ->chunked()  // This is the default
    ->dispatch();
```

Configure chunk size in `config/laravel-reportable.php` or via environment:

```env
REPORTABLE_CHUNK_SIZE=1000
```

### All At Once

For smaller datasets, process everything in memory:

```php
$export = (new UserExport())
    ->allAtOnce()
    ->dispatch();
```

## Tracking Export Status

The `ReportExport` model tracks the status of each export:

```php
use Intrfce\LaravelReportable\Models\ReportExport;

$export = ReportExport::find($id);

// Status checks
$export->status;              // ReportExportStatus enum
$export->isRunning();         // true if dispatched or processing
$export->isFinished();        // true if completed or failed

// Progress
$export->rows_processed;      // Number of rows exported
$export->total_rows;          // Total rows (if known)
$export->progressPercentage(); // e.g., 45.5

// Timestamps
$export->started_at;
$export->completed_at;
$export->failed_at;

// Error information
$export->error_message;       // Error message if failed

// Output location
$export->output_disk;
$export->output_path;
```

### Status Values

```php
use Intrfce\LaravelReportable\Enums\ReportExportStatus;

ReportExportStatus::Pending;     // Created, not yet dispatched
ReportExportStatus::Dispatched;  // Job dispatched to queue
ReportExportStatus::Processing;  // Currently exporting
ReportExportStatus::Completed;   // Successfully finished
ReportExportStatus::Failed;      // Failed with error
```

## Retrying Failed Exports

Retry a failed export:

```php
$export = ReportExport::find($id);

if ($export->canRetry()) {
    $retryExport = $export->retry();

    // $retryExport is a new ReportExport linked to the original
    echo $retryExport->retried_from_id; // Original export ID
}
```

Track retry history:

```php
// Get all retries of an export
$export->retries;

// Get the original export (if this is a retry)
$export->retriedFrom;
```

## Joined Queries

Export data from multiple tables:

```php
use Illuminate\Support\Facades\DB;

class OrderSummaryExport extends Reportable
{
    public function query(): QueryBuilder|EloquentBuilder
    {
        return DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select([
                'orders.order_number',
                'users.name as customer_name',
                'products.name as product_name',
                'order_items.quantity',
                'order_items.total as line_total',
            ])
            ->orderBy('orders.id');
    }

    public function mapHeaders(): array
    {
        return [
            'order_number' => 'Order #',
            'customer_name' => 'Customer',
            'product_name' => 'Product',
            'quantity' => 'Qty',
            'line_total' => 'Total',
        ];
    }
}
```

## Configuration

Published config file (`config/laravel-reportable.php`):

```php
return [
    // Default storage disk for exports
    'disk' => env('REPORTABLE_DISK', 'local'),

    // Default output directory
    'output_path' => env('REPORTABLE_OUTPUT_PATH', 'reports'),

    // Queue for processing exports
    'queue' => env('REPORTABLE_QUEUE', 'default'),

    // Queue connection
    'connection' => env('REPORTABLE_CONNECTION'),

    // Rows per chunk for chunked processing
    'chunk_size' => env('REPORTABLE_CHUNK_SIZE', 1000),
];
```

## Accessing the Generated File

After an export completes:

```php
use Illuminate\Support\Facades\Storage;

$export = ReportExport::find($id);

if ($export->status === ReportExportStatus::Completed) {
    $disk = Storage::disk($export->output_disk);

    // Download
    return $disk->download($export->output_path);

    // Get contents
    $csv = $disk->get($export->output_path);

    // Get URL (if disk supports it)
    $url = $disk->url($export->output_path);
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email dan@danmatthews.me instead of using the issue tracker.

## Credits

- [Dan Matthews](https://github.com/intrfce)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
