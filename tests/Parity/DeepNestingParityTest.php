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

        // Verify country attributes
        $this->assertModelParity($eloquent, $immutable, 'Country parity failed');

        // Verify suppliers collection
        $eloquentSuppliers = $eloquent->suppliers->sortBy('id')->values();
        $immutableSuppliers = $immutable->suppliers->sortBy('id')->values();
        $this->assertCollectionParity($eloquentSuppliers, $immutableSuppliers);
    }

    public function test_two_level_nesting_user_to_orders(): void
    {
        $eloquent = EloquentUser::with('orders')->find(1);
        $immutable = ImmutableUser::with('orders')->find(1);

        // Verify user attributes
        $this->assertModelParity($eloquent, $immutable, 'User parity failed');

        // Verify orders collection
        $eloquentOrders = $eloquent->orders->sortBy('id')->values();
        $immutableOrders = $immutable->orders->sortBy('id')->values();
        $this->assertCollectionParity($eloquentOrders, $immutableOrders);
    }

    // =========================================================================
    // 3-LEVEL NESTING
    // =========================================================================

    public function test_three_level_nesting_country_to_users(): void
    {
        $eloquent = EloquentCountry::with('suppliers.users')->find(1);
        $immutable = ImmutableCountry::with('suppliers.users')->find(1);

        // Verify country
        $this->assertModelParity($eloquent, $immutable, 'Country parity failed');

        // Verify each supplier and their users
        $eloquentSuppliers = $eloquent->suppliers->sortBy('id')->values();
        $immutableSuppliers = $immutable->suppliers->sortBy('id')->values();

        $this->assertEquals($eloquentSuppliers->count(), $immutableSuppliers->count());
        foreach ($eloquentSuppliers as $i => $eSupplier) {
            $this->assertModelParity($eSupplier, $immutableSuppliers[$i], "Supplier {$i} parity failed");
            $this->assertCollectionParity(
                $eSupplier->users->sortBy('id')->values(),
                $immutableSuppliers[$i]->users->sortBy('id')->values()
            );
        }
    }

    public function test_three_level_nesting_user_to_order_items(): void
    {
        $eloquent = EloquentUser::with('orders.items')->find(1);
        $immutable = ImmutableUser::with('orders.items')->find(1);

        // Verify user
        $this->assertModelParity($eloquent, $immutable, 'User parity failed');

        // Verify each order and their items
        $eloquentOrders = $eloquent->orders->sortBy('id')->values();
        $immutableOrders = $immutable->orders->sortBy('id')->values();

        $this->assertEquals($eloquentOrders->count(), $immutableOrders->count());
        foreach ($eloquentOrders as $i => $eOrder) {
            $this->assertModelParity($eOrder, $immutableOrders[$i], "Order {$i} parity failed");
            $this->assertCollectionParity(
                $eOrder->items->sortBy('id')->values(),
                $immutableOrders[$i]->items->sortBy('id')->values()
            );
        }
    }

    public function test_three_level_nesting_order_item_to_product(): void
    {
        $eloquent = EloquentOrderItem::with('order.user')->find(1);
        $immutable = ImmutableOrderItem::with('order.user')->find(1);

        // Verify order item
        $this->assertModelParity($eloquent, $immutable, 'OrderItem parity failed');

        // Verify nested order
        $this->assertModelParity($eloquent->order, $immutable->order, 'Order parity failed');

        // Verify nested user
        $this->assertModelParity($eloquent->order->user, $immutable->order->user, 'User parity failed');
    }

    // =========================================================================
    // 4-LEVEL NESTING
    // =========================================================================

    public function test_four_level_nesting_country_to_orders(): void
    {
        $eloquent = EloquentCountry::with('suppliers.users.orders')->find(1);
        $immutable = ImmutableCountry::with('suppliers.users.orders')->find(1);

        // Verify country
        $this->assertModelParity($eloquent, $immutable, 'Country parity failed');

        // Verify nested structure
        $eloquentSuppliers = $eloquent->suppliers->sortBy('id')->values();
        $immutableSuppliers = $immutable->suppliers->sortBy('id')->values();

        $this->assertEquals($eloquentSuppliers->count(), $immutableSuppliers->count());
        foreach ($eloquentSuppliers as $i => $eSupplier) {
            $this->assertModelParity($eSupplier, $immutableSuppliers[$i], "Supplier {$i} parity failed");

            $eUsers = $eSupplier->users->sortBy('id')->values();
            $iUsers = $immutableSuppliers[$i]->users->sortBy('id')->values();
            $this->assertEquals($eUsers->count(), $iUsers->count());

            foreach ($eUsers as $j => $eUser) {
                $this->assertModelParity($eUser, $iUsers[$j], "User {$j} parity failed");
                $this->assertCollectionParity(
                    $eUser->orders->sortBy('id')->values(),
                    $iUsers[$j]->orders->sortBy('id')->values()
                );
            }
        }
    }

    public function test_four_level_nesting_order_item_to_supplier(): void
    {
        $eloquent = EloquentOrderItem::with('order.user.supplier')->find(1);
        $immutable = ImmutableOrderItem::with('order.user.supplier')->find(1);

        // Verify each level
        $this->assertModelParity($eloquent, $immutable, 'OrderItem parity failed');
        $this->assertModelParity($eloquent->order, $immutable->order, 'Order parity failed');
        $this->assertModelParity($eloquent->order->user, $immutable->order->user, 'User parity failed');
        $this->assertModelParity($eloquent->order->user->supplier, $immutable->order->user->supplier, 'Supplier parity failed');
    }

    // =========================================================================
    // 5-LEVEL NESTING
    // =========================================================================

    public function test_five_level_nesting_country_to_order_items(): void
    {
        $eloquent = EloquentCountry::with('suppliers.users.orders.items')->find(1);
        $immutable = ImmutableCountry::with('suppliers.users.orders.items')->find(1);

        // Verify country
        $this->assertModelParity($eloquent, $immutable, 'Country parity failed');

        // Verify 5-level nested structure
        $eloquentSuppliers = $eloquent->suppliers->sortBy('id')->values();
        $immutableSuppliers = $immutable->suppliers->sortBy('id')->values();

        $this->assertEquals($eloquentSuppliers->count(), $immutableSuppliers->count());
        foreach ($eloquentSuppliers as $i => $eSupplier) {
            $this->assertModelParity($eSupplier, $immutableSuppliers[$i], "Supplier {$i} parity failed");

            $eUsers = $eSupplier->users->sortBy('id')->values();
            $iUsers = $immutableSuppliers[$i]->users->sortBy('id')->values();
            $this->assertEquals($eUsers->count(), $iUsers->count());

            foreach ($eUsers as $j => $eUser) {
                $this->assertModelParity($eUser, $iUsers[$j], "User {$j} parity failed");

                $eOrders = $eUser->orders->sortBy('id')->values();
                $iOrders = $iUsers[$j]->orders->sortBy('id')->values();
                $this->assertEquals($eOrders->count(), $iOrders->count());

                foreach ($eOrders as $k => $eOrder) {
                    $this->assertModelParity($eOrder, $iOrders[$k], "Order {$k} parity failed");
                    $this->assertCollectionParity(
                        $eOrder->items->sortBy('id')->values(),
                        $iOrders[$k]->items->sortBy('id')->values()
                    );
                }
            }
        }
    }

    public function test_five_level_nesting_order_item_to_country(): void
    {
        $eloquent = EloquentOrderItem::with('order.user.supplier.country')->find(1);
        $immutable = ImmutableOrderItem::with('order.user.supplier.country')->find(1);

        // Verify each level
        $this->assertModelParity($eloquent, $immutable, 'OrderItem parity failed');
        $this->assertModelParity($eloquent->order, $immutable->order, 'Order parity failed');
        $this->assertModelParity($eloquent->order->user, $immutable->order->user, 'User parity failed');
        $this->assertModelParity($eloquent->order->user->supplier, $immutable->order->user->supplier, 'Supplier parity failed');
        $this->assertModelParity($eloquent->order->user->supplier->country, $immutable->order->user->supplier->country, 'Country parity failed');
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

        // Verify country
        $this->assertModelParity($eloquent, $immutable, 'Country parity failed');

        // Verify suppliers and constrained users
        $eloquentSuppliers = $eloquent->suppliers->sortBy('id')->values();
        $immutableSuppliers = $immutable->suppliers->sortBy('id')->values();

        $this->assertEquals($eloquentSuppliers->count(), $immutableSuppliers->count());
        foreach ($eloquentSuppliers as $i => $eSupplier) {
            $this->assertModelParity($eSupplier, $immutableSuppliers[$i], "Supplier {$i} parity failed");
            $this->assertCollectionParity(
                $eSupplier->users->sortBy('id')->values(),
                $immutableSuppliers[$i]->users->sortBy('id')->values()
            );
        }
    }

    public function test_constrained_deep_nesting_with_orders(): void
    {
        $eloquent = EloquentCountry::with([
            'suppliers.users.orders' => fn($q) => $q->where('status', 'completed'),
        ])->find(1);

        $immutable = ImmutableCountry::with([
            'suppliers.users.orders' => fn($q) => $q->where('status', 'completed'),
        ])->find(1);

        // Verify country
        $this->assertModelParity($eloquent, $immutable, 'Country parity failed');

        // Verify nested structure with constrained orders
        $eloquentSuppliers = $eloquent->suppliers->sortBy('id')->values();
        $immutableSuppliers = $immutable->suppliers->sortBy('id')->values();

        $this->assertEquals($eloquentSuppliers->count(), $immutableSuppliers->count());
        foreach ($eloquentSuppliers as $i => $eSupplier) {
            $this->assertModelParity($eSupplier, $immutableSuppliers[$i], "Supplier {$i} parity failed");

            $eUsers = $eSupplier->users->sortBy('id')->values();
            $iUsers = $immutableSuppliers[$i]->users->sortBy('id')->values();
            $this->assertEquals($eUsers->count(), $iUsers->count());

            foreach ($eUsers as $j => $eUser) {
                $this->assertModelParity($eUser, $iUsers[$j], "User {$j} parity failed");
                $this->assertCollectionParity(
                    $eUser->orders->sortBy('id')->values(),
                    $iUsers[$j]->orders->sortBy('id')->values()
                );
            }
        }
    }

    // =========================================================================
    // MIXED EAGER/LAZY LOADING
    // =========================================================================

    public function test_lazy_load_after_eager_load(): void
    {
        $eloquent = EloquentUser::with('orders')->find(1);
        $immutable = ImmutableUser::with('orders')->find(1);

        // Verify user and eager loaded orders
        $this->assertModelParity($eloquent, $immutable, 'User parity failed');
        $this->assertCollectionParity(
            $eloquent->orders->sortBy('id')->values(),
            $immutable->orders->sortBy('id')->values()
        );

        // Now lazy load items on the first order
        $eloquentItems = $eloquent->orders->first()->items->sortBy('id')->values();
        $immutableItems = $immutable->orders->first()->items->sortBy('id')->values();

        $this->assertCollectionParity($eloquentItems, $immutableItems);
    }

    // =========================================================================
    // EMPTY NESTED RELATIONS
    // =========================================================================

    public function test_empty_nested_relations(): void
    {
        // User 3 (Bob Wilson) has no orders
        $eloquent = EloquentUser::with('orders.items')->find(3);
        $immutable = ImmutableUser::with('orders.items')->find(3);

        // Verify user
        $this->assertModelParity($eloquent, $immutable, 'User parity failed');

        // Verify empty or populated orders
        $this->assertCollectionParity(
            $eloquent->orders->sortBy('id')->values(),
            $immutable->orders->sortBy('id')->values()
        );
    }

    public function test_deep_nesting_with_no_records(): void
    {
        // User 1 has a supplier with a country
        $eloquent = EloquentUser::with('supplier.country')->find(1);
        $immutable = ImmutableUser::with('supplier.country')->find(1);

        // Verify each level
        $this->assertModelParity($eloquent, $immutable, 'User parity failed');

        if ($eloquent->supplier !== null) {
            $this->assertModelParity($eloquent->supplier, $immutable->supplier, 'Supplier parity failed');

            if ($eloquent->supplier->country !== null) {
                $this->assertModelParity($eloquent->supplier->country, $immutable->supplier->country, 'Country parity failed');
            } else {
                $this->assertNull($immutable->supplier->country);
            }
        } else {
            $this->assertNull($immutable->supplier);
        }
    }
}
