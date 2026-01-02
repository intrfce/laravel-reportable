<?php

use Illuminate\Http\Request;
use Intrfce\LaravelReportable\Enums\FilterComparator;
use Intrfce\LaravelReportable\Filter;
use Intrfce\LaravelReportable\FilterCollection;
use Intrfce\LaravelReportable\Tests\Fixtures\Reportables\UserReport;

it('can create an empty filter collection', function () {
    $collection = FilterCollection::make();

    expect($collection)->toBeInstanceOf(FilterCollection::class);
    expect($collection->isEmpty())->toBeTrue();
    expect($collection->count())->toBe(0);
});

it('can create a filter collection with filters', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::contains('name', 'john'),
    ]);

    expect($collection->count())->toBe(2);
    expect($collection->isNotEmpty())->toBeTrue();
});

it('can add filters to a collection', function () {
    $collection = FilterCollection::make()
        ->add(Filter::equals('status', 'active'))
        ->add(Filter::contains('name', 'john'));

    expect($collection->count())->toBe(2);
});

it('can add multiple filters at once', function () {
    $collection = FilterCollection::make()->addMany([
        Filter::equals('status', 'active'),
        Filter::contains('name', 'john'),
    ]);

    expect($collection->count())->toBe(2);
});

it('can get all filters', function () {
    $filters = [
        Filter::equals('status', 'active'),
        Filter::contains('name', 'john'),
    ];

    $collection = FilterCollection::make($filters);

    expect($collection->all())->toHaveCount(2);
    expect($collection->all()[0])->toBeInstanceOf(Filter::class);
});

it('can get filters for a specific column', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::equals('status', 'pending'),
        Filter::contains('name', 'john'),
    ]);

    $statusFilters = $collection->forColumn('status');

    expect($statusFilters)->toHaveCount(2);
    expect($collection->hasColumn('status'))->toBeTrue();
    expect($collection->hasColumn('email'))->toBeFalse();
});

it('can convert to array', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::in('role', ['admin', 'editor']),
    ]);

    $array = $collection->toArray();

    expect($array)->toBeArray();
    expect($array[0])->toBe([
        'column' => 'status',
        'operator' => '=',
        'value' => 'active',
    ]);
    expect($array[1])->toBe([
        'column' => 'role',
        'operator' => 'in',
        'value' => ['admin', 'editor'],
    ]);
});

it('can convert to query string', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $queryString = $collection->toQueryString();

    expect($queryString)->toContain('filters');
    expect($queryString)->toContain('status');
    expect($queryString)->toContain('active');
});

it('can create from query string', function () {
    $original = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::greaterThan('age', 18),
    ]);

    $queryString = $original->toQueryString();
    $restored = FilterCollection::fromQueryString($queryString);

    expect($restored->count())->toBe(2);
    expect($restored->all()[0]->column)->toBe('status');
    expect($restored->all()[0]->comparator)->toBe(FilterComparator::Equals);
    expect($restored->all()[0]->value)->toBe('active');
    expect($restored->all()[1]->column)->toBe('age');
    expect($restored->all()[1]->comparator)->toBe(FilterComparator::GreaterThan);
    expect($restored->all()[1]->value)->toBe('18'); // Note: query strings convert to strings
});

it('can create from array', function () {
    $data = [
        ['column' => 'status', 'operator' => '=', 'value' => 'active'],
        ['column' => 'role', 'operator' => 'in', 'value' => ['admin', 'editor']],
    ];

    $collection = FilterCollection::fromArray($data);

    expect($collection->count())->toBe(2);
    expect($collection->all()[0]->column)->toBe('status');
    expect($collection->all()[1]->value)->toBe(['admin', 'editor']);
});

it('can create from laravel request', function () {
    $request = Request::create('/test', 'GET', [
        'filters' => [
            ['column' => 'status', 'operator' => '=', 'value' => 'active'],
            ['column' => 'name', 'operator' => 'contains', 'value' => 'john'],
        ],
    ]);

    $collection = FilterCollection::fromRequest($request);

    expect($collection->count())->toBe(2);
    expect($collection->all()[0]->column)->toBe('status');
    expect($collection->all()[1]->comparator)->toBe(FilterComparator::Contains);
});

it('returns empty collection for invalid request data', function () {
    $request = Request::create('/test', 'GET', [
        'filters' => 'invalid',
    ]);

    $collection = FilterCollection::fromRequest($request);

    expect($collection->isEmpty())->toBeTrue();
});

it('can convert to and from JSON', function () {
    $original = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::in('role', ['admin', 'editor']),
    ]);

    $json = $original->toJson();
    $restored = FilterCollection::fromJson($json);

    expect($restored->count())->toBe(2);
    expect($restored->all()[0]->column)->toBe('status');
    expect($restored->all()[1]->value)->toBe(['admin', 'editor']);
});

it('can append to a URL', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $url = $collection->appendToUrl('https://example.com/reports');

    expect($url)->toStartWith('https://example.com/reports?');
    expect($url)->toContain('filters');
});

it('can append to a URL that already has query params', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $url = $collection->appendToUrl('https://example.com/reports?page=1');

    expect($url)->toContain('?page=1&');
    expect($url)->toContain('filters');
});

it('returns original URL when collection is empty', function () {
    $collection = FilterCollection::make();

    $url = $collection->appendToUrl('https://example.com/reports');

    expect($url)->toBe('https://example.com/reports');
});

it('can merge with another collection', function () {
    $collection1 = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $collection2 = FilterCollection::make([
        Filter::contains('name', 'john'),
    ]);

    $merged = $collection1->merge($collection2);

    expect($merged->count())->toBe(2);
    expect($collection1->count())->toBe(1); // Original unchanged
});

it('can be iterated', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::contains('name', 'john'),
    ]);

    $count = 0;
    foreach ($collection as $filter) {
        expect($filter)->toBeInstanceOf(Filter::class);
        $count++;
    }

    expect($count)->toBe(2);
});

it('can be accessed like an array', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
        Filter::contains('name', 'john'),
    ]);

    expect($collection[0])->toBeInstanceOf(Filter::class);
    expect($collection[0]->column)->toBe('status');
    expect(isset($collection[0]))->toBeTrue();
    expect(isset($collection[5]))->toBeFalse();
});

it('can be JSON serialized', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $json = json_encode($collection);
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray();
    expect($decoded[0]['column'])->toBe('status');
});

it('works with the Reportable withFilters method', function () {
    $this->seedDatabase();

    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $report = (new UserReport())->withFilters($collection);

    expect($report->getFilters())->toHaveCount(1);

    $results = $report->getBuiltQuery()->get();
    expect($results)->toHaveCount(3);
});

it('can get filter collection from reportable', function () {
    $report = (new UserReport())->withFilters([
        Filter::equals('status', 'active'),
        Filter::contains('name', 'john'),
    ]);

    $collection = $report->getFilterCollection();

    expect($collection)->toBeInstanceOf(FilterCollection::class);
    expect($collection->count())->toBe(2);
});

it('can convert to query array for Laravel URL generation', function () {
    $collection = FilterCollection::make([
        Filter::equals('status', 'active'),
    ]);

    $queryArray = $collection->toQueryArray();

    expect($queryArray)->toHaveKey('filters');
    expect($queryArray['filters'])->toBeArray();

    // Can be used with Laravel's route() helper
    // route('reports.index', $collection->toQueryArray())
});

it('handles between filter serialization', function () {
    $collection = FilterCollection::make([
        Filter::between('price', 10, 100),
    ]);

    $array = $collection->toArray();

    expect($array[0]['value'])->toBe([10, 100]);

    $restored = FilterCollection::fromArray($array);
    expect($restored->all()[0]->value)->toBe([10, 100]);
});

it('handles null value filters', function () {
    $collection = FilterCollection::make([
        Filter::isNull('deleted_at'),
        Filter::isNotNull('verified_at'),
    ]);

    $array = $collection->toArray();

    expect($array[0]['value'])->toBeNull();
    expect($array[1]['value'])->toBeNull();

    $restored = FilterCollection::fromArray($array);
    expect($restored->all()[0]->comparator)->toBe(FilterComparator::IsNull);
    expect($restored->all()[1]->comparator)->toBe(FilterComparator::IsNotNull);
});
