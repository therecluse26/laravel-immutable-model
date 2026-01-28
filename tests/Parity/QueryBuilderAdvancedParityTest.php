<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;

/**
 * Parity tests for advanced query builder methods.
 *
 * Verifies that ImmutableModel produces identical results to Eloquent
 * for whereDate, whereColumn, joins, groupBy, having, and cursorPaginate.
 */
class QueryBuilderAdvancedParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAdvancedTestData();
    }

    protected function seedAdvancedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'settings' => json_encode(['role' => 'admin']),
                'email_verified_at' => '2024-01-15 10:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'settings' => null,
                'email_verified_at' => '2024-01-20 10:00:00',
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Charlie',
                'email' => 'charlie@example.com',
                'settings' => json_encode(['role' => 'user']),
                'email_verified_at' => '2024-02-01 10:00:00',
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
        ]);

        $this->app['db']->table('posts')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'First Post',
                'body' => 'Content of first post',
                'published' => true,
                'created_at' => '2024-01-01 10:00:00',
                'updated_at' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'Second Post',
                'body' => 'Content of second post',
                'published' => true,
                'created_at' => '2024-01-02 10:00:00',
                'updated_at' => '2024-01-02 10:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'category_id' => null,
                'title' => 'Bob Post',
                'body' => 'Content of Bob post',
                'published' => false,
                'created_at' => '2024-01-03 10:00:00',
                'updated_at' => '2024-01-03 10:00:00',
            ],
        ]);
    }

    // =========================================================================
    // WHERE DATE PARITY
    // =========================================================================

    public function test_where_date_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereDate('email_verified_at', '2024-01-15')->get(),
            fn() => ImmutableUser::query()->whereDate('email_verified_at', '2024-01-15')->get(),
            'whereDate() results differ'
        );
    }

    public function test_where_date_with_operator_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereDate('email_verified_at', '>', '2024-01-15')
                ->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereDate('email_verified_at', '>', '2024-01-15')
                ->orderBy('id')->get(),
            'whereDate() with operator differs'
        );
    }

    public function test_where_day_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereDay('email_verified_at', '15')->get(),
            fn() => ImmutableUser::query()->whereDay('email_verified_at', '15')->get(),
            'whereDay() results differ'
        );
    }

    public function test_where_month_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereMonth('email_verified_at', '01')
                ->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereMonth('email_verified_at', '01')
                ->orderBy('id')->get(),
            'whereMonth() results differ'
        );
    }

    public function test_where_year_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereYear('email_verified_at', '2024')
                ->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereYear('email_verified_at', '2024')
                ->orderBy('id')->get(),
            'whereYear() results differ'
        );
    }

    public function test_where_time_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereTime('email_verified_at', '10:00:00')
                ->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereTime('email_verified_at', '10:00:00')
                ->orderBy('id')->get(),
            'whereTime() results differ'
        );
    }

    // =========================================================================
    // WHERE COLUMN PARITY
    // =========================================================================

    public function test_where_column_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereColumn('created_at', 'updated_at')
                ->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereColumn('created_at', 'updated_at')
                ->orderBy('id')->get(),
            'whereColumn() results differ'
        );
    }

    public function test_where_column_with_operator_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereColumn('created_at', '!=', 'updated_at')
                ->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereColumn('created_at', '!=', 'updated_at')
                ->orderBy('id')->get(),
            'whereColumn() with operator differs'
        );
    }

    // =========================================================================
    // JOIN PARITY
    // =========================================================================

    public function test_join_parity(): void
    {
        $eloquent = EloquentUser::join('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->orderBy('posts.id')
            ->get();

        $immutable = ImmutableUser::query()
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->orderBy('posts.id')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'join() count differs');

        for ($i = 0; $i < $eloquent->count(); $i++) {
            $this->assertEquals(
                $eloquent[$i]->name,
                $immutable[$i]->name,
                "join() name at index {$i} differs"
            );
            $this->assertEquals(
                $eloquent[$i]->title,
                $immutable[$i]->title,
                "join() title at index {$i} differs"
            );
        }
    }

    public function test_left_join_parity(): void
    {
        $eloquent = EloquentUser::leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->orderBy('users.id')
            ->orderBy('posts.id')
            ->get();

        $immutable = ImmutableUser::query()
            ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->orderBy('users.id')
            ->orderBy('posts.id')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'leftJoin() count differs');

        for ($i = 0; $i < $eloquent->count(); $i++) {
            $this->assertEquals(
                $eloquent[$i]->name,
                $immutable[$i]->name,
                "leftJoin() name at index {$i} differs"
            );
            $this->assertEquals(
                $eloquent[$i]->title,
                $immutable[$i]->title,
                "leftJoin() title at index {$i} differs"
            );
        }
    }

    public function test_cross_join_parity(): void
    {
        $eloquent = EloquentUser::crossJoin('posts')
            ->select('users.name', 'posts.title')
            ->orderBy('users.id')
            ->orderBy('posts.id')
            ->get();

        $immutable = ImmutableUser::query()
            ->crossJoin('posts')
            ->select('users.name', 'posts.title')
            ->orderBy('users.id')
            ->orderBy('posts.id')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'crossJoin() count differs');
    }

    public function test_join_with_closure_parity(): void
    {
        $eloquent = EloquentUser::join('posts', function ($join) {
                $join->on('users.id', '=', 'posts.user_id')
                     ->where('posts.published', '=', true);
            })
            ->select('users.name', 'posts.title')
            ->orderBy('posts.id')
            ->get();

        $immutable = ImmutableUser::query()
            ->join('posts', function ($join) {
                $join->on('users.id', '=', 'posts.user_id')
                     ->where('posts.published', '=', true);
            })
            ->select('users.name', 'posts.title')
            ->orderBy('posts.id')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'join() with closure count differs');
    }

    // =========================================================================
    // GROUP BY PARITY
    // =========================================================================

    public function test_group_by_parity(): void
    {
        $eloquent = EloquentPost::select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();

        $immutable = ImmutablePost::query()
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'groupBy() count differs');

        for ($i = 0; $i < $eloquent->count(); $i++) {
            $this->assertEquals(
                $eloquent[$i]->user_id,
                $immutable[$i]->user_id,
                "groupBy() user_id at index {$i} differs"
            );
            $this->assertEquals(
                $eloquent[$i]->post_count,
                $immutable[$i]->post_count,
                "groupBy() post_count at index {$i} differs"
            );
        }
    }

    public function test_group_by_multiple_columns_parity(): void
    {
        $eloquent = EloquentPost::select('user_id', 'published')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('user_id', 'published')
            ->orderBy('user_id')
            ->orderBy('published')
            ->get();

        $immutable = ImmutablePost::query()
            ->select('user_id', 'published')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('user_id', 'published')
            ->orderBy('user_id')
            ->orderBy('published')
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'groupBy() multiple columns count differs');
    }

    // =========================================================================
    // HAVING PARITY
    // =========================================================================

    public function test_having_parity(): void
    {
        $eloquent = EloquentPost::select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->having('post_count', '>', 1)
            ->get();

        $immutable = ImmutablePost::query()
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->having('post_count', '>', 1)
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'having() count differs');

        if ($eloquent->count() > 0) {
            $this->assertEquals(
                $eloquent->first()->user_id,
                $immutable->first()->user_id,
                'having() user_id differs'
            );
            $this->assertEquals(
                $eloquent->first()->post_count,
                $immutable->first()->post_count,
                'having() post_count differs'
            );
        }
    }

    public function test_having_raw_parity(): void
    {
        $eloquent = EloquentPost::select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [2])
            ->get();

        $immutable = ImmutablePost::query()
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= ?', [2])
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'havingRaw() count differs');
    }

    // =========================================================================
    // CURSOR PAGINATE PARITY
    // =========================================================================

    public function test_cursor_paginate_parity(): void
    {
        $eloquent = EloquentUser::orderBy('id')->cursorPaginate(2);
        $immutable = ImmutableUser::query()->orderBy('id')->cursorPaginate(2);

        $this->assertEquals(
            count($eloquent->items()),
            count($immutable->items()),
            'cursorPaginate() item count differs'
        );

        $this->assertEquals(
            $eloquent->hasMorePages(),
            $immutable->hasMorePages(),
            'cursorPaginate() hasMorePages differs'
        );

        // Compare items
        $eloquentItems = array_values($eloquent->items());
        $immutableItems = array_values($immutable->items());

        for ($i = 0; $i < count($eloquentItems); $i++) {
            $this->assertEquals(
                $eloquentItems[$i]->toArray(),
                $immutableItems[$i]->toArray(),
                "cursorPaginate() item at index {$i} differs"
            );
        }
    }

    public function test_cursor_paginate_second_page_parity(): void
    {
        $eloquentFirst = EloquentUser::orderBy('id')->cursorPaginate(2);
        $immutableFirst = ImmutableUser::query()->orderBy('id')->cursorPaginate(2);

        $eloquentCursor = $eloquentFirst->nextCursor();
        $immutableCursor = $immutableFirst->nextCursor();

        $eloquentSecond = EloquentUser::orderBy('id')
            ->cursorPaginate(2, ['*'], 'cursor', $eloquentCursor);
        $immutableSecond = ImmutableUser::query()
            ->orderBy('id')
            ->cursorPaginate(2, ['*'], 'cursor', $immutableCursor);

        $this->assertEquals(
            count($eloquentSecond->items()),
            count($immutableSecond->items()),
            'cursorPaginate() second page item count differs'
        );

        $this->assertEquals(
            $eloquentSecond->hasMorePages(),
            $immutableSecond->hasMorePages(),
            'cursorPaginate() second page hasMorePages differs'
        );
    }

    // =========================================================================
    // ADDITIONAL WHERE METHOD PARITY
    // =========================================================================

    public function test_where_not_between_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereNotBetween('id', [1, 2])->get(),
            fn() => ImmutableUser::query()->whereNotBetween('id', [1, 2])->get(),
            'whereNotBetween() results differ'
        );
    }

    public function test_or_where_in_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where('name', 'Alice')
                ->orWhereIn('id', [2, 3])
                ->orderBy('id')
                ->get(),
            fn() => ImmutableUser::query()
                ->where('name', 'Alice')
                ->orWhereIn('id', [2, 3])
                ->orderBy('id')
                ->get(),
            'orWhereIn() results differ'
        );
    }

    public function test_where_nested_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::where(function ($query) {
                    $query->where('name', 'Alice')
                          ->orWhere('name', 'Bob');
                })
                ->orderBy('id')
                ->get(),
            fn() => ImmutableUser::query()
                ->where(function ($query) {
                    $query->where('name', 'Alice')
                          ->orWhere('name', 'Bob');
                })
                ->orderBy('id')
                ->get(),
            'Nested where() results differ'
        );
    }

    // =========================================================================
    // RAW EXPRESSION PARITY
    // =========================================================================

    public function test_select_raw_parity(): void
    {
        $eloquent = EloquentUser::selectRaw('COUNT(*) as user_count')->first();
        $immutable = ImmutableUser::query()->selectRaw('COUNT(*) as user_count')->first();

        $this->assertEquals(
            $eloquent->user_count,
            $immutable->user_count,
            'selectRaw() count differs'
        );
    }

    public function test_where_raw_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::whereRaw('id > ?', [1])->orderBy('id')->get(),
            fn() => ImmutableUser::query()->whereRaw('id > ?', [1])->orderBy('id')->get(),
            'whereRaw() results differ'
        );
    }

    public function test_order_by_raw_parity(): void
    {
        $this->assertQueryParity(
            fn() => EloquentUser::orderByRaw('id DESC')->get(),
            fn() => ImmutableUser::query()->orderByRaw('id DESC')->get(),
            'orderByRaw() results differ'
        );
    }
}
