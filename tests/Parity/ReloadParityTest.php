<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Parity tests for fresh(), refresh() and replicate().
 *
 * fresh() and refresh() only read from the database. replicate() only
 * copies in memory. All three must behave exactly like Eloquent.
 */
class ReloadParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
    }

    // =========================================================================
    // fresh()
    // =========================================================================

    public function test_fresh_returns_new_instance_with_current_database_values(): void
    {
        $eloquent = EloquentUser::findOrFail(1);
        $immutable = ImmutableUser::findOrFail(1);

        DB::table('users')->where('id', 1)->update(['name' => 'Alice Updated']);

        $eloquentFresh = $eloquent->fresh();
        $immutableFresh = $immutable->fresh();

        $this->assertNotSame($immutable, $immutableFresh);
        $this->assertSame('Alice', $immutable->name);
        $this->assertSame('Alice Updated', $immutableFresh->name);
        $this->assertModelParity($eloquentFresh, $immutableFresh);
    }

    public function test_fresh_eager_loads_requested_relations(): void
    {
        $eloquent = EloquentUser::findOrFail(1)->fresh(['posts']);
        $immutable = ImmutableUser::findOrFail(1)->fresh(['posts']);

        $this->assertTrue($immutable->relationLoaded('posts'));
        $this->assertSame(
            $eloquent->posts->pluck('id')->all(),
            $immutable->posts->pluck('id')->all()
        );
    }

    public function test_fresh_returns_null_when_row_was_deleted(): void
    {
        $eloquent = EloquentUser::findOrFail(2);
        $immutable = ImmutableUser::findOrFail(2);

        DB::table('users')->where('id', 2)->delete();

        $this->assertNull($eloquent->fresh());
        $this->assertNull($immutable->fresh());
    }

    public function test_fresh_returns_null_for_model_not_loaded_from_database(): void
    {
        $this->assertNull((new EloquentUser())->fresh());
        $this->assertNull((new ImmutableUser())->fresh());
    }

    // =========================================================================
    // refresh()
    // =========================================================================

    public function test_refresh_reloads_same_instance(): void
    {
        $eloquent = EloquentUser::findOrFail(1);
        $immutable = ImmutableUser::findOrFail(1);

        DB::table('users')->where('id', 1)->update(['name' => 'Alice Updated']);

        $this->assertSame($eloquent, $eloquent->refresh());
        $this->assertSame($immutable, $immutable->refresh());
        $this->assertSame('Alice Updated', $immutable->name);
        $this->assertModelParity($eloquent, $immutable);
    }

    public function test_refresh_discards_in_memory_changes(): void
    {
        $immutable = ImmutableUser::findOrFail(1);
        $immutable->name = 'Changed in memory';

        $immutable->refresh();

        $this->assertSame('Alice', $immutable->name);
    }

    public function test_refresh_reloads_loaded_relations(): void
    {
        $eloquent = EloquentUser::with('posts')->findOrFail(1);
        $immutable = ImmutableUser::with('posts')->findOrFail(1);

        DB::table('posts')->where('user_id', 1)->update(['title' => 'Retitled']);

        $eloquent->refresh();
        $immutable->refresh();

        $this->assertSame(
            $eloquent->posts->pluck('title')->all(),
            $immutable->posts->pluck('title')->all()
        );
        $this->assertContains('Retitled', $immutable->posts->pluck('title')->all());
    }

    public function test_refresh_throws_when_row_was_deleted(): void
    {
        $immutable = ImmutableUser::findOrFail(2);
        DB::table('users')->where('id', 2)->delete();

        $this->expectException(ModelNotFoundException::class);

        $immutable->refresh();
    }

    // =========================================================================
    // replicate()
    // =========================================================================

    public function test_replicate_copies_attributes_without_key(): void
    {
        $eloquent = EloquentUser::findOrFail(1)->replicate();
        $immutable = ImmutableUser::findOrFail(1)->replicate();

        $this->assertFalse($immutable->exists);
        $this->assertNull($immutable->getKey());
        $this->assertSame('Alice', $immutable->name);
        $this->assertEquals(
            array_keys($eloquent->getAttributes()),
            array_keys($immutable->getAttributes())
        );
    }

    public function test_replicate_respects_except(): void
    {
        $immutable = ImmutableUser::findOrFail(1)->replicate(['email']);

        $this->assertArrayNotHasKey('email', $immutable->getAttributes());
    }

    public function test_replicated_model_cannot_be_saved(): void
    {
        $count = DB::table('users')->count();
        $replica = ImmutableUser::findOrFail(1)->replicate();

        try {
            $replica->save();
            $this->fail('Expected ImmutableModelViolationException.');
        } catch (ImmutableModelViolationException) {
            $this->assertSame($count, DB::table('users')->count());
        }
    }
}
