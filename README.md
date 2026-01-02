# Laravel Reportable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/intrfce/laravel-reportable.svg?style=flat-square)](https://packagist.org/packages/intrfce/laravel-reportable)
[![Total Downloads](https://img.shields.io/packagist/dt/intrfce/laravel-reportable.svg?style=flat-square)](https://packagist.org/packages/intrfce/laravel-reportable)

Reportable is a package I've put together to facilitate running large reports on a SQL-like database, using jobs in your
queue.

### Features and assumptions

#### Opinionated workflow

The idea is basic: exports are classes that define a query and can be passed parameters and filters.

When you dispatch an export to the queue, a Model is saved to the database with the export's metadata, and can be used
to track it's progress, any errors that might occur, and allow it to be retried easily.

#### Query performance is your concern

Yes, you read that right, i'm touting that as a feature. The package doesn't make any assumptions or guesses, nor any
eloquent "magic" applied for you to your queries.

Databases, and data structure, are different across so many applications, that the package doesn't make any assumptions
or try to take any shortcuts for you.

#### Supports both Eloquent and Query Builder queries

You can use Eloquent to build queries with this package, it's supported, but our specifice use case was for users with
more advanced needs, using Laravel's Query Builder.

The underlying query is _always_ parsed to SQL (for the moment) and stored as SQL, so it can be retried easily.

#### Chunking is off by default, but available.

The idea is that you build queries that are efficient enough to run in one go, while the output is streamed directly to
a CSV file on the filesystem.

But, chunking is built in as a feature in the case you're exporting extremely large amounts of data, or running a
slightly less than ideal query that might exceed the timeout limits of your queues.

#####       

### Database support.

The hope is to fully support all drivers that Laravel supports - as the package is mainly a facilitator and wrapper that
takes database results and exports them to CSV in
an opinionated way, rather than dictating query logic, structure, or syntax.

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

### Capturing Filters from URL

Automatically capture filters from the current request URL:

```php
// Captures filters from ?filters[0][column]=status&filters[0][operator]==&...
$export = (new UserExport())
    ->withFiltersFromUrl()
    ->dispatch();

// Capture from a specific group in the URL
// e.g., ?user_report[0][column]=status&user_report[0][operator]==&...
$export = (new UserExport())
    ->withFiltersFromUrl('user_report')
    ->dispatch();

// Combine with programmatic filters
$export = (new UserExport())
    ->addFilter(Filter::equals('type', 'premium'))
    ->withFiltersFromUrl('user_report')  // Merges URL filters with existing
    ->dispatch();
```

This is useful when building filter UIs where users can configure filters via query parameters.

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

## Filter Collections

Use `FilterCollection` to group filters and serialize them to/from URLs and requests.

### Creating a Collection

```php
use Intrfce\LaravelReportable\FilterCollection;
use Intrfce\LaravelReportable\Filter;

$filters = FilterCollection::make([
    Filter::equals('status', 'active'),
    Filter::greaterThan('created_at', '2024-01-01'),
]);

// Or build it fluently
$filters = FilterCollection::make()
    ->add(Filter::equals('status', 'active'))
    ->add(Filter::contains('name', 'john'));

// Use with a reportable
$export = (new UserExport())
    ->withFilters($filters)
    ->dispatch();
```

### Serializing to URL

```php
$filters = FilterCollection::make([
    Filter::equals('status', 'active'),
    Filter::in('role', ['admin', 'editor']),
]);

// Get query string
$queryString = $filters->toQueryString();
// filters[0][column]=status&filters[0][operator]=%3D&filters[0][value]=active&...

// Append to URL
$url = $filters->appendToUrl('https://example.com/reports');
// https://example.com/reports?filters[0][column]=status&...

// Get array for Laravel's route() helper
$url = route('reports.index', $filters->toQueryArray());
```

### Reading from Request

```php
// In a controller
public function index(Request $request)
{
    $filters = FilterCollection::fromRequest($request);

    $export = (new UserExport())
        ->withFilters($filters)
        ->dispatch();

    return redirect()->route('exports.show', $export);
}
```

### Reading from Query String

```php
$filters = FilterCollection::fromQueryString($queryString);
```

### JSON Serialization

```php
// To JSON
$json = $filters->toJson();

// From JSON
$filters = FilterCollection::fromJson($json);

// Also works with json_encode()
$json = json_encode($filters);
```

### Collection Methods

```php
$filters = FilterCollection::make([...]);

// Check if empty
$filters->isEmpty();
$filters->isNotEmpty();

// Count filters
$filters->count();
count($filters); // Also works

// Get filters for a column
$statusFilters = $filters->forColumn('status');
$filters->hasColumn('status'); // true/false

// Merge collections
$combined = $filters->merge($otherFilters);

// Iterate
foreach ($filters as $filter) {
    echo $filter->column;
}

// Array access
$first = $filters[0];
```

### Filter Groups

Use groups to have multiple independent filter collections in the same URL. This is useful when you need to filter multiple datasets on the same page.

```php
// Create collections with group names
$userFilters = FilterCollection::make([
    Filter::equals('status', 'active'),
], 'users');

$orderFilters = FilterCollection::make([
    Filter::greaterThan('total', 100),
], 'orders');

// Or set the group after creation
$filters = FilterCollection::make([...])
    ->withGroup('my_filters');

// Get the group name
$group = $filters->getGroup(); // 'my_filters'
```

#### Serializing Grouped Filters

```php
// Groups appear as their own keys in the URL
$userFilters = FilterCollection::make([
    Filter::equals('status', 'active'),
], 'users');

$url = $userFilters->appendToUrl('https://example.com/dashboard');
// https://example.com/dashboard?users[0][column]=status&users[0][operator]==&users[0][value]=active

// Multiple groups can coexist in the same URL
$url = $userFilters->appendToUrl($url);
$url = $orderFilters->appendToUrl($url);
// https://example.com/dashboard?users[0][...]&orders[0][...]
```

#### Reading Grouped Filters from Request

```php
// In a controller - extract specific filter groups
public function dashboard(Request $request)
{
    $userFilters = FilterCollection::fromRequest($request, 'users');
    $orderFilters = FilterCollection::fromRequest($request, 'orders');

    $userExport = (new UserExport())
        ->withFilters($userFilters)
        ->dispatch();

    $orderExport = (new OrderExport())
        ->withFilters($orderFilters)
        ->dispatch();

    // ...
}
```

#### Reading from Query String with Groups

```php
$userFilters = FilterCollection::fromQueryString($queryString, 'users');
$orderFilters = FilterCollection::fromQueryString($queryString, 'orders');
```

### Preserving Filters in Views

```blade
{{-- Generate a URL with current filters --}}
<a href="{{ route('reports.export', $filters->toQueryArray()) }}">
    Export with Filters
</a>

{{-- Add filters to pagination links --}}
{{ $users->appends($filters->toQueryArray())->links() }}
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


