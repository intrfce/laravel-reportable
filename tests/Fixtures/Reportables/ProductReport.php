<?php

namespace Intrfce\LaravelReportable\Tests\Fixtures\Reportables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Intrfce\LaravelReportable\Reportable;
use Intrfce\LaravelReportable\Tests\Fixtures\Models\Product;

class ProductReport extends Reportable
{
    protected ?string $category = null;

    public function __construct(?string $category = null)
    {
        $this->category = $category;
    }

    public function query(): QueryBuilder|EloquentBuilder
    {
        $query = Product::query()->select(['id', 'name', 'sku', 'price', 'stock', 'category']);

        if ($this->category) {
            $query->where('category', $this->category);
        }

        return $query;
    }

    public function filename(): string
    {
        $suffix = $this->category ? '-' . strtolower($this->category) : '';

        return "products{$suffix}.csv";
    }

    public function mapHeaders(): array
    {
        return [
            'id' => 'Product ID',
            'name' => 'Product Name',
            'sku' => 'SKU',
            'price' => 'Price',
            'stock' => 'Stock Level',
            'category' => 'Category',
        ];
    }
}
