<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentInitializedUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableInitializedUser;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Parity tests for the created_at / updated_at columns.
 *
 * Eloquent casts timestamp columns to dates on read when $timestamps is
 * true. Immutable models must read them the same way, while every
 * timestamp write (touch(), save()) still throws.
 */
class TimestampParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    public function test_timestamp_columns_are_dates_without_explicit_casts(): void
    {
        $eloquent = EloquentInitializedUser::findOrFail(1);
        $immutable = ImmutableInitializedUser::findOrFail(1);

        $this->assertInstanceOf(CarbonInterface::class, $eloquent->created_at);
        $this->assertInstanceOf(CarbonInterface::class, $immutable->created_at);
        $this->assertInstanceOf(CarbonInterface::class, $immutable->updated_at);
        $this->assertEquals($eloquent->created_at, $immutable->created_at);
    }

    public function test_timestamp_serialization_matches_eloquent(): void
    {
        $eloquent = EloquentInitializedUser::findOrFail(1)->toArray();
        $immutable = ImmutableInitializedUser::findOrFail(1)->toArray();

        $this->assertSame($eloquent['created_at'], $immutable['created_at']);
        $this->assertSame($eloquent['updated_at'], $immutable['updated_at']);
    }

    public function test_uses_timestamps_matches_eloquent(): void
    {
        $this->assertSame(
            (new EloquentInitializedUser())->usesTimestamps(),
            (new ImmutableInitializedUser())->usesTimestamps()
        );
    }

    public function test_touch_still_throws_and_writes_nothing(): void
    {
        $before = DB::table('users')->where('id', 1)->value('updated_at');

        try {
            ImmutableInitializedUser::findOrFail(1)->touch();
            $this->fail('Expected ImmutableModelViolationException.');
        } catch (ImmutableModelViolationException) {
            $this->assertSame($before, DB::table('users')->where('id', 1)->value('updated_at'));
        }
    }

    public function test_builder_touch_throws_and_writes_nothing(): void
    {
        $before = DB::table('users')->pluck('updated_at')->all();

        try {
            ImmutableInitializedUser::query()->touch();
            $this->fail('Expected ImmutableModelViolationException.');
        } catch (ImmutableModelViolationException) {
            $this->assertSame($before, DB::table('users')->pluck('updated_at')->all());
        }
    }
}
