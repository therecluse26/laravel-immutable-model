<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;

/**
 * Tests that ImmutableCollection operations match Eloquent Collection behavior.
 */
class CollectionParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // BASIC OPERATIONS
    // =========================================================================

    public function test_count(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_is_empty_false(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertFalse($eloquent->isEmpty());
        $this->assertFalse($immutable->isEmpty());
    }

    public function test_is_empty_true(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->get();
        $immutable = ImmutableUser::where('id', 9999)->get();

        $this->assertTrue($eloquent->isEmpty());
        $this->assertTrue($immutable->isEmpty());
    }

    public function test_is_not_empty(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertTrue($eloquent->isNotEmpty());
        $this->assertTrue($immutable->isNotEmpty());
    }

    // =========================================================================
    // ACCESSING ITEMS
    // =========================================================================

    public function test_first(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->first();
        $immutable = ImmutableUser::query()->orderBy('id')->get()->first();

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_first_with_callback(): void
    {
        $eloquent = EloquentUser::all()->first(fn($u) => $u->name === 'Bob');
        $immutable = ImmutableUser::all()->first(fn($u) => $u->name === 'Bob');

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_first_returns_null_when_empty(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->get()->first();
        $immutable = ImmutableUser::where('id', 9999)->get()->first();

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_last(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->last();
        $immutable = ImmutableUser::query()->orderBy('id')->get()->last();

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_get_by_index(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->get(0);
        $immutable = ImmutableUser::query()->orderBy('id')->get()->get(0);

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_all(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->all();
        $immutable = ImmutableUser::query()->orderBy('id')->get()->all();

        $this->assertCount(count($eloquent), $immutable);
    }

    // =========================================================================
    // FILTERING
    // =========================================================================

    public function test_filter(): void
    {
        $eloquent = EloquentUser::all()->filter(fn($u) => $u->id > 1)->values();
        $immutable = ImmutableUser::all()->filter(fn($u) => $u->id > 1)->values();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_reject(): void
    {
        $eloquent = EloquentUser::all()->reject(fn($u) => $u->name === 'Alice')->values();
        $immutable = ImmutableUser::all()->reject(fn($u) => $u->name === 'Alice')->values();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_where(): void
    {
        $eloquent = EloquentUser::all()->where('name', 'Alice');
        $immutable = ImmutableUser::all()->where('name', 'Alice');

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_where_in(): void
    {
        $eloquent = EloquentUser::all()->whereIn('id', [1, 2]);
        $immutable = ImmutableUser::all()->whereIn('id', [1, 2]);

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_where_not_in(): void
    {
        $eloquent = EloquentUser::all()->whereNotIn('id', [1]);
        $immutable = ImmutableUser::all()->whereNotIn('id', [1]);

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_where_strict(): void
    {
        $eloquent = EloquentUser::all()->whereStrict('id', 1);
        $immutable = ImmutableUser::all()->whereStrict('id', 1);

        $this->assertEquals($eloquent->count(), $immutable->count());
        if ($eloquent->count() > 0) {
            $this->assertEquals(
                $eloquent->first()->name,
                $immutable->first()->name
            );
        }
    }

    public function test_where_null(): void
    {
        $eloquent = EloquentUser::all()->whereNull('email_verified_at');
        $immutable = ImmutableUser::all()->whereNull('email_verified_at');

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_where_not_null(): void
    {
        $eloquent = EloquentUser::all()->whereNotNull('email_verified_at');
        $immutable = ImmutableUser::all()->whereNotNull('email_verified_at');

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    // =========================================================================
    // TRANSFORMATION
    // =========================================================================

    public function test_map(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->map(fn($u) => $u->name);
        $immutable = ImmutableUser::query()->orderBy('id')->get()->map(fn($u) => $u->name);

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_pluck(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->pluck('name');
        $immutable = ImmutableUser::query()->orderBy('id')->get()->pluck('name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_pluck_with_key(): void
    {
        $eloquent = EloquentUser::all()->pluck('name', 'id');
        $immutable = ImmutableUser::all()->pluck('name', 'id');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_keys(): void
    {
        $eloquent = EloquentUser::all()->keys();
        $immutable = ImmutableUser::all()->keys();

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_values(): void
    {
        $eloquent = EloquentUser::all()->filter(fn($u) => $u->id > 1)->values();
        $immutable = ImmutableUser::all()->filter(fn($u) => $u->id > 1)->values();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    // =========================================================================
    // AGGREGATES
    // =========================================================================

    public function test_sum(): void
    {
        $eloquent = EloquentUser::all()->sum('id');
        $immutable = ImmutableUser::all()->sum('id');

        $this->assertEquals($eloquent, $immutable);
    }

    public function test_avg(): void
    {
        $eloquent = EloquentUser::all()->avg('id');
        $immutable = ImmutableUser::all()->avg('id');

        $this->assertEquals($eloquent, $immutable);
    }

    public function test_min(): void
    {
        $eloquent = EloquentUser::all()->min('id');
        $immutable = ImmutableUser::all()->min('id');

        $this->assertEquals($eloquent, $immutable);
    }

    public function test_max(): void
    {
        $eloquent = EloquentUser::all()->max('id');
        $immutable = ImmutableUser::all()->max('id');

        $this->assertEquals($eloquent, $immutable);
    }

    // =========================================================================
    // SORTING
    // =========================================================================

    public function test_sort_by(): void
    {
        $eloquent = EloquentUser::all()->sortBy('name')->values()->pluck('name');
        $immutable = ImmutableUser::all()->sortBy('name')->values()->pluck('name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_sort_by_desc(): void
    {
        $eloquent = EloquentUser::all()->sortByDesc('name')->values()->pluck('name');
        $immutable = ImmutableUser::all()->sortByDesc('name')->values()->pluck('name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_reverse(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->reverse()->values()->pluck('id');
        $immutable = ImmutableUser::query()->orderBy('id')->get()->reverse()->values()->pluck('id');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    // =========================================================================
    // SLICING
    // =========================================================================

    public function test_take(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->take(2);
        $immutable = ImmutableUser::query()->orderBy('id')->get()->take(2);

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_skip(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->skip(1)->values();
        $immutable = ImmutableUser::query()->orderBy('id')->get()->skip(1)->values();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_slice(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->slice(1, 2);
        $immutable = ImmutableUser::query()->orderBy('id')->get()->slice(1, 2);

        $this->assertEquals($eloquent->count(), $immutable->count());
        // Compare values
        $eloquentNames = $eloquent->pluck('name')->toArray();
        $immutableNames = $immutable->pluck('name')->toArray();
        $this->assertEquals($eloquentNames, $immutableNames);
    }

    public function test_sort(): void
    {
        $eloquent = EloquentUser::all()->sort(fn($a, $b) => $b->id <=> $a->id)->values();
        $immutable = ImmutableUser::all()->sort(fn($a, $b) => $b->id <=> $a->id)->values();

        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertEquals(
            $eloquent->first()->name,
            $immutable->first()->name
        );
    }

    public function test_flat_map(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->flatMap(fn($u) => [$u->name, $u->email]);
        $immutable = ImmutableUser::query()->orderBy('id')->get()->flatMap(fn($u) => [$u->name, $u->email]);

        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }

    public function test_unique(): void
    {
        // Add duplicate name for testing
        $this->app['db']->table('users')->insert([
            'name' => 'Alice',
            'email' => 'alice2@example.com',
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eloquent = EloquentUser::all()->unique('name');
        $immutable = ImmutableUser::all()->unique('name');

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    // =========================================================================
    // GROUPING
    // =========================================================================

    public function test_group_by(): void
    {
        $eloquent = EloquentUser::all()->groupBy('name');
        $immutable = ImmutableUser::all()->groupBy('name');

        $this->assertEquals($eloquent->keys()->sort()->values()->toArray(), $immutable->keys()->sort()->values()->toArray());
    }

    public function test_key_by(): void
    {
        $eloquent = EloquentUser::all()->keyBy('id');
        $immutable = ImmutableUser::all()->keyBy('id');

        $this->assertEquals($eloquent->keys()->toArray(), $immutable->keys()->toArray());
    }

    // =========================================================================
    // TESTING
    // =========================================================================

    public function test_contains(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertEquals(
            $eloquent->contains('name', 'Alice'),
            $immutable->contains('name', 'Alice')
        );

        $this->assertEquals(
            $eloquent->contains('name', 'NonExistent'),
            $immutable->contains('name', 'NonExistent')
        );
    }

    public function test_every(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertEquals(
            $eloquent->every(fn($u) => $u->id > 0),
            $immutable->every(fn($u) => $u->id > 0)
        );
    }

    // =========================================================================
    // ITERATION
    // =========================================================================

    public function test_each(): void
    {
        $eloquentNames = [];
        $immutableNames = [];

        EloquentUser::orderBy('id')->get()->each(function ($u) use (&$eloquentNames) {
            $eloquentNames[] = $u->name;
        });

        ImmutableUser::query()->orderBy('id')->get()->each(function ($u) use (&$immutableNames) {
            $immutableNames[] = $u->name;
        });

        $this->assertEquals($eloquentNames, $immutableNames);
    }

    public function test_reduce(): void
    {
        $eloquent = EloquentUser::all()->reduce(fn($carry, $u) => $carry + $u->id, 0);
        $immutable = ImmutableUser::all()->reduce(fn($carry, $u) => $carry + $u->id, 0);

        $this->assertEquals($eloquent, $immutable);
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    public function test_to_array(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get()->toArray();
        $immutable = ImmutableUser::query()->orderBy('id')->get()->toArray();

        $this->assertEquals($eloquent, $immutable);
    }

    public function test_to_json(): void
    {
        $eloquent = json_decode(EloquentUser::orderBy('id')->get()->toJson(), true);
        $immutable = json_decode(ImmutableUser::query()->orderBy('id')->get()->toJson(), true);

        $this->assertEquals($eloquent, $immutable);
    }

    // =========================================================================
    // ARRAY ACCESS
    // =========================================================================

    public function test_array_access_get(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get();
        $immutable = ImmutableUser::query()->orderBy('id')->get();

        $this->assertEquals($eloquent[0]->toArray(), $immutable[0]->toArray());
    }

    public function test_array_access_isset(): void
    {
        $eloquent = EloquentUser::orderBy('id')->get();
        $immutable = ImmutableUser::query()->orderBy('id')->get();

        $this->assertEquals(isset($eloquent[0]), isset($immutable[0]));
        $this->assertEquals(isset($eloquent[999]), isset($immutable[999]));
    }

    // =========================================================================
    // TO BASE
    // =========================================================================

    public function test_to_base_returns_mutable_collection(): void
    {
        $immutable = ImmutableUser::all();
        $base = $immutable->toBase();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $base);
        $this->assertNotInstanceOf(ImmutableCollection::class, $base);
    }
}
