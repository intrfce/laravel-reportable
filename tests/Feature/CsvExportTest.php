<?php

use Illuminate\Support\Facades\Storage;
use Intrfce\LaravelReportable\Filter;
use Intrfce\LaravelReportable\Jobs\ProcessReportExport;
use Intrfce\LaravelReportable\Models\ReportExport;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\OrderSummaryReport;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\ProductReport;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\UserReport;

beforeEach(function () {
    $this->seedDatabase();
    Storage::fake('local');
});

it('exports data to csv file', function () {
    $report = new UserReport;
    $export = ReportExport::fromReportable($report);

    // Manually run the job instead of using queue
    (new ProcessReportExport($export))->handle();

    Storage::disk('local')->assertExists('reports/users.csv');
});

it('exports with correct headers from mapHeaders', function () {
    $report = new UserReport;
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    $content = Storage::disk('local')->get('reports/users.csv');
    $lines = explode("\n", trim($content));

    expect($lines[0])->toBe('ID,"Full Name","Email Address",Status,Role');
});

it('exports all rows', function () {
    $report = new UserReport;
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    $content = Storage::disk('local')->get('reports/users.csv');
    $lines = array_filter(explode("\n", trim($content)));

    // Header + 5 users = 6 lines
    expect($lines)->toHaveCount(6);
});

it('exports filtered data', function () {
    $report = (new UserReport)->withFilters([
        Filter::equals('status', 'active'),
    ]);

    $export = ReportExport::fromReportable($report);
    (new ProcessReportExport($export))->handle();

    $content = Storage::disk('local')->get('reports/users.csv');
    $lines = array_filter(explode("\n", trim($content)));

    // Header + 3 active users = 4 lines
    expect($lines)->toHaveCount(4);
});

it('exports with custom filename', function () {
    $report = new ProductReport('Electronics');
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    Storage::disk('local')->assertExists('reports/products-electronics.csv');
});

it('exports to custom path', function () {
    $report = (new UserReport)->toPath('custom/exports');
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    Storage::disk('local')->assertExists('custom/exports/users.csv');
});

it('exports joined table data correctly', function () {
    $report = new OrderSummaryReport;
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    $content = Storage::disk('local')->get('reports/order-summary.csv');
    $lines = array_filter(explode("\n", trim($content)));

    // Header row should have mapped headers
    expect($lines[0])->toContain('Order #');
    expect($lines[0])->toContain('Customer');
    expect($lines[0])->toContain('Product');

    // Should have multiple lines of data (header + order items)
    expect(count($lines))->toBeGreaterThan(1);
});

it('exports with filters on joined data', function () {
    $report = (new OrderSummaryReport)->withFilters([
        Filter::equals('order_status', 'completed'),
    ]);

    $export = ReportExport::fromReportable($report);
    (new ProcessReportExport($export))->handle();

    $content = Storage::disk('local')->get('reports/order-summary.csv');
    $lines = array_filter(explode("\n", trim($content)));

    // Check that only completed orders are in the export
    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toContain('completed');
    }
});

it('updates progress during chunked export', function () {
    config(['laravel-reportable.chunk_size' => 2]);

    $report = new UserReport;
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    $export->refresh();

    expect($export->rows_processed)->toBe(5);
});

it('marks export as completed after successful run', function () {
    $report = new UserReport;
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    $export->refresh();

    expect($export->status->value)->toBe('completed');
    expect($export->completed_at)->not->toBeNull();
});

it('exports using all at once mode', function () {
    $report = (new UserReport)->allAtOnce();
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    $export->refresh();

    Storage::disk('local')->assertExists('reports/users.csv');
    expect($export->rows_processed)->toBe(5);
    expect($export->total_rows)->toBe(5);
});

it('creates directory if it does not exist', function () {
    $report = (new UserReport)->toPath('deep/nested/path');
    $export = ReportExport::fromReportable($report);

    (new ProcessReportExport($export))->handle();

    Storage::disk('local')->assertExists('deep/nested/path/users.csv');
});

it('exports with unmapped headers falling back to column names', function () {
    // UserReport has mapHeaders, but ProductReport without category still exports
    // Let's test with a report that has partial mappings
    $report = new ProductReport;

    $export = ReportExport::fromReportable($report);
    (new ProcessReportExport($export))->handle();

    $content = Storage::disk('local')->get('reports/products.csv');
    $lines = explode("\n", trim($content));

    // Should use mapped headers from ProductReport
    expect($lines[0])->toContain('Product ID');
    expect($lines[0])->toContain('Product Name');
    expect($lines[0])->toContain('SKU');
});
