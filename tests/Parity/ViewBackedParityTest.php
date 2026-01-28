<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUserPostCount;
use Brighten\ImmutableModel\Tests\Models\ImmutableUserPostCount;

/**
 * Tests that view-backed models behave identically in both models.
 *
 * The user_post_counts view aggregates user data with their post counts.
 */
class ViewBackedParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedViewData();
    }

    protected function seedViewData(): void
    {
        // Create users
        $this->app['db']->table('users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create posts with varying counts
        $this->app['db']->table('posts')->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'Post 1', 'body' => 'Body 1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 1, 'title' => 'Post 2', 'body' => 'Body 2', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 1, 'title' => 'Post 3', 'body' => 'Body 3', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'user_id' => 2, 'title' => 'Post 4', 'body' => 'Body 4', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            // User 3 (Charlie) has no posts
        ]);
    }

    // =========================================================================
    // BASIC QUERIES ON VIEW
    // =========================================================================

    public function test_get_all_from_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->get();
        $immutable = ImmutableUserPostCount::orderBy('user_id')->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'View row count mismatch');
    }

    public function test_first_from_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->first();
        $immutable = ImmutableUserPostCount::orderBy('user_id')->first();

        $this->assertEquals($eloquent->user_id, $immutable->user_id);
        $this->assertEquals($eloquent->name, $immutable->name);
        $this->assertEquals($eloquent->post_count, $immutable->post_count);
    }

    // =========================================================================
    // FILTERING ON VIEW
    // =========================================================================

    public function test_where_on_aggregated_column(): void
    {
        $eloquent = EloquentUserPostCount::where('post_count', '>', 0)->orderBy('user_id')->get();
        $immutable = ImmutableUserPostCount::where('post_count', '>', 0)->orderBy('user_id')->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'Filter count mismatch');
    }

    public function test_where_on_base_column(): void
    {
        $eloquent = EloquentUserPostCount::where('name', 'Alice')->first();
        $immutable = ImmutableUserPostCount::where('name', 'Alice')->first();

        $this->assertEquals($eloquent->user_id, $immutable->user_id);
        $this->assertEquals($eloquent->post_count, $immutable->post_count);
    }

    public function test_where_post_count_zero(): void
    {
        $eloquent = EloquentUserPostCount::where('post_count', 0)->get();
        $immutable = ImmutableUserPostCount::where('post_count', 0)->get();

        $this->assertEquals($eloquent->count(), $immutable->count(), 'Zero post count mismatch');
    }

    // =========================================================================
    // ORDERING ON VIEW
    // =========================================================================

    public function test_order_by_aggregated_column(): void
    {
        $eloquent = EloquentUserPostCount::orderByDesc('post_count')->get();
        $immutable = ImmutableUserPostCount::orderByDesc('post_count')->get();

        $this->assertEquals(
            $eloquent->first()->user_id,
            $immutable->first()->user_id,
            'Top poster mismatch'
        );
    }

    public function test_order_by_name(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('name')->get();
        $immutable = ImmutableUserPostCount::orderBy('name')->get();

        $this->assertEquals(
            $eloquent->pluck('name')->toArray(),
            $immutable->pluck('name')->toArray(),
            'Name ordering mismatch'
        );
    }

    // =========================================================================
    // AGGREGATES ON VIEW
    // =========================================================================

    public function test_count_on_view(): void
    {
        $eloquent = EloquentUserPostCount::count();
        $immutable = ImmutableUserPostCount::query()->count();

        $this->assertEquals($eloquent, $immutable, 'View count mismatch');
    }

    public function test_sum_on_aggregated_column(): void
    {
        $eloquent = EloquentUserPostCount::sum('post_count');
        $immutable = ImmutableUserPostCount::query()->sum('post_count');

        $this->assertEquals($eloquent, $immutable, 'Sum of post counts mismatch');
    }

    public function test_max_on_aggregated_column(): void
    {
        $eloquent = EloquentUserPostCount::max('post_count');
        $immutable = ImmutableUserPostCount::query()->max('post_count');

        $this->assertEquals($eloquent, $immutable, 'Max post count mismatch');
    }

    public function test_avg_on_aggregated_column(): void
    {
        $eloquent = EloquentUserPostCount::avg('post_count');
        $immutable = ImmutableUserPostCount::query()->avg('post_count');

        // Use approximate comparison for floating point
        $this->assertEqualsWithDelta($eloquent, $immutable, 0.001, 'Avg post count mismatch');
    }

    // =========================================================================
    // SELECTION ON VIEW
    // =========================================================================

    public function test_select_specific_columns(): void
    {
        $eloquent = EloquentUserPostCount::select('name', 'post_count')->orderBy('user_id')->get();
        $immutable = ImmutableUserPostCount::select('name', 'post_count')->orderBy('user_id')->get();

        $this->assertEquals($eloquent->count(), $immutable->count());

        // Compare first record
        $this->assertEquals($eloquent->first()->name, $immutable->first()->name);
        $this->assertEquals($eloquent->first()->post_count, $immutable->first()->post_count);
    }

    public function test_pluck_from_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->pluck('name');
        $immutable = ImmutableUserPostCount::orderBy('user_id')->pluck('name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray(), 'Pluck results mismatch');
    }

    public function test_pluck_with_key_from_view(): void
    {
        $eloquent = EloquentUserPostCount::pluck('post_count', 'name');
        $immutable = ImmutableUserPostCount::pluck('post_count', 'name');

        $this->assertEquals($eloquent->toArray(), $immutable->toArray(), 'Keyed pluck results mismatch');
    }

    // =========================================================================
    // SERIALIZATION FROM VIEW
    // =========================================================================

    public function test_to_array_from_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->first()->toArray();
        $immutable = ImmutableUserPostCount::orderBy('user_id')->first()->toArray();

        // Compare relevant keys
        $this->assertEquals($eloquent['user_id'], $immutable['user_id']);
        $this->assertEquals($eloquent['name'], $immutable['name']);
        $this->assertEquals($eloquent['post_count'], $immutable['post_count']);
    }

    public function test_to_json_from_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->get();
        $immutable = ImmutableUserPostCount::orderBy('user_id')->get();

        $eloquentJson = json_decode($eloquent->toJson(), true);
        $immutableJson = json_decode($immutable->toJson(), true);

        $this->assertEquals(count($eloquentJson), count($immutableJson), 'JSON array count mismatch');
    }

    // =========================================================================
    // NULL PRIMARY KEY HANDLING
    // =========================================================================

    public function test_model_without_pk_uses_where_instead(): void
    {
        // Views typically don't have a primary key, so we need to use where() instead of find()
        // This tests that both models handle querying by user_id consistently
        $eloquent = EloquentUserPostCount::where('user_id', 1)->first();
        $immutable = ImmutableUserPostCount::where('user_id', 1)->first();

        $this->assertEquals($eloquent->user_id, $immutable->user_id);
        $this->assertEquals($eloquent->name, $immutable->name);
        $this->assertEquals($eloquent->post_count, $immutable->post_count);
    }

    // =========================================================================
    // PAGINATION ON VIEW
    // =========================================================================

    public function test_paginate_on_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->paginate(2);
        $immutable = ImmutableUserPostCount::orderBy('user_id')->paginate(2);

        $this->assertEquals($eloquent->total(), $immutable->total(), 'Pagination total mismatch');
        $this->assertEquals($eloquent->perPage(), $immutable->perPage(), 'Pagination per page mismatch');
        $this->assertEquals($eloquent->count(), $immutable->count(), 'Pagination current page count mismatch');
    }

    public function test_simple_paginate_on_view(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->simplePaginate(2);
        $immutable = ImmutableUserPostCount::orderBy('user_id')->simplePaginate(2);

        $this->assertEquals($eloquent->count(), $immutable->count(), 'Simple pagination count mismatch');
    }

    // =========================================================================
    // CAST VERIFICATION
    // =========================================================================

    public function test_casts_applied_correctly(): void
    {
        $eloquent = EloquentUserPostCount::orderBy('user_id')->first();
        $immutable = ImmutableUserPostCount::orderBy('user_id')->first();

        // user_id should be cast to int
        $this->assertIsInt($eloquent->user_id);
        $this->assertIsInt($immutable->user_id);

        // post_count should be cast to int
        $this->assertIsInt($eloquent->post_count);
        $this->assertIsInt($immutable->post_count);
    }
}
