<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentPost;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Tests edge cases to ensure ImmutableModel handles them identically to Eloquent.
 */
class EdgeCaseParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // FIND EDGE CASES
    // =========================================================================

    public function test_find_nonexistent_returns_null(): void
    {
        $eloquent = EloquentUser::find(99999);
        $immutable = ImmutableUser::find(99999);

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_find_with_zero_returns_null(): void
    {
        $eloquent = EloquentUser::find(0);
        $immutable = ImmutableUser::find(0);

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_find_with_negative_returns_null(): void
    {
        $eloquent = EloquentUser::find(-1);
        $immutable = ImmutableUser::find(-1);

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    // =========================================================================
    // FIRST EDGE CASES
    // =========================================================================

    public function test_first_on_empty_result(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->first();
        $immutable = ImmutableUser::where('id', 99999)->first();

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_first_or_fail_throws_on_empty(): void
    {
        $eloquentThrew = false;
        $immutableThrew = false;

        try {
            EloquentUser::where('id', 99999)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $eloquentThrew = true;
        }

        try {
            ImmutableUser::where('id', 99999)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $immutableThrew = true;
        }

        $this->assertTrue($eloquentThrew);
        $this->assertTrue($immutableThrew);
    }

    // =========================================================================
    // WHERE EDGE CASES
    // =========================================================================

    public function test_where_in_empty_array(): void
    {
        $eloquent = EloquentUser::whereIn('id', [])->get();
        $immutable = ImmutableUser::whereIn('id', [])->get();

        $this->assertTrue($eloquent->isEmpty());
        $this->assertTrue($immutable->isEmpty());
    }

    public function test_where_not_in_empty_array(): void
    {
        $eloquent = EloquentUser::whereNotIn('id', [])->orderBy('id')->get();
        $immutable = ImmutableUser::whereNotIn('id', [])->orderBy('id')->get();

        // Should return all records
        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    public function test_where_between_same_values(): void
    {
        $eloquent = EloquentUser::whereBetween('id', [1, 1])->get();
        $immutable = ImmutableUser::whereBetween('id', [1, 1])->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    // =========================================================================
    // NULL VALUE HANDLING
    // =========================================================================

    public function test_null_attribute_access(): void
    {
        $eloquent = EloquentUser::find(2);
        $immutable = ImmutableUser::find(2);

        // settings is null for user 2
        $this->assertNull($eloquent->settings);
        $this->assertNull($immutable->settings);
    }

    public function test_null_foreign_key_relation(): void
    {
        // User 3 has null supplier_id
        $eloquent = EloquentUser::find(3);
        $immutable = ImmutableUser::find(3);

        $this->assertNull($eloquent->supplier);
        $this->assertNull($immutable->supplier);
    }

    // =========================================================================
    // MISSING ATTRIBUTE HANDLING
    // =========================================================================

    public function test_missing_attribute_returns_null(): void
    {
        $eloquent = EloquentUser::find(1);
        $immutable = ImmutableUser::find(1);

        $this->assertNull($eloquent->nonexistent_column);
        $this->assertNull($immutable->nonexistent_column);
    }

    public function test_partial_select_missing_attribute(): void
    {
        $eloquent = EloquentUser::select('id')->first();
        $immutable = ImmutableUser::query()->select('id')->first();

        // Accessing non-selected column
        $this->assertNull($eloquent->name);
        $this->assertNull($immutable->name);
    }

    // =========================================================================
    // WHEN/UNLESS CONDITIONAL QUERIES
    // =========================================================================

    public function test_when_true(): void
    {
        $eloquent = EloquentUser::query()
            ->when(true, fn($q) => $q->where('name', 'Alice'))
            ->get();
        $immutable = ImmutableUser::query()
            ->when(true, fn($q) => $q->where('name', 'Alice'))
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertEquals(1, $eloquent->count());
    }

    public function test_when_false(): void
    {
        $eloquent = EloquentUser::query()
            ->when(false, fn($q) => $q->where('name', 'Alice'))
            ->orderBy('id')
            ->get();
        $immutable = ImmutableUser::query()
            ->when(false, fn($q) => $q->where('name', 'Alice'))
            ->orderBy('id')
            ->get();

        // Should return all users
        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertGreaterThan(1, $eloquent->count());
    }

    public function test_when_with_default(): void
    {
        $eloquent = EloquentUser::query()
            ->when(
                false,
                fn($q) => $q->where('name', 'Alice'),
                fn($q) => $q->where('name', 'Bob')
            )
            ->get();
        $immutable = ImmutableUser::query()
            ->when(
                false,
                fn($q) => $q->where('name', 'Alice'),
                fn($q) => $q->where('name', 'Bob')
            )
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertEquals(1, $eloquent->count());
    }

    public function test_unless_false(): void
    {
        $eloquent = EloquentUser::query()
            ->unless(false, fn($q) => $q->where('name', 'Alice'))
            ->get();
        $immutable = ImmutableUser::query()
            ->unless(false, fn($q) => $q->where('name', 'Alice'))
            ->get();

        $this->assertEquals($eloquent->count(), $immutable->count());
        $this->assertEquals(1, $eloquent->count());
    }

    public function test_unless_true(): void
    {
        $eloquent = EloquentUser::query()
            ->unless(true, fn($q) => $q->where('name', 'Alice'))
            ->orderBy('id')
            ->get();
        $immutable = ImmutableUser::query()
            ->unless(true, fn($q) => $q->where('name', 'Alice'))
            ->orderBy('id')
            ->get();

        // Should return all users
        $this->assertEquals($eloquent->count(), $immutable->count());
    }

    // =========================================================================
    // AGGREGATES ON EMPTY RESULTS
    // =========================================================================

    public function test_count_empty(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->count();
        $immutable = ImmutableUser::where('id', 99999)->count();

        $this->assertEquals(0, $eloquent);
        $this->assertEquals(0, $immutable);
    }

    public function test_sum_empty(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->sum('id');
        $immutable = ImmutableUser::where('id', 99999)->sum('id');

        $this->assertEquals(0, $eloquent);
        $this->assertEquals(0, $immutable);
    }

    public function test_avg_empty(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->avg('id');
        $immutable = ImmutableUser::where('id', 99999)->avg('id');

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_min_empty(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->min('id');
        $immutable = ImmutableUser::where('id', 99999)->min('id');

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    public function test_max_empty(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->max('id');
        $immutable = ImmutableUser::where('id', 99999)->max('id');

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    // =========================================================================
    // EXISTS/DOESNT EXIST
    // =========================================================================

    public function test_exists_true(): void
    {
        $eloquent = EloquentUser::where('id', 1)->exists();
        $immutable = ImmutableUser::where('id', 1)->exists();

        $this->assertTrue($eloquent);
        $this->assertTrue($immutable);
    }

    public function test_exists_false(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->exists();
        $immutable = ImmutableUser::where('id', 99999)->exists();

        $this->assertFalse($eloquent);
        $this->assertFalse($immutable);
    }

    public function test_doesnt_exist_true(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->doesntExist();
        $immutable = ImmutableUser::where('id', 99999)->doesntExist();

        $this->assertTrue($eloquent);
        $this->assertTrue($immutable);
    }

    public function test_doesnt_exist_false(): void
    {
        $eloquent = EloquentUser::where('id', 1)->doesntExist();
        $immutable = ImmutableUser::where('id', 1)->doesntExist();

        $this->assertFalse($eloquent);
        $this->assertFalse($immutable);
    }

    // =========================================================================
    // EMPTY RELATIONS
    // =========================================================================

    public function test_has_many_empty(): void
    {
        // User 3 has no posts
        $eloquent = EloquentUser::find(3)->posts;
        $immutable = ImmutableUser::find(3)->posts;

        $this->assertTrue($eloquent->isEmpty());
        $this->assertTrue($immutable->isEmpty());
        $this->assertEquals(0, $eloquent->count());
        $this->assertEquals(0, $immutable->count());
    }

    public function test_has_one_null(): void
    {
        // User 2 has no profile
        $eloquent = EloquentUser::find(2)->profile;
        $immutable = ImmutableUser::find(2)->profile;

        $this->assertNull($eloquent);
        $this->assertNull($immutable);
    }

    // =========================================================================
    // PLUCK EDGE CASES
    // =========================================================================

    public function test_pluck_empty(): void
    {
        $eloquent = EloquentUser::where('id', 99999)->pluck('name');
        $immutable = ImmutableUser::where('id', 99999)->pluck('name');

        $this->assertTrue($eloquent->isEmpty());
        $this->assertTrue($immutable->isEmpty());
    }

    public function test_pluck_null_values(): void
    {
        $eloquent = EloquentUser::orderBy('id')->pluck('settings');
        $immutable = ImmutableUser::query()->orderBy('id')->pluck('settings');

        // Should contain both arrays and nulls
        $this->assertEquals($eloquent->toArray(), $immutable->toArray());
    }
}
