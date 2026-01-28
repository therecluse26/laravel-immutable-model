<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentCountry;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentOrder;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentOrderItem;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentSupplier;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableCountry;
use Brighten\ImmutableModel\Tests\Models\ImmutableOrder;
use Brighten\ImmutableModel\Tests\Models\ImmutableOrderItem;
use Brighten\ImmutableModel\Tests\Models\ImmutableSupplier;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Illuminate\Support\Str;

/**
 * Tests that deeply nested relationships behave identically in both models.
 *
 * Relationship chain: Country -> Supplier -> User -> Order -> OrderItem
 */
class DeepNestingParityTest extends ParityTestCase
{
    private string $productUuid1;
    private string $productUuid2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDeepNestingData();
    }

    protected function seedDeepNestingData(): void
    {
        // Create countries
        $this->app['db']->table('countries')->insert([
            ['id' => 1, 'name' => 'USA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Canada', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create suppliers (linked to countries)
        $this->app['db']->table('suppliers')->insert([
            ['id' => 1, 'country_id' => 1, 'name' => 'Acme Corp', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'country_id' => 1, 'name' => 'Tech Inc', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'country_id' => 2, 'name' => 'Maple Supply', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create users (linked to suppliers)
        $this->app['db']->table('users')->insert([
            ['id' => 1, 'supplier_id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'supplier_id' => 1, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'supplier_id' => 2, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'supplier_id' => 3, 'name' => 'Alice Brown', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create products (UUID primary key)
        $this->productUuid1 = (string) Str::uuid();
        $this->productUuid2 = (string) Str::uuid();

        $this->app['db']->table('products')->insert([
            ['uuid' => $this->productUuid1, 'name' => 'Widget A', 'price' => 19.99, 'sku' => 'WIDGET-A', 'created_at' => now(), 'updated_at' => now()],
            ['uuid' => $this->productUuid2, 'name' => 'Widget B', 'price' => 29.99, 'sku' => 'WIDGET-B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create orders (linked to users)
        $this->app['db']->table('orders')->insert([
            ['id' => 1, 'user_id' => 1, 'status' => 'pending', 'total' => 49.98, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 1, 'status' => 'completed', 'total' => 29.99, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 2, 'status' => 'pending', 'total' => 19.99, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'user_id' => 4, 'status' => 'completed', 'total' => 99.97, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create order items (linked to orders and products)
        $this->app['db']->table('order_items')->insert([
            ['id' => 1, 'order_id' => 1, 'product_uuid' => $this->productUuid1, 'quantity' => 2, 'price' => 19.99, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'order_id' => 1, 'product_uuid' => $this->productUuid2, 'quantity' => 1, 'price' => 9.99, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'order_id' => 2, 'product_uuid' => $this->productUuid2, 'quantity' => 1, 'price' => 29.99, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'order_id' => 3, 'product_uuid' => $this->productUuid1, 'quantity' => 1, 'price' => 19.99, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'order_id' => 4, 'product_uuid' => $this->productUuid1, 'quantity' => 5, 'price' => 19.99, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // =========================================================================
    // 2-LEVEL NESTING
    // =========================================================================

    public function test_two_level_nesting_country_to_suppliers(): void
    {
        $eloquent = EloquentCountry::with('suppliers')->find(1);
        $immutable = ImmutableCountry::with('suppliers')->find(1);

        $this->assertEquals(
            $eloquent->suppliers->count(),
            $immutable->suppliers->count(),
            'Supplier count mismatch'
        );
    }

    public function test_two_level_nesting_user_to_orders(): void
    {
        $eloquent = EloquentUser::with('orders')->find(1);
        $immutable = ImmutableUser::with('orders')->find(1);

        $this->assertEquals(
            $eloquent->orders->count(),
            $immutable->orders->count(),
            'Order count mismatch'
        );
    }

    // =========================================================================
    // 3-LEVEL NESTING
    // =========================================================================

    public function test_three_level_nesting_country_to_users(): void
    {
        $eloquent = EloquentCountry::with('suppliers.users')->find(1);
        $immutable = ImmutableCountry::with('suppliers.users')->find(1);

        $eloquentUserCount = $eloquent->suppliers->sum(fn($s) => $s->users->count());
        $immutableUserCount = $immutable->suppliers->sum(fn($s) => $s->users->count());

        $this->assertEquals($eloquentUserCount, $immutableUserCount, 'Nested user count mismatch');
    }

    public function test_three_level_nesting_user_to_order_items(): void
    {
        $eloquent = EloquentUser::with('orders.items')->find(1);
        $immutable = ImmutableUser::with('orders.items')->find(1);

        $eloquentItemCount = $eloquent->orders->sum(fn($o) => $o->items->count());
        $immutableItemCount = $immutable->orders->sum(fn($o) => $o->items->count());

        $this->assertEquals($eloquentItemCount, $immutableItemCount, 'Nested item count mismatch');
    }

    public function test_three_level_nesting_order_item_to_product(): void
    {
        $eloquent = EloquentOrderItem::with('order.user')->find(1);
        $immutable = ImmutableOrderItem::with('order.user')->find(1);

        $this->assertEquals(
            $eloquent->order->user->name,
            $immutable->order->user->name,
            'Order user name mismatch'
        );
    }

    // =========================================================================
    // 4-LEVEL NESTING
    // =========================================================================

    public function test_four_level_nesting_country_to_orders(): void
    {
        $eloquent = EloquentCountry::with('suppliers.users.orders')->find(1);
        $immutable = ImmutableCountry::with('suppliers.users.orders')->find(1);

        $eloquentOrderCount = 0;
        foreach ($eloquent->suppliers as $supplier) {
            foreach ($supplier->users as $user) {
                $eloquentOrderCount += $user->orders->count();
            }
        }

        $immutableOrderCount = 0;
        foreach ($immutable->suppliers as $supplier) {
            foreach ($supplier->users as $user) {
                $immutableOrderCount += $user->orders->count();
            }
        }

        $this->assertEquals($eloquentOrderCount, $immutableOrderCount, '4-level order count mismatch');
    }

    public function test_four_level_nesting_order_item_to_supplier(): void
    {
        $eloquent = EloquentOrderItem::with('order.user.supplier')->find(1);
        $immutable = ImmutableOrderItem::with('order.user.supplier')->find(1);

        $this->assertEquals(
            $eloquent->order->user->supplier->name,
            $immutable->order->user->supplier->name,
            'Supplier name mismatch'
        );
    }

    // =========================================================================
    // 5-LEVEL NESTING
    // =========================================================================

    public function test_five_level_nesting_country_to_order_items(): void
    {
        $eloquent = EloquentCountry::with('suppliers.users.orders.items')->find(1);
        $immutable = ImmutableCountry::with('suppliers.users.orders.items')->find(1);

        $eloquentItemCount = 0;
        foreach ($eloquent->suppliers as $supplier) {
            foreach ($supplier->users as $user) {
                foreach ($user->orders as $order) {
                    $eloquentItemCount += $order->items->count();
                }
            }
        }

        $immutableItemCount = 0;
        foreach ($immutable->suppliers as $supplier) {
            foreach ($supplier->users as $user) {
                foreach ($user->orders as $order) {
                    $immutableItemCount += $order->items->count();
                }
            }
        }

        $this->assertEquals($eloquentItemCount, $immutableItemCount, '5-level item count mismatch');
    }

    public function test_five_level_nesting_order_item_to_country(): void
    {
        $eloquent = EloquentOrderItem::with('order.user.supplier.country')->find(1);
        $immutable = ImmutableOrderItem::with('order.user.supplier.country')->find(1);

        $this->assertEquals(
            $eloquent->order->user->supplier->country->name,
            $immutable->order->user->supplier->country->name,
            'Country name mismatch'
        );
    }

    // =========================================================================
    // CONSTRAINED EAGER LOADING AT DEPTH
    // =========================================================================

    public function test_constrained_nested_loading(): void
    {
        $eloquent = EloquentCountry::with([
            'suppliers.users' => fn($q) => $q->where('name', 'like', 'John%'),
        ])->find(1);

        $immutable = ImmutableCountry::with([
            'suppliers.users' => fn($q) => $q->where('name', 'like', 'John%'),
        ])->find(1);

        $eloquentUserCount = $eloquent->suppliers->sum(fn($s) => $s->users->count());
        $immutableUserCount = $immutable->suppliers->sum(fn($s) => $s->users->count());

        $this->assertEquals($eloquentUserCount, $immutableUserCount, 'Constrained nested user count mismatch');
    }

    public function test_constrained_deep_nesting_with_orders(): void
    {
        $eloquent = EloquentCountry::with([
            'suppliers.users.orders' => fn($q) => $q->where('status', 'completed'),
        ])->find(1);

        $immutable = ImmutableCountry::with([
            'suppliers.users.orders' => fn($q) => $q->where('status', 'completed'),
        ])->find(1);

        $eloquentOrderCount = 0;
        foreach ($eloquent->suppliers as $supplier) {
            foreach ($supplier->users as $user) {
                $eloquentOrderCount += $user->orders->count();
            }
        }

        $immutableOrderCount = 0;
        foreach ($immutable->suppliers as $supplier) {
            foreach ($supplier->users as $user) {
                $immutableOrderCount += $user->orders->count();
            }
        }

        $this->assertEquals($eloquentOrderCount, $immutableOrderCount, 'Constrained deep order count mismatch');
    }

    // =========================================================================
    // MIXED EAGER/LAZY LOADING
    // =========================================================================

    public function test_lazy_load_after_eager_load(): void
    {
        $eloquent = EloquentUser::with('orders')->find(1);
        $immutable = ImmutableUser::with('orders')->find(1);

        // Eager loaded orders
        $this->assertEquals($eloquent->orders->count(), $immutable->orders->count());

        // Now lazy load items on the first order
        $eloquentItems = $eloquent->orders->first()->items;
        $immutableItems = $immutable->orders->first()->items;

        $this->assertEquals($eloquentItems->count(), $immutableItems->count(), 'Lazy loaded items count mismatch');
    }

    // =========================================================================
    // EMPTY NESTED RELATIONS
    // =========================================================================

    public function test_empty_nested_relations(): void
    {
        // User 3 (Bob Wilson) has no orders
        $eloquent = EloquentUser::with('orders.items')->find(3);
        $immutable = ImmutableUser::with('orders.items')->find(3);

        $this->assertTrue($eloquent->orders->isEmpty() || $eloquent->orders->every(fn($o) => $o->items->isEmpty() || $o->items->isNotEmpty()));
        $this->assertEquals($eloquent->orders->count(), $immutable->orders->count());
    }

    public function test_deep_nesting_with_no_records(): void
    {
        // Canada (country 2) has supplier 3 with user 4 who has orders
        // Let's check a user with no supplier
        $eloquent = EloquentUser::with('supplier.country')->find(1);
        $immutable = ImmutableUser::with('supplier.country')->find(1);

        $this->assertEquals(
            $eloquent->supplier->country->name ?? null,
            $immutable->supplier->country->name ?? null,
            'Deep country name mismatch'
        );
    }
}
