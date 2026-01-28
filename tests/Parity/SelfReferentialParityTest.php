<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentCategory;
use Brighten\ImmutableModel\Tests\Models\ImmutableCategory;

/**
 * Tests that self-referential relationships behave identically in both models.
 */
class SelfReferentialParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCategoryData();
    }

    protected function seedCategoryData(): void
    {
        // Root categories
        $this->app['db']->table('immutable_categories')->insert([
            ['id' => 1, 'parent_id' => null, 'name' => 'Electronics', 'slug' => 'electronics', 'depth' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'parent_id' => null, 'name' => 'Clothing', 'slug' => 'clothing', 'depth' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Second level
        $this->app['db']->table('immutable_categories')->insert([
            ['id' => 3, 'parent_id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'depth' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'parent_id' => 1, 'name' => 'Laptops', 'slug' => 'laptops', 'depth' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'parent_id' => 2, 'name' => 'Shirts', 'slug' => 'shirts', 'depth' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Third level
        $this->app['db']->table('immutable_categories')->insert([
            ['id' => 6, 'parent_id' => 3, 'name' => 'Smartphones', 'slug' => 'smartphones', 'depth' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'parent_id' => 3, 'name' => 'Feature Phones', 'slug' => 'feature-phones', 'depth' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // =========================================================================
    // PARENT RELATION
    // =========================================================================

    public function test_parent_lazy_load(): void
    {
        $eloquent = EloquentCategory::find(3);
        $immutable = ImmutableCategory::find(3);

        $this->assertModelParity(
            $eloquent->parent,
            $immutable->parent,
            'Parent lazy load differs'
        );
    }

    public function test_parent_eager_load(): void
    {
        $eloquent = EloquentCategory::with('parent')->find(3);
        $immutable = ImmutableCategory::with('parent')->find(3);

        $this->assertModelParity(
            $eloquent->parent,
            $immutable->parent,
            'Parent eager load differs'
        );
    }

    public function test_root_category_parent_is_null(): void
    {
        $eloquent = EloquentCategory::find(1);
        $immutable = ImmutableCategory::find(1);

        $this->assertNull($eloquent->parent);
        $this->assertNull($immutable->parent);
    }

    // =========================================================================
    // CHILDREN RELATION
    // =========================================================================

    public function test_children_lazy_load(): void
    {
        $eloquent = EloquentCategory::find(1);
        $immutable = ImmutableCategory::find(1);

        $eloquentChildren = $eloquent->children->sortBy('id')->values();
        $immutableChildren = $immutable->children->sortBy('id')->values();

        $this->assertEquals($eloquentChildren->count(), $immutableChildren->count());
    }

    public function test_children_eager_load(): void
    {
        $eloquent = EloquentCategory::with('children')->find(1);
        $immutable = ImmutableCategory::with('children')->find(1);

        $this->assertEquals(
            $eloquent->children->count(),
            $immutable->children->count()
        );
    }

    public function test_leaf_category_children_empty(): void
    {
        // Category 6 (Smartphones) has no children
        $eloquent = EloquentCategory::find(6);
        $immutable = ImmutableCategory::find(6);

        $this->assertTrue($eloquent->children->isEmpty());
        $this->assertTrue($immutable->children->isEmpty());
    }

    // =========================================================================
    // NESTED EAGER LOADING
    // =========================================================================

    public function test_nested_parent_eager_load(): void
    {
        // Load Smartphones (6) with parent (Phones 3) and grandparent (Electronics 1)
        $eloquent = EloquentCategory::with('parent.parent')->find(6);
        $immutable = ImmutableCategory::with('parent.parent')->find(6);

        // Parent should be Phones
        $this->assertEquals(
            $eloquent->parent->name,
            $immutable->parent->name
        );

        // Grandparent should be Electronics
        $this->assertEquals(
            $eloquent->parent->parent->name,
            $immutable->parent->parent->name
        );
    }

    public function test_nested_children_eager_load(): void
    {
        // Load Electronics (1) with children and grandchildren
        $eloquent = EloquentCategory::with('children.children')->find(1);
        $immutable = ImmutableCategory::with('children.children')->find(1);

        // Should have children (Phones, Laptops)
        $this->assertEquals(
            $eloquent->children->count(),
            $immutable->children->count()
        );

        // Phones should have grandchildren
        $eloquentPhones = $eloquent->children->firstWhere('name', 'Phones');
        $immutablePhones = $immutable->children->first(fn($c) => $c->name === 'Phones');

        if ($eloquentPhones && $immutablePhones) {
            $this->assertEquals(
                $eloquentPhones->children->count(),
                $immutablePhones->children->count()
            );
        }
    }

    // =========================================================================
    // QUERYING
    // =========================================================================

    public function test_query_root_categories(): void
    {
        $eloquent = EloquentCategory::whereNull('parent_id')->orderBy('id')->get();
        $immutable = ImmutableCategory::whereNull('parent_id')->orderBy('id')->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_query_by_depth(): void
    {
        $eloquent = EloquentCategory::where('depth', 1)->orderBy('id')->get();
        $immutable = ImmutableCategory::where('depth', 1)->orderBy('id')->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }
}
