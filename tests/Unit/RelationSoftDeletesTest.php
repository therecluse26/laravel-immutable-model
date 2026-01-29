<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableCountry;
use Brighten\ImmutableModel\Tests\Models\ImmutableSupplier;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Tests for soft delete handling in relationships.
 *
 * Tests that soft-deleted intermediate models are properly excluded
 * from through relationships, and withTrashedParents() works correctly.
 */
class RelationSoftDeletesTest extends TestCase
{
    private ImmutableCountry $country;

    protected function setUp(): void
    {
        parent::setUp();

        // Create country
        DB::table('countries')->insert([
            'id' => 1,
            'name' => 'United States',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create suppliers (with soft delete support)
        DB::table('suppliers')->insert([
            ['id' => 1, 'country_id' => 1, 'name' => 'Active Supplier', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'country_id' => 1, 'name' => 'Deleted Supplier', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create users linked to suppliers
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'User from Active', 'email' => 'active@example.com', 'supplier_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'User from Deleted', 'email' => 'deleted@example.com', 'supplier_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->country = ImmutableCountry::find(1);
    }

    // =========================================================================
    // HasManyThrough Soft Delete Tests
    // =========================================================================

    public function test_has_many_through_excludes_users_from_soft_deleted_suppliers(): void
    {
        $users = $this->country->users;

        // Should only see user from active supplier
        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(1, $users);
        $this->assertEquals('User from Active', $users->first()->name);
    }

    public function test_has_many_through_count_excludes_soft_deleted(): void
    {
        $count = $this->country->users()->count();

        $this->assertEquals(1, $count);
    }

    public function test_has_many_through_with_trashed_parents_includes_all(): void
    {
        // Create a custom model that uses withTrashedParents
        $country = new class extends ImmutableCountry {
            public function usersWithTrashed()
            {
                return $this->hasManyThrough(
                    ImmutableUser::class,
                    ImmutableSupplier::class,
                    'country_id',
                    'supplier_id',
                    'id',
                    'id'
                )->withTrashedParents();
            }
        };

        $country = $country::find(1);
        $users = $country->usersWithTrashed;

        // Should see all users including those from deleted supplier
        $this->assertCount(2, $users);
    }

    public function test_has_one_through_excludes_soft_deleted_intermediate(): void
    {
        $firstUser = $this->country->firstUser;

        // Should only see user from active supplier
        $this->assertInstanceOf(ImmutableUser::class, $firstUser);
        $this->assertEquals('User from Active', $firstUser->name);
    }

    // =========================================================================
    // Supplier Direct Relation Tests (with soft deletes)
    // =========================================================================

    public function test_supplier_with_deleted_at_has_users(): void
    {
        $supplier = ImmutableSupplier::find(1);
        $users = $supplier->users;

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(1, $users);
    }

    public function test_supplier_country_relation_works(): void
    {
        $supplier = ImmutableSupplier::find(1);
        $country = $supplier->country;

        $this->assertInstanceOf(ImmutableCountry::class, $country);
        $this->assertEquals('United States', $country->name);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function test_has_many_through_returns_empty_when_all_intermediates_deleted(): void
    {
        // Soft delete all suppliers
        DB::table('suppliers')->update(['deleted_at' => now()]);

        // Reload country
        $country = ImmutableCountry::find(1);
        $users = $country->users;

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(0, $users);
    }

    public function test_has_one_through_returns_null_when_all_intermediates_deleted(): void
    {
        // Soft delete all suppliers
        DB::table('suppliers')->update(['deleted_at' => now()]);

        // Reload country
        $country = ImmutableCountry::find(1);
        $firstUser = $country->firstUser;

        $this->assertNull($firstUser);
    }

    public function test_soft_deleted_intermediate_respects_eager_loading(): void
    {
        $countries = ImmutableCountry::with('users')->get();
        $country = $countries->first();

        // Should only have user from active supplier
        $this->assertTrue($country->relationLoaded('users'));
        $this->assertCount(1, $country->users);
    }

    public function test_soft_deleted_intermediate_respects_constraints(): void
    {
        $countries = ImmutableCountry::with(['users' => function ($q) {
            $q->where('users.name', 'like', 'User%');
        }])->get();

        $country = $countries->first();

        // Should only have user from active supplier matching constraint
        $this->assertCount(1, $country->users);
        $this->assertEquals('User from Active', $country->users->first()->name);
    }

    // =========================================================================
    // Multiple Countries with Mixed Soft Deleted Intermediates
    // =========================================================================

    public function test_multiple_countries_with_mixed_soft_deleted_suppliers(): void
    {
        // Create second country with all active suppliers
        DB::table('countries')->insert([
            'id' => 2,
            'name' => 'Canada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('suppliers')->insert([
            ['id' => 3, 'country_id' => 2, 'name' => 'Canadian Supplier', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('users')->insert([
            ['id' => 3, 'name' => 'Canadian User', 'email' => 'canadian@example.com', 'supplier_id' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $countries = ImmutableCountry::with('users')->get();

        $usa = $countries->firstWhere('name', 'United States');
        $canada = $countries->firstWhere('name', 'Canada');

        $this->assertCount(1, $usa->users); // Only active supplier's user
        $this->assertCount(1, $canada->users); // All suppliers active
    }
}
