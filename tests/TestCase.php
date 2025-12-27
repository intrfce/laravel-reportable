<?php

namespace Intrfce\LaravelReportable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Intrfce\LaravelReportable\LaravelReportableServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelReportableServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('laravel-reportable.disk', 'local');
        $app['config']->set('laravel-reportable.output_path', 'reports');
        $app['config']->set('laravel-reportable.chunk_size', 100);

        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);
    }

    /**
     * Set up the test database schema.
     */
    protected function setUpDatabase(): void
    {
        // Create report_exports table
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->string('reportable_class');
            $table->longText('serialized_reportable');
            $table->longText('query_sql')->nullable();
            $table->json('query_bindings')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('retried_from_id')->nullable();
            $table->string('output_disk')->nullable();
            $table->string('output_path')->nullable();
            $table->unsignedBigInteger('rows_processed')->default(0);
            $table->unsignedBigInteger('total_rows')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->string('role')->default('user');
            $table->timestamps();
        });

        // Create products table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->string('category')->nullable();
            $table->timestamps();
        });

        // Create orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('pending');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        // Create order_items table (pivot between orders and products)
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Seed the database with test data.
     */
    protected function seedDatabase(): void
    {
        // Create users
        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'role' => 'admin'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'active', 'role' => 'user'],
            ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'status' => 'inactive', 'role' => 'user'],
            ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'status' => 'active', 'role' => 'editor'],
            ['name' => 'Charlie Davis', 'email' => 'charlie@example.com', 'status' => 'suspended', 'role' => 'user'],
        ];

        foreach ($users as $user) {
            \Intrfce\LaravelReportable\Tests\Fixtures\Models\User::create($user);
        }

        // Create products
        $products = [
            ['name' => 'Laptop', 'sku' => 'TECH-001', 'price' => 999.99, 'stock' => 50, 'category' => 'Electronics'],
            ['name' => 'Mouse', 'sku' => 'TECH-002', 'price' => 29.99, 'stock' => 200, 'category' => 'Electronics'],
            ['name' => 'Keyboard', 'sku' => 'TECH-003', 'price' => 79.99, 'stock' => 150, 'category' => 'Electronics'],
            ['name' => 'Desk Chair', 'sku' => 'FURN-001', 'price' => 299.99, 'stock' => 30, 'category' => 'Furniture'],
            ['name' => 'Standing Desk', 'sku' => 'FURN-002', 'price' => 599.99, 'stock' => 20, 'category' => 'Furniture'],
        ];

        foreach ($products as $product) {
            \Intrfce\LaravelReportable\Tests\Fixtures\Models\Product::create($product);
        }

        // Create orders with items
        $orders = [
            ['user_id' => 1, 'order_number' => 'ORD-001', 'status' => 'completed', 'items' => [[1, 1], [2, 2]]],
            ['user_id' => 1, 'order_number' => 'ORD-002', 'status' => 'pending', 'items' => [[3, 1]]],
            ['user_id' => 2, 'order_number' => 'ORD-003', 'status' => 'completed', 'items' => [[4, 1], [5, 1]]],
            ['user_id' => 2, 'order_number' => 'ORD-004', 'status' => 'shipped', 'items' => [[1, 2]]],
            ['user_id' => 4, 'order_number' => 'ORD-005', 'status' => 'completed', 'items' => [[2, 5], [3, 2]]],
        ];

        foreach ($orders as $orderData) {
            $items = $orderData['items'];
            unset($orderData['items']);

            $order = \Intrfce\LaravelReportable\Tests\Fixtures\Models\Order::create($orderData);

            $total = 0;
            foreach ($items as [$productId, $quantity]) {
                $product = \Intrfce\LaravelReportable\Tests\Fixtures\Models\Product::find($productId);
                $itemTotal = $product->price * $quantity;
                $total += $itemTotal;

                \Intrfce\LaravelReportable\Tests\Fixtures\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'total' => $itemTotal,
                ]);
            }

            $order->update(['total' => $total]);
        }
    }
}
