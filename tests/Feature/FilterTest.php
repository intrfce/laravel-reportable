<?php

use Intrfce\LaravelReportable\Enums\FilterComparator;
use Intrfce\LaravelReportable\Filter;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\UserReport;

beforeEach(function () {
    $this->seedDatabase();
});

it('can create a filter with make method', function () {
    $filter = Filter::make('status', FilterComparator::Equals, 'active');

    expect($filter->column)->toBe('status');
    expect($filter->comparator)->toBe(FilterComparator::Equals);
    expect($filter->value)->toBe('active');
});

it('can create filters with static helper methods', function () {
    expect(Filter::equals('status', 'active')->comparator)->toBe(FilterComparator::Equals);
    expect(Filter::notEquals('status', 'active')->comparator)->toBe(FilterComparator::NotEquals);
    expect(Filter::greaterThan('price', 100)->comparator)->toBe(FilterComparator::GreaterThan);
    expect(Filter::lessThan('price', 100)->comparator)->toBe(FilterComparator::LessThan);
    expect(Filter::contains('name', 'John')->comparator)->toBe(FilterComparator::Contains);
    expect(Filter::in('status', ['active', 'pending'])->comparator)->toBe(FilterComparator::In);
    expect(Filter::isNull('deleted_at')->comparator)->toBe(FilterComparator::IsNull);
    expect(Filter::between('price', 10, 100)->value)->toBe([10, 100]);
});

it('applies equals filter correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::equals('status', 'active'),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(3);
    expect($results->pluck('status')->unique()->toArray())->toBe(['active']);
});

it('applies not equals filter correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::notEquals('status', 'active'),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('status')->contains('active'))->toBeFalse();
});

it('applies contains filter correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::contains('name', 'John'),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('John Doe');
});

it('applies starts with filter correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::startsWith('email', 'j'),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('email')->toArray())->toContain('john@example.com', 'jane@example.com');
});

it('applies in filter correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::in('role', ['admin', 'editor']),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('role')->toArray())->toContain('admin', 'editor');
});

it('applies not in filter correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::notIn('role', ['admin', 'editor']),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(3);
    expect($results->pluck('role')->unique()->toArray())->toBe(['user']);
});

it('applies multiple filters correctly', function () {
    $report = (new UserReport)->withFilters([
        Filter::equals('status', 'active'),
        Filter::equals('role', 'user'),
    ]);

    $query = $report->getBuiltQuery();
    $results = $query->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Jane Smith');
});

it('can add filters one at a time', function () {
    $report = (new UserReport)
        ->addFilter(Filter::equals('status', 'active'))
        ->addFilter(Filter::equals('role', 'admin'));

    expect($report->getFilters())->toHaveCount(2);

    $results = $report->getBuiltQuery()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('John Doe');
});
