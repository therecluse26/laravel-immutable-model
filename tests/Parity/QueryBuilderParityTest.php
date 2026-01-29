<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Tests that ImmutableModel query builder behavior matches Eloquent exactly.
 *
 * Every test runs the same query on both Eloquent and ImmutableModel,
 * then asserts the results are identical.
 */
class QueryBuilderParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // BASIC QUERY METHODS
    // =========================================================================

    public function test_all_returns_same_data(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::all(),
            fn() => ImmutableUser::all(),
            'all() results differ'
        );
    }

    public function test_get_returns_same_data(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()->orderBy('id')->get(),
            fn() => ImmutableUser::query()->orderBy('id')->get(),
            'get() results differ'
        );
    }

    public function test_find_returns_same_model(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::find(1),
            fn() => ImmutableUser::find(1),
            'find() results differ'
        );
    }

    public function test_find_returns_null_for_missing(): void
    {
        $eloquent = EloquentUser::find(9999);
        $immutable = ImmutableUser::find(9999);

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_find_or_fail_returns_same_model(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::findOrFail(1),
            fn() => ImmutableUser::findOrFail(1),
            'findOrFail() results differ'
        );
    }

    public function test_find_or_fail_throws_same_exception(): void
    {
        $eloquentException = null;
        $immutableException = null;

        try {
            EloquentUser::findOrFail(9999);
        } catch (\Exception $e) {
            $eloquentException = $e;
        }

        try {
            ImmutableUser::findOrFail(9999);
        } catch (\Exception $e) {
            $immutableException = $e;
        }

        $this->assertInstanceOf(ModelNotFoundException::class, $eloquentException);
        $this->assertInstanceOf(ModelNotFoundException::class, $immutableException);
    }

    public function test_first_returns_same_model(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()->orderBy('id')->first(),
            fn() => ImmutableUser::query()->orderBy('id')->first(),
            'first() results differ'
        );
    }

    public function test_first_returns_null_for_empty(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->first();
        $immutable = ImmutableUser::where('id', 9999)->first();

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_first_or_fail_returns_same_model(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()->orderBy('id')->firstOrFail(),
            fn() => ImmutableUser::query()->orderBy('id')->firstOrFail(),
            'firstOrFail() results differ'
        );
    }

    public function test_first_or_fail_throws_same_exception(): void
    {
        $eloquentException = null;
        $immutableException = null;

        try {
            EloquentUser::where('id', 9999)->firstOrFail();
        } catch (\Exception $e) {
            $eloquentException = $e;
        }

        try {
            ImmutableUser::where('id', 9999)->firstOrFail();
        } catch (\Exception $e) {
            $immutableException = $e;
        }

        $this->assertInstanceOf(ModelNotFoundException::class, $eloquentException);
        $this->assertInstanceOf(ModelNotFoundException::class, $immutableException);
    }

    // =========================================================================
    // WHERE CLAUSES
    // =========================================================================

    public function test_where_equality(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('name', 'Alice')->get(),
            fn() => ImmutableUser::where('name', 'Alice')->get(),
            'where() equality differs'
        );
    }

    public function test_where_with_operator(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('id', '>', 1)->orderBy('id')->get(),
            fn() => ImmutableUser::where('id', '>', 1)->orderBy('id')->get(),
            'where() with operator differs'
        );
    }

    public function test_where_with_less_than(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('id', '<', 3)->orderBy('id')->get(),
            fn() => ImmutableUser::where('id', '<', 3)->orderBy('id')->get(),
            'where() with < operator differs'
        );
    }

    public function test_where_with_not_equal(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('name', '!=', 'Alice')->orderBy('id')->get(),
            fn() => ImmutableUser::where('name', '!=', 'Alice')->orderBy('id')->get(),
            'where() with != operator differs'
        );
    }

    public function test_where_in(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereIn('id', [1, 2])->orderBy('id')->get(),
            fn() => ImmutableUser::whereIn('id', [1, 2])->orderBy('id')->get(),
            'whereIn() differs'
        );
    }

    public function test_where_in_empty_array(): void
    {
        $eloquent = EloquentUser::whereIn('id', [])->get();
        $immutable = ImmutableUser::whereIn('id', [])->get();

        $this->assertTrue($eloquent->isEmpty());
        $this->assertTrue($immutable->isEmpty());
    }

    public function test_where_not_in(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereNotIn('id', [1])->orderBy('id')->get(),
            fn() => ImmutableUser::whereNotIn('id', [1])->orderBy('id')->get(),
            'whereNotIn() differs'
        );
    }

    public function test_where_between(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereBetween('id', [1, 2])->orderBy('id')->get(),
            fn() => ImmutableUser::whereBetween('id', [1, 2])->orderBy('id')->get(),
            'whereBetween() differs'
        );
    }

    public function test_where_null(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereNull('email_verified_at')->orderBy('id')->get(),
            fn() => ImmutableUser::whereNull('email_verified_at')->orderBy('id')->get(),
            'whereNull() differs'
        );
    }

    public function test_where_not_null(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereNotNull('email_verified_at')->orderBy('id')->get(),
            fn() => ImmutableUser::whereNotNull('email_verified_at')->orderBy('id')->get(),
            'whereNotNull() differs'
        );
    }

    public function test_or_where(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('name', 'Alice')
                ->orWhere('name', 'Bob')
                ->orderBy('id')
                ->get(),
            fn() => ImmutableUser::where('name', 'Alice')
                ->orWhere('name', 'Bob')
                ->orderBy('id')
                ->get(),
            'orWhere() differs'
        );
    }

    public function test_multiple_where_clauses(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('id', '>', 0)
                ->where('name', '!=', 'Charlie')
                ->orderBy('id')
                ->get(),
            fn() => ImmutableUser::where('id', '>', 0)
                ->where('name', '!=', 'Charlie')
                ->orderBy('id')
                ->get(),
            'Multiple where() clauses differ'
        );
    }

    public function test_when_true_applies_callback(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()
                ->when(true, fn($q) => $q->where('name', 'Alice'))
                ->get(),
            fn() => ImmutableUser::query()
                ->when(true, fn($q) => $q->where('name', 'Alice'))
                ->get(),
            'when(true) differs'
        );
    }

    public function test_when_false_skips_callback(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()
                ->when(false, fn($q) => $q->where('name', 'Alice'))
                ->orderBy('id')
                ->get(),
            fn() => ImmutableUser::query()
                ->when(false, fn($q) => $q->where('name', 'Alice'))
                ->orderBy('id')
                ->get(),
            'when(false) differs'
        );
    }

    public function test_unless_false_applies_callback(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()
                ->unless(false, fn($q) => $q->where('name', 'Alice'))
                ->get(),
            fn() => ImmutableUser::query()
                ->unless(false, fn($q) => $q->where('name', 'Alice'))
                ->get(),
            'unless(false) differs'
        );
    }

    public function test_unless_true_skips_callback(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::query()
                ->unless(true, fn($q) => $q->where('name', 'Alice'))
                ->orderBy('id')
                ->get(),
            fn() => ImmutableUser::query()
                ->unless(true, fn($q) => $q->where('name', 'Alice'))
                ->orderBy('id')
                ->get(),
            'unless(true) differs'
        );
    }

    // =========================================================================
    // ORDERING & LIMITING
    // =========================================================================

    public function test_order_by_asc(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderBy('name', 'asc')->get(),
            fn() => ImmutableUser::query()->orderBy('name', 'asc')->get(),
            'orderBy() asc differs'
        );
    }

    public function test_order_by_desc(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderBy('name', 'desc')->get(),
            fn() => ImmutableUser::query()->orderBy('name', 'desc')->get(),
            'orderBy() desc differs'
        );
    }

    public function test_order_by_desc_method(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderByDesc('id')->get(),
            fn() => ImmutableUser::query()->orderByDesc('id')->get(),
            'orderByDesc() differs'
        );
    }

    public function test_latest(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::latest()->get(),
            fn() => ImmutableUser::query()->latest()->get(),
            'latest() differs'
        );
    }

    public function test_oldest(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::oldest()->get(),
            fn() => ImmutableUser::query()->oldest()->get(),
            'oldest() differs'
        );
    }

    public function test_limit(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderBy('id')->limit(2)->get(),
            fn() => ImmutableUser::query()->orderBy('id')->limit(2)->get(),
            'limit() differs'
        );
    }

    public function test_take(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderBy('id')->take(2)->get(),
            fn() => ImmutableUser::query()->orderBy('id')->take(2)->get(),
            'take() differs'
        );
    }

    public function test_offset(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderBy('id')->offset(1)->limit(10)->get(),
            fn() => ImmutableUser::query()->orderBy('id')->offset(1)->limit(10)->get(),
            'offset() differs'
        );
    }

    public function test_skip(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderBy('id')->skip(1)->take(10)->get(),
            fn() => ImmutableUser::query()->orderBy('id')->skip(1)->take(10)->get(),
            'skip() differs'
        );
    }

    // =========================================================================
    // AGGREGATES
    // =========================================================================

    public function test_count(): void
    {
        $eloquent = EloquentUser::count();
        $immutable = ImmutableUser::query()->count();

        $this->assertEquals($eloquent, $immutable, 'count() differs');
    }

    public function test_count_with_where(): void
    {
        $eloquent = EloquentUser::where('id', '>', 1)->count();
        $immutable = ImmutableUser::where('id', '>', 1)->count();

        $this->assertEquals($eloquent, $immutable, 'count() with where differs');
    }

    public function test_exists_true(): void
    {
        $eloquent = EloquentUser::where('id', 1)->exists();
        $immutable = ImmutableUser::where('id', 1)->exists();

        $this->assertTrue($eloquent);
        $this->assertTrue($immutable);
    }

    public function test_exists_false(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->exists();
        $immutable = ImmutableUser::where('id', 9999)->exists();

        $this->assertFalse($eloquent);
        $this->assertFalse($immutable);
    }

    public function test_doesnt_exist(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->doesntExist();
        $immutable = ImmutableUser::where('id', 9999)->doesntExist();

        $this->assertTrue($eloquent);
        $this->assertTrue($immutable);
    }

    public function test_sum(): void
    {
        $eloquent = EloquentUser::sum('id');
        $immutable = ImmutableUser::query()->sum('id');

        $this->assertEquals($eloquent, $immutable, 'sum() differs');
    }

    public function test_avg(): void
    {
        $eloquent = EloquentUser::avg('id');
        $immutable = ImmutableUser::query()->avg('id');

        $this->assertEquals($eloquent, $immutable, 'avg() differs');
    }

    public function test_min(): void
    {
        $eloquent = EloquentUser::min('id');
        $immutable = ImmutableUser::query()->min('id');

        $this->assertEquals($eloquent, $immutable, 'min() differs');
    }

    public function test_max(): void
    {
        $eloquent = EloquentUser::max('id');
        $immutable = ImmutableUser::query()->max('id');

        $this->assertEquals($eloquent, $immutable, 'max() differs');
    }

    // =========================================================================
    // PLUCK
    // =========================================================================

    public function test_pluck(): void
    {
        $eloquent = EloquentUser::orderBy('id')->pluck('name');
        $immutable = ImmutableUser::query()->orderBy('id')->pluck('name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray(), 'pluck() differs');
    }

    public function test_pluck_with_key(): void
    {
        $eloquent = EloquentUser::pluck('name', 'id');
        $immutable = ImmutableUser::query()->pluck('name', 'id');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray(), 'pluck() with key differs');
    }

    // =========================================================================
    // SELECTION
    // =========================================================================

    public function test_select(): void
    {
        $eloquent = EloquentUser::select(['id', 'name'])->orderBy('id')->first();
        $immutable = ImmutableUser::query()->select(['id', 'name'])->orderBy('id')->first();

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_add_select(): void
    {
        $eloquent = EloquentUser::select('id')->addSelect('name')->orderBy('id')->first();
        $immutable = ImmutableUser::query()->select('id')->addSelect('name')->orderBy('id')->first();

        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_distinct(): void
    {
        // Insert duplicate names to test distinct
        $this->app['db']->table('users')->insert([
            'name' => 'Alice',
            'email' => 'alice2@example.com',
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eloquent = EloquentUser::select('name')->distinct()->orderBy('name')->pluck('name');
        $immutable = ImmutableUser::query()->select('name')->distinct()->orderBy('name')->pluck('name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray(), 'distinct() differs');
    }

    // =========================================================================
    // PAGINATION
    // =========================================================================

    public function test_paginate(): void
    {
        $eloquent = EloquentUser::orderBy('id')->paginate(2);
        $immutable = ImmutableUser::query()->orderBy('id')->paginate(2);

        $this->assertPaginationParity($eloquent, $immutable, 'paginate() differs');
    }

    public function test_simple_paginate(): void
    {
        $eloquent = EloquentUser::orderBy('id')->simplePaginate(2);
        $immutable = ImmutableUser::query()->orderBy('id')->simplePaginate(2);

        // SimplePaginate doesn't have total(), compare all items
        $eloquentItems = $eloquent->items();
        $immutableItems = $immutable->items();
        $this->assertCount(count($eloquentItems), $immutableItems);

        foreach ($eloquentItems as $i => $eloquentModel) {
            $this->assertModelParity($eloquentModel, $immutableItems[$i]);
        }
    }

    // =========================================================================
    // CHUNKING & CURSOR
    // =========================================================================

    public function test_chunk(): void
    {
        $eloquentItems = [];
        $immutableItems = [];

        EloquentUser::orderBy('id')->chunk(2, function ($chunk) use (&$eloquentItems) {
            foreach ($chunk as $user) {
                $eloquentItems[] = $user->toArray();
            }
        });

        ImmutableUser::query()->orderBy('id')->chunk(2, function ($chunk) use (&$immutableItems) {
            foreach ($chunk as $user) {
                $immutableItems[] = $user->toArray();
            }
        });

        $this->assertEquals($eloquentItems, $immutableItems, 'chunk() processed items differ');
    }

    public function test_cursor(): void
    {
        $eloquentItems = [];
        $immutableItems = [];

        foreach (EloquentUser::orderBy('id')->cursor() as $user) {
            $eloquentItems[] = $user->toArray();
        }

        foreach (ImmutableUser::query()->orderBy('id')->cursor() as $user) {
            $immutableItems[] = $user->toArray();
        }

        $this->assertEquals($eloquentItems, $immutableItems, 'cursor() items differ');
    }

    // =========================================================================
    // COLLECTION TYPE VERIFICATION
    // =========================================================================

    public function test_get_returns_correct_collection_type(): void
    {
        $eloquent = EloquentUser::all();
        $immutable = ImmutableUser::all();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $eloquent);
        $this->assertInstanceOf(EloquentCollection::class, $immutable);
    }

    public function test_empty_result_returns_empty_collection(): void
    {
        $eloquent = EloquentUser::where('id', 9999)->get();
        $immutable = ImmutableUser::where('id', 9999)->get();

        $this->assertTrue($eloquent->isEmpty());
        $this->assertTrue($immutable->isEmpty());
        $this->assertEquals(0, $eloquent->count());
        $this->assertEquals(0, $immutable->count());
    }
}
