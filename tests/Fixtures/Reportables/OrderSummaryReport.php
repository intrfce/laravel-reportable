<?php

namespace Intrfce\LaravelReportable\Tests\Fixtures\Reportables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Intrfce\LaravelReportable\Reportable;

class OrderSummaryReport extends Reportable
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
                'users.email as customer_email',
                'orders.status as order_status',
                'products.name as product_name',
                'order_items.quantity',
                'order_items.unit_price',
                'order_items.total as line_total',
                'orders.total as order_total',
            ])
            ->orderBy('orders.id')
            ->orderBy('order_items.id');
    }

    public function filename(): string
    {
        return 'order-summary.csv';
    }

    public function mapHeaders(): array
    {
        return [
            'order_number' => 'Order #',
            'customer_name' => 'Customer',
            'customer_email' => 'Email',
            'order_status' => 'Status',
            'product_name' => 'Product',
            'quantity' => 'Qty',
            'unit_price' => 'Unit Price',
            'line_total' => 'Line Total',
            'order_total' => 'Order Total',
        ];
    }
}
