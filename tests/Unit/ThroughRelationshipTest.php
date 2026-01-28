<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableCountry;
use Brighten\ImmutableModel\Tests\Models\ImmutableSupplier;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ThroughRelationshipTest extends TestCase
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

        // Create suppliers
        DB::table('suppliers')->insert([
            ['id' => 1, 'country_id' => 1, 'name' => 'Supplier A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'country_id' => 1, 'name' => 'Supplier B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create users linked to suppliers
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'User 1', 'email' => 'user1@example.com', 'supplier_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'User 2', 'email' => 'user2@example.com', 'supplier_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'User 3', 'email' => 'user3@example.com', 'supplier_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->country = ImmutableCountry::find(1);
    }

    public function test_has_many_through_lazy_load(): void
    {
        $users = $this->country->users;

        $this->assertInstanceOf(ImmutableCollection::class, $users);
        $this->assertCount(3, $users);
        $this->assertInstanceOf(ImmutableUser::class, $users->first());
    }

    public function test_has_one_through_lazy_load(): void
    {
        $user = $this->country->firstUser;

        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('User 1', $user->name);
    }

    public function test_has_many_through_eager_load(): void
    {
        $countries = ImmutableCountry::with('users')->get();

        $this->assertCount(1, $countries);
        $this->assertTrue($countries->first()->relationLoaded('users'));
        $this->assertCount(3, $countries->first()->users);
    }

    public function test_has_one_through_eager_load(): void
    {
        $countries = ImmutableCountry::with('firstUser')->get();

        $this->assertCount(1, $countries);
        $this->assertTrue($countries->first()->relationLoaded('firstUser'));
        $this->assertInstanceOf(ImmutableUser::class, $countries->first()->firstUser);
    }

    public function test_has_many_through_with_constraints(): void
    {
        $countries = ImmutableCountry::with(['users' => fn($q) => $q->where('users.name', 'User 1')])->get();

        $this->assertCount(1, $countries->first()->users);
        $this->assertEquals('User 1', $countries->first()->users->first()->name);
    }

    public function test_has_many_through_returns_empty_collection_when_no_relations(): void
    {
        // Create a country with no suppliers
        DB::table('countries')->insert([
            'id' => 2,
            'name' => 'Empty Country',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $country = ImmutableCountry::find(2);
        $users = $country->users;

        $this->assertInstanceOf(ImmutableCollection::class, $users);
        $this->assertCount(0, $users);
    }

    public function test_has_one_through_returns_null_when_no_relation(): void
    {
        // Create a country with no suppliers
        DB::table('countries')->insert([
            'id' => 3,
            'name' => 'Another Empty Country',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $country = ImmutableCountry::find(3);
        $user = $country->firstUser;

        $this->assertNull($user);
    }

    public function test_has_many_through_relation_method_returns_builder(): void
    {
        $count = $this->country->users()->count();

        $this->assertEquals(3, $count);
    }

    public function test_has_many_through_blocks_create(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $this->country->users()->create(['name' => 'New User']);
    }

    public function test_has_one_through_blocks_save(): void
    {
        $this->expectException(ImmutableModelViolationException::class);

        $user = new ImmutableUser();
        $this->country->firstUser()->save($user);
    }

    public function test_has_many_through_respects_soft_deletes_on_intermediate(): void
    {
        // Soft delete a supplier
        DB::table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        // Reload the country
        $country = ImmutableCountry::find(1);
        $users = $country->users;

        // Should only see user from non-deleted supplier
        $this->assertCount(1, $users);
        $this->assertEquals('User 3', $users->first()->name);
    }

    public function test_has_many_through_with_trashed_parents(): void
    {
        // Soft delete a supplier
        DB::table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        // Create a custom model that includes trashed
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
        $this->assertCount(3, $users);
    }

    public function test_has_one_through_respects_soft_deletes_on_intermediate(): void
    {
        // Soft delete the first supplier (which has User 1)
        DB::table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        // Reload the country
        $country = ImmutableCountry::find(1);
        $user = $country->firstUser;

        // Should get user from the non-deleted supplier (User 3 from Supplier B)
        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('User 3', $user->name);
    }

    public function test_has_one_through_with_trashed_parents(): void
    {
        // Soft delete the first supplier (which has User 1)
        DB::table('suppliers')->where('id', 1)->update(['deleted_at' => now()]);

        // Create a custom model that includes trashed
        $country = new class extends ImmutableCountry {
            public function firstUserWithTrashed()
            {
                return $this->hasOneThrough(
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
        $user = $country->firstUserWithTrashed;

        // Should get User 1 even though supplier is deleted
        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('User 1', $user->name);
    }

    public function test_suppliers_have_users(): void
    {
        $supplier = ImmutableSupplier::find(1);
        $users = $supplier->users;

        $this->assertCount(2, $users);
    }

    public function test_supplier_belongs_to_country(): void
    {
        $supplier = ImmutableSupplier::find(1);
        $country = $supplier->country;

        $this->assertInstanceOf(ImmutableCountry::class, $country);
        $this->assertEquals('United States', $country->name);
    }
}
