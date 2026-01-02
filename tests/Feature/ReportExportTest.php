<?php

use Illuminate\Support\Facades\Queue;
use Intrfce\LaravelReportable\Enums\ReportExportStatus;
use Intrfce\LaravelReportable\Filter;
use Intrfce\LaravelReportable\Jobs\ProcessReportExport;
use Intrfce\LaravelReportable\Models\ReportExport;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\ProductReport;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\UserReport;

beforeEach(function () {
    $this->seedDatabase();
    Queue::fake();
});

it('creates a report export when dispatching a reportable', function () {
    $report = new UserReport;
    $export = $report->dispatch();

    expect($export)->toBeInstanceOf(ReportExport::class);
    expect($export->reportable_class)->toBe(UserReport::class);
    expect($export->status)->toBe(ReportExportStatus::Dispatched);
    expect($export->output_disk)->toBe('local');
    expect($export->output_path)->toBe('reports/users.csv');
});

it('stores the serialized reportable', function () {
    $report = (new UserReport)->withFilters([
        Filter::equals('status', 'active'),
    ]);

    $export = $report->dispatch();

    $deserializedReport = $export->getReportable();

    expect($deserializedReport)->toBeInstanceOf(UserReport::class);
    expect($deserializedReport->getFilters())->toHaveCount(1);
});

it('stores the compiled SQL query', function () {
    $report = (new UserReport)->withFilters([
        Filter::equals('status', 'active'),
    ]);

    $export = $report->dispatch();

    expect($export->query_sql)->toContain('select');
    expect($export->query_sql)->toContain('users');
    expect($export->query_sql)->toContain('active');
});

it('dispatches the processing job', function () {
    $report = new UserReport;
    $report->dispatch();

    Queue::assertPushed(ProcessReportExport::class);
});

it('can track progress', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();

    $export->updateProgress(50, 100);

    expect($export->rows_processed)->toBe(50);
    expect($export->total_rows)->toBe(100);
    expect($export->progressPercentage())->toBe(50.0);
});

it('can mark export as processing', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();
    $export->markAsProcessing();

    expect($export->status)->toBe(ReportExportStatus::Processing);
    expect($export->started_at)->not->toBeNull();
});

it('can mark export as completed', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();
    $export->markAsCompleted();

    expect($export->status)->toBe(ReportExportStatus::Completed);
    expect($export->completed_at)->not->toBeNull();
    expect($export->isFinished())->toBeTrue();
});

it('can mark export as failed', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();
    $export->markAsFailed('Something went wrong');

    expect($export->status)->toBe(ReportExportStatus::Failed);
    expect($export->failed_at)->not->toBeNull();
    expect($export->error_message)->toBe('Something went wrong');
    expect($export->isFinished())->toBeTrue();
});

it('can retry a failed export', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();
    $export->markAsFailed('Network error');

    expect($export->canRetry())->toBeTrue();

    $retryExport = $export->retry();

    expect($retryExport->id)->not->toBe($export->id);
    expect($retryExport->retried_from_id)->toBe($export->id);
    expect($retryExport->status)->toBe(ReportExportStatus::Dispatched);
    expect($retryExport->retriedFrom->id)->toBe($export->id);

    // Original should have the retry in its retries relationship
    expect($export->retries)->toHaveCount(1);
    expect($export->retries->first()->id)->toBe($retryExport->id);
});

it('preserves constructor arguments when serializing', function () {
    $report = new ProductReport('Electronics');
    $export = $report->dispatch();

    $deserializedReport = $export->getReportable();

    // The query should already be filtered by category
    $results = $deserializedReport->getBuiltQuery()->get();

    expect($results)->toHaveCount(3);
    expect($results->pluck('category')->unique()->toArray())->toBe(['Electronics']);
});

it('preserves filters when serializing', function () {
    $report = (new ProductReport)
        ->withFilters([
            Filter::greaterThan('price', 100),
        ]);

    $export = $report->dispatch();
    $deserializedReport = $export->getReportable();

    $results = $deserializedReport->getBuiltQuery()->get();

    // Products over $100: Laptop (999.99), Desk Chair (299.99), Standing Desk (599.99)
    expect($results)->toHaveCount(3);
});

it('preserves disk and path settings when serializing', function () {
    $report = (new UserReport)
        ->toDisk('s3')
        ->toPath('custom/exports');

    $export = $report->dispatch();

    expect($export->output_disk)->toBe('s3');
    expect($export->output_path)->toBe('custom/exports/users.csv');

    $deserializedReport = $export->getReportable();

    expect($deserializedReport->disk())->toBe('s3');
    expect($deserializedReport->outputPath())->toBe('custom/exports/users.csv');
});

it('returns null for progress percentage when total is unknown', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();
    $export->updateProgress(50);

    expect($export->progressPercentage())->toBeNull();
});

it('correctly identifies running status', function () {
    Queue::fake();

    $export = (new UserReport)->dispatch();

    expect($export->isRunning())->toBeTrue();

    $export->markAsProcessing();
    expect($export->isRunning())->toBeTrue();

    $export->markAsCompleted();
    expect($export->isRunning())->toBeFalse();
});
