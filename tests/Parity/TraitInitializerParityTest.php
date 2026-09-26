<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\Tests\Models\Eloquent\EloquentInitializedUser;
use Brighten\ImmutableModel\Tests\Models\ImmutableInitializedUser;
use Illuminate\Support\Facades\DB;

/**
 * Parity tests for trait initializers (initializeXxx() methods).
 *
 * ImmutableModel::newFromBuilder() skips the constructor for speed.
 * Trait initializers must still run once for every hydrated model.
 */
class TraitInitializerParityTest extends ParityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedParityTestData();
        DB::table('users')->where('id', 1)->update(['supplier_id' => null]);
    }

    public function test_initializer_runs_once_for_query_results(): void
    {
        $users = ImmutableInitializedUser::orderBy('id')->get();

        $this->assertCount(3, $users);
        foreach ($users as $user) {
            $this->assertSame(1, $user->initializerRuns);
        }
    }

    public function test_initializer_state_matches_eloquent(): void
    {
        $eloquent = EloquentInitializedUser::findOrFail(1);
        $immutable = ImmutableInitializedUser::findOrFail(1);

        $this->assertSame($eloquent->getHidden(), $immutable->getHidden());
        $this->assertSame($eloquent->getAppends(), $immutable->getAppends());
        $this->assertSame($eloquent->getCasts(), $immutable->getCasts());
        $this->assertEquals(
            $eloquent->makeHidden(['created_at', 'updated_at'])->toArray(),
            $immutable->makeHidden(['created_at', 'updated_at'])->toArray()
        );
    }

    public function test_with_casts_does_not_leak_into_later_queries(): void
    {
        $withCasts = ImmutableInitializedUser::withCasts(['name' => 'array'])->findOrFail(1);
        $plain = ImmutableInitializedUser::findOrFail(1);

        $this->assertSame('array', $withCasts->getCasts()['name']);
        $this->assertArrayNotHasKey('name', $plain->getCasts());
    }

    public function test_table_set_at_runtime_is_kept_on_query_results(): void
    {
        DB::statement('create view users_view as select * from users');

        $model = (new ImmutableInitializedUser())->setTable('users_view');
        $user = $model->newQuery()->findOrFail(1);

        $this->assertSame('users_view', $user->getTable());
    }

    public function test_initializer_state_does_not_accumulate_across_instances(): void
    {
        ImmutableInitializedUser::all();
        ImmutableInitializedUser::all();

        $user = ImmutableInitializedUser::findOrFail(1);

        $this->assertSame(['email'], $user->getHidden());
        $this->assertSame(['initialized_marker'], $user->getAppends());
    }

    public function test_initializer_runs_for_from_row(): void
    {
        $user = ImmutableInitializedUser::fromRow(['id' => 9, 'name' => 'Row', 'email' => 'row@example.com']);

        $this->assertSame(1, $user->initializerRuns);
        $this->assertArrayNotHasKey('email', $user->toArray());
        $this->assertSame('initialized', $user->toArray()['initialized_marker']);
    }
}
