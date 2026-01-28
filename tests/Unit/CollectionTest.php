<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Collection;

class CollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-02 00:00:00', 'updated_at' => '2024-01-02 00:00:00'],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'settings' => null, 'email_verified_at' => null, 'created_at' => '2024-01-03 00:00:00', 'updated_at' => '2024-01-03 00:00:00'],
        ]);
    }

    // =========================================================================
    // BASIC OPERATIONS
    // =========================================================================

    public function test_count(): void
    {
        $users = ImmutableUser::all();

        $this->assertCount(3, $users);
        $this->assertEquals(3, $users->count());
    }

    public function test_is_empty(): void
    {
        $users = ImmutableUser::all();
        $empty = ImmutableUser::where('id', 999)->get();

        $this->assertFalse($users->isEmpty());
        $this->assertTrue($empty->isEmpty());
    }

    public function test_is_not_empty(): void
    {
        $users = ImmutableUser::all();

        $this->assertTrue($users->isNotEmpty());
    }

    public function test_all(): void
    {
        $users = ImmutableUser::all();

        $this->assertIsArray($users->all());
        $this->assertCount(3, $users->all());
    }

    public function test_first(): void
    {
        $users = ImmutableUser::query()->orderBy('id')->get();

        $this->assertEquals('Alice', $users->first()->name);
    }

    public function test_last(): void
    {
        $users = ImmutableUser::query()->orderBy('id')->get();

        $this->assertEquals('Charlie', $users->last()->name);
    }

    public function test_get(): void
    {
        $users = ImmutableUser::query()->orderBy('id')->get();

        $this->assertEquals('Bob', $users->get(1)->name);
        $this->assertNull($users->get(999));
    }

    // =========================================================================
    // FILTERING (returns ImmutableCollection)
    // =========================================================================

    public function test_filter(): void
    {
        $users = ImmutableUser::all();
        $filtered = $users->filter(fn($u) => $u->id > 1);

        $this->assertInstanceOf(ImmutableCollection::class, $filtered);
        $this->assertCount(2, $filtered);
    }

    public function test_reject(): void
    {
        $users = ImmutableUser::all();
        $rejected = $users->reject(fn($u) => $u->id === 1);

        $this->assertInstanceOf(ImmutableCollection::class, $rejected);
        $this->assertCount(2, $rejected);
    }

    public function test_where(): void
    {
        $users = ImmutableUser::all();
        // Use explicit equality operator for where
        $filtered = $users->where('name', '=', 'Alice');

        $this->assertInstanceOf(ImmutableCollection::class, $filtered);
        $this->assertCount(1, $filtered);
    }

    public function test_where_in(): void
    {
        $users = ImmutableUser::all();
        $filtered = $users->whereIn('id', [1, 2]);

        $this->assertInstanceOf(ImmutableCollection::class, $filtered);
        $this->assertCount(2, $filtered);
    }

    public function test_where_not_in(): void
    {
        $users = ImmutableUser::all();
        $filtered = $users->whereNotIn('id', [1, 2]);

        $this->assertInstanceOf(ImmutableCollection::class, $filtered);
        $this->assertCount(1, $filtered);
    }

    public function test_take(): void
    {
        $users = ImmutableUser::all();
        $taken = $users->take(2);

        $this->assertInstanceOf(ImmutableCollection::class, $taken);
        $this->assertCount(2, $taken);
    }

    public function test_skip(): void
    {
        $users = ImmutableUser::query()->orderBy('id')->get();
        $skipped = $users->skip(1);

        $this->assertInstanceOf(ImmutableCollection::class, $skipped);
        $this->assertCount(2, $skipped);
        $this->assertEquals('Bob', $skipped->first()->name);
    }

    public function test_unique(): void
    {
        $users = ImmutableUser::all();
        $unique = $users->unique('name');

        $this->assertInstanceOf(ImmutableCollection::class, $unique);
        $this->assertCount(3, $unique);
    }

    public function test_sort_by(): void
    {
        $users = ImmutableUser::all();
        $sorted = $users->sortBy('name');

        $this->assertInstanceOf(ImmutableCollection::class, $sorted);
        $this->assertEquals('Alice', $sorted->first()->name);
    }

    public function test_sort_by_desc(): void
    {
        $users = ImmutableUser::all();
        $sorted = $users->sortByDesc('name');

        $this->assertInstanceOf(ImmutableCollection::class, $sorted);
        $this->assertEquals('Charlie', $sorted->first()->name);
    }

    public function test_reverse(): void
    {
        $users = ImmutableUser::query()->orderBy('id')->get();
        $reversed = $users->reverse();

        $this->assertInstanceOf(ImmutableCollection::class, $reversed);
        $this->assertEquals('Charlie', $reversed->first()->name);
    }

    public function test_values(): void
    {
        $users = ImmutableUser::all();
        $values = $users->values();

        $this->assertInstanceOf(ImmutableCollection::class, $values);
    }

    // =========================================================================
    // TRANSFORMATIONS (returns base Collection)
    // =========================================================================

    public function test_map_returns_base_collection(): void
    {
        $users = ImmutableUser::all();
        $names = $users->map(fn($u) => $u->name);

        $this->assertInstanceOf(Collection::class, $names);
        $this->assertNotInstanceOf(ImmutableCollection::class, $names);
    }

    public function test_pluck_returns_base_collection(): void
    {
        $users = ImmutableUser::all();
        $names = $users->pluck('name');

        $this->assertInstanceOf(Collection::class, $names);
        $this->assertNotInstanceOf(ImmutableCollection::class, $names);
    }

    public function test_keys_returns_base_collection(): void
    {
        $users = ImmutableUser::all();
        $keys = $users->keys();

        $this->assertInstanceOf(Collection::class, $keys);
    }

    public function test_flat_map_returns_base_collection(): void
    {
        $users = ImmutableUser::all();
        $result = $users->flatMap(fn($u) => [$u->name]);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_group_by_returns_base_collection(): void
    {
        $users = ImmutableUser::all();
        $grouped = $users->groupBy('name');

        $this->assertInstanceOf(Collection::class, $grouped);
    }

    public function test_key_by_returns_base_collection(): void
    {
        $users = ImmutableUser::all();
        $keyed = $users->keyBy('id');

        $this->assertInstanceOf(Collection::class, $keyed);
    }

    // =========================================================================
    // AGGREGATES
    // =========================================================================

    public function test_sum(): void
    {
        $users = ImmutableUser::all();

        $this->assertEquals(6, $users->sum('id'));
    }

    public function test_avg(): void
    {
        $users = ImmutableUser::all();

        $this->assertEquals(2, $users->avg('id'));
    }

    public function test_min(): void
    {
        $users = ImmutableUser::all();

        $this->assertEquals(1, $users->min('id'));
    }

    public function test_max(): void
    {
        $users = ImmutableUser::all();

        $this->assertEquals(3, $users->max('id'));
    }

    public function test_contains(): void
    {
        $users = ImmutableUser::all();

        // Use explicit equality operator
        $this->assertTrue($users->contains('name', '=', 'Alice'));
        $this->assertFalse($users->contains('name', '=', 'Unknown'));
    }

    public function test_every(): void
    {
        $users = ImmutableUser::all();

        $this->assertTrue($users->every(fn($u) => $u->id > 0));
        $this->assertFalse($users->every(fn($u) => $u->id > 1));
    }

    // =========================================================================
    // ITERATION
    // =========================================================================

    public function test_each(): void
    {
        $users = ImmutableUser::all();
        $names = [];

        $result = $users->each(function ($user) use (&$names) {
            $names[] = $user->name;
        });

        $this->assertSame($users, $result);
        $this->assertCount(3, $names);
    }

    public function test_reduce(): void
    {
        $users = ImmutableUser::all();

        $total = $users->reduce(fn($carry, $user) => $carry + $user->id, 0);

        $this->assertEquals(6, $total);
    }

    public function test_iteration(): void
    {
        $users = ImmutableUser::all();
        $count = 0;

        foreach ($users as $user) {
            $count++;
            $this->assertInstanceOf(ImmutableUser::class, $user);
        }

        $this->assertEquals(3, $count);
    }

    // =========================================================================
    // ARRAY ACCESS (read-only)
    // =========================================================================

    public function test_array_access_read(): void
    {
        $users = ImmutableUser::query()->orderBy('id')->get();

        $this->assertEquals('Alice', $users[0]->name);
        $this->assertTrue(isset($users[0]));
        $this->assertFalse(isset($users[999]));
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    public function test_to_array(): void
    {
        $users = ImmutableUser::all();
        $array = $users->toArray();

        $this->assertIsArray($array);
        $this->assertCount(3, $array);
        $this->assertIsArray($array[0]);
    }

    public function test_to_json(): void
    {
        $users = ImmutableUser::all();
        $json = $users->toJson();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertCount(3, $decoded);
    }

    public function test_json_serialize(): void
    {
        $users = ImmutableUser::all();
        $json = json_encode($users);

        $this->assertJson($json);
    }

    // =========================================================================
    // TO BASE
    // =========================================================================

    public function test_to_base_returns_mutable_collection(): void
    {
        $users = ImmutableUser::all();
        $mutable = $users->toBase();

        $this->assertInstanceOf(Collection::class, $mutable);
        $this->assertNotInstanceOf(ImmutableCollection::class, $mutable);

        // Mutable collection allows modifications
        $mutable->push(ImmutableUser::find(1));
        $this->assertCount(4, $mutable);
    }
}
