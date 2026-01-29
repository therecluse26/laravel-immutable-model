<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\Models\NoPrimaryKeyModel;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class QueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    protected function seedTestData(): void
    {
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'settings' => json_encode(['role' => 'admin']),
                'email_verified_at' => '2024-01-01 00:00:00',
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Charlie',
                'email' => 'charlie@example.com',
                'settings' => json_encode(['role' => 'user']),
                'email_verified_at' => '2024-01-03 00:00:00',
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
        ]);
    }

    // =========================================================================
    // BASIC QUERY METHODS
    // =========================================================================

    public function test_query_returns_builder(): void
    {
        $builder = ImmutableUser::query();

        $this->assertInstanceOf(ImmutableQueryBuilder::class, $builder);
    }

    public function test_get_returns_immutable_collection(): void
    {
        $users = ImmutableUser::query()->get();

        $this->assertInstanceOf(EloquentCollection::class, $users);
        $this->assertCount(3, $users);
    }

    public function test_first_returns_model_or_null(): void
    {
        $user = ImmutableUser::query()->first();
        $this->assertInstanceOf(ImmutableUser::class, $user);

        $missing = ImmutableUser::query()->where('id', 999)->first();
        $this->assertNull($missing);
    }

    public function test_first_or_fail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        ImmutableUser::query()->where('id', 999)->firstOrFail();
    }

    public function test_find_returns_model_or_null(): void
    {
        $user = ImmutableUser::find(1);
        $this->assertInstanceOf(ImmutableUser::class, $user);
        $this->assertEquals('Alice', $user->name);

        $missing = ImmutableUser::find(999);
        $this->assertNull($missing);
    }

    public function test_find_or_fail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        ImmutableUser::findOrFail(999);
    }

    // =========================================================================
    // WHERE CLAUSES
    // =========================================================================

    public function test_where_basic(): void
    {
        $users = ImmutableUser::where('name', 'Alice')->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Alice', $users->first()->name);
    }

    public function test_where_with_operator(): void
    {
        $users = ImmutableUser::where('id', '>', 1)->get();

        $this->assertCount(2, $users);
    }

    public function test_or_where(): void
    {
        $users = ImmutableUser::where('name', 'Alice')
            ->orWhere('name', 'Bob')
            ->get();

        $this->assertCount(2, $users);
    }

    public function test_where_in(): void
    {
        $users = ImmutableUser::query()->whereIn('id', [1, 2])->get();

        $this->assertCount(2, $users);
    }

    public function test_where_not_in(): void
    {
        $users = ImmutableUser::query()->whereNotIn('id', [1, 2])->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Charlie', $users->first()->name);
    }

    public function test_where_between(): void
    {
        $users = ImmutableUser::query()->whereBetween('id', [1, 2])->get();

        $this->assertCount(2, $users);
    }

    public function test_where_null(): void
    {
        $users = ImmutableUser::query()->whereNull('email_verified_at')->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Bob', $users->first()->name);
    }

    public function test_where_not_null(): void
    {
        $users = ImmutableUser::query()->whereNotNull('email_verified_at')->get();

        $this->assertCount(2, $users);
    }

    public function test_when_applies_callback_when_true(): void
    {
        $users = ImmutableUser::query()
            ->when(true, fn($q) => $q->where('name', 'Alice'))
            ->get();

        $this->assertCount(1, $users);
    }

    public function test_when_skips_callback_when_false(): void
    {
        $users = ImmutableUser::query()
            ->when(false, fn($q) => $q->where('name', 'Alice'))
            ->get();

        $this->assertCount(3, $users);
    }

    public function test_unless_applies_callback_when_false(): void
    {
        $users = ImmutableUser::query()
            ->unless(false, fn($q) => $q->where('name', 'Alice'))
            ->get();

        $this->assertCount(1, $users);
    }

    // =========================================================================
    // SELECTION
    // =========================================================================

    public function test_select_specific_columns(): void
    {
        $user = ImmutableUser::query()->select(['id', 'name'])->first();

        $this->assertEquals(1, $user->id);
        $this->assertEquals('Alice', $user->name);
        $this->assertNull($user->email); // Not selected
    }

    public function test_add_select(): void
    {
        $user = ImmutableUser::query()
            ->select('id')
            ->addSelect('name')
            ->first();

        $this->assertEquals(1, $user->id);
        $this->assertEquals('Alice', $user->name);
    }

    public function test_distinct(): void
    {
        $count = ImmutableUser::query()->distinct()->count();

        $this->assertEquals(3, $count);
    }

    // =========================================================================
    // ORDERING & LIMITING
    // =========================================================================

    public function test_order_by(): void
    {
        $users = ImmutableUser::query()->orderBy('name', 'asc')->get();

        $this->assertEquals('Alice', $users[0]->name);
        $this->assertEquals('Bob', $users[1]->name);
        $this->assertEquals('Charlie', $users[2]->name);
    }

    public function test_order_by_desc(): void
    {
        $users = ImmutableUser::query()->orderByDesc('name')->get();

        $this->assertEquals('Charlie', $users[0]->name);
        $this->assertEquals('Bob', $users[1]->name);
        $this->assertEquals('Alice', $users[2]->name);
    }

    public function test_latest(): void
    {
        $user = ImmutableUser::query()->latest('created_at')->first();

        $this->assertEquals('Charlie', $user->name);
    }

    public function test_oldest(): void
    {
        $user = ImmutableUser::query()->oldest('created_at')->first();

        $this->assertEquals('Alice', $user->name);
    }

    public function test_limit(): void
    {
        $users = ImmutableUser::query()->limit(2)->get();

        $this->assertCount(2, $users);
    }

    public function test_take(): void
    {
        $users = ImmutableUser::query()->take(2)->get();

        $this->assertCount(2, $users);
    }

    public function test_offset(): void
    {
        // SQLite requires limit when using offset
        $users = ImmutableUser::query()->orderBy('id')->offset(1)->limit(10)->get();

        $this->assertCount(2, $users);
        $this->assertEquals('Bob', $users[0]->name);
    }

    public function test_skip(): void
    {
        // SQLite requires limit when using skip
        $users = ImmutableUser::query()->orderBy('id')->skip(1)->limit(10)->get();

        $this->assertCount(2, $users);
    }

    // =========================================================================
    // AGGREGATES
    // =========================================================================

    public function test_count(): void
    {
        $count = ImmutableUser::query()->count();

        $this->assertEquals(3, $count);
    }

    public function test_exists(): void
    {
        $this->assertTrue(ImmutableUser::query()->exists());
        $this->assertFalse(ImmutableUser::query()->where('id', 999)->exists());
    }

    public function test_doesnt_exist(): void
    {
        $this->assertFalse(ImmutableUser::query()->doesntExist());
        $this->assertTrue(ImmutableUser::query()->where('id', 999)->doesntExist());
    }

    public function test_sum(): void
    {
        $sum = ImmutableUser::query()->sum('id');

        $this->assertEquals(6, $sum);
    }

    public function test_avg(): void
    {
        $avg = ImmutableUser::query()->avg('id');

        $this->assertEquals(2, $avg);
    }

    public function test_min(): void
    {
        $min = ImmutableUser::query()->min('id');

        $this->assertEquals(1, $min);
    }

    public function test_max(): void
    {
        $max = ImmutableUser::query()->max('id');

        $this->assertEquals(3, $max);
    }

    public function test_pluck(): void
    {
        $names = ImmutableUser::query()->orderBy('id')->pluck('name');

        $this->assertInstanceOf(Collection::class, $names);
        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $names->all());
    }

    public function test_pluck_with_key(): void
    {
        $names = ImmutableUser::query()->pluck('name', 'id');

        $this->assertEquals([1 => 'Alice', 2 => 'Bob', 3 => 'Charlie'], $names->all());
    }

    // =========================================================================
    // PAGINATION
    // =========================================================================

    public function test_paginate(): void
    {
        $paginator = ImmutableUser::query()->paginate(2);

        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
        $this->assertEquals(3, $paginator->total());
        $this->assertEquals(2, $paginator->lastPage());
    }

    public function test_simple_paginate(): void
    {
        $paginator = ImmutableUser::query()->simplePaginate(2);

        $this->assertInstanceOf(\Illuminate\Pagination\Paginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
    }

    // =========================================================================
    // CHUNKING & LAZY
    // =========================================================================

    public function test_chunk(): void
    {
        $chunks = [];

        ImmutableUser::query()->orderBy('id')->chunk(2, function ($users) use (&$chunks) {
            $chunks[] = $users;
        });

        $this->assertCount(2, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(1, $chunks[1]);
    }

    public function test_chunk_can_be_stopped(): void
    {
        $processed = 0;

        ImmutableUser::query()->chunk(1, function ($users) use (&$processed) {
            $processed++;

            return false; // Stop after first chunk
        });

        $this->assertEquals(1, $processed);
    }

    public function test_cursor(): void
    {
        $users = ImmutableUser::query()->cursor();

        $this->assertInstanceOf(\Illuminate\Support\LazyCollection::class, $users);

        $names = [];
        foreach ($users as $user) {
            $names[] = $user->name;
        }

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $names);
    }

    // =========================================================================
    // MODEL WITHOUT PRIMARY KEY
    // =========================================================================

    public function test_find_throws_without_primary_key(): void
    {
        NoPrimaryKeyModel::setConnectionResolver($this->app['db']);

        $this->expectException(ImmutableModelConfigurationException::class);
        $this->expectExceptionMessage('Cannot perform [find]');

        NoPrimaryKeyModel::find(1);
    }
}
