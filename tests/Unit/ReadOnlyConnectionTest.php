<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Unit;

use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\ReadOnlyConnection;
use Brighten\ImmutableModel\Tests\Models\ImmutablePost;
use Brighten\ImmutableModel\Tests\Models\ImmutableSupplier;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for ReadOnlyConnection, the single place where writes are blocked.
 *
 * Every write test also asserts that the database did not change. A write
 * that throws only after it reached the database would still be a failure.
 */
class ReadOnlyConnectionTest extends TestCase
{
    private const TABLES = ['users', 'posts', 'tags', 'post_tag', 'taggables', 'countries', 'suppliers'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('posts')->insert([
            ['id' => 1, 'user_id' => 1, 'title' => 'First', 'body' => 'Body', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 1, 'title' => 'Second', 'body' => 'Body', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'user_id' => 2, 'title' => 'Third', 'body' => 'Body', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('tags')->insert([
            ['id' => 1, 'name' => 'PHP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Laravel', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('taggables')->insert([
            ['tag_id' => 1, 'taggable_type' => ImmutablePost::class, 'taggable_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('countries')->insert(['id' => 1, 'name' => 'Norway', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('suppliers')->insert([
            ['id' => 1, 'country_id' => 1, 'name' => 'Active', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
            ['id' => 2, 'country_id' => 1, 'name' => 'Trashed', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => now()],
        ]);
    }

    /**
     * Snapshot every fixture table so a test can prove nothing was written.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $snapshot = [];

        foreach (self::TABLES as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    /**
     * Assert that the callback throws ImmutableModelViolationException and writes nothing.
     */
    private function assertWriteBlocked(callable $write): ImmutableModelViolationException
    {
        $before = $this->snapshot();

        try {
            $write();
            $this->fail('Expected ImmutableModelViolationException, but no exception was thrown.');
        } catch (ImmutableModelViolationException $e) {
            $this->assertSame($before, $this->snapshot(), 'The database changed before the write was blocked.');

            return $e;
        }
    }

    // =========================================================================
    // THE SIX CONNECTION WRITE METHODS
    // =========================================================================

    /**
     * @return array<string, array{string, callable(ReadOnlyConnection): mixed}>
     */
    public static function connectionWriteProvider(): array
    {
        return [
            'insert' => ['insert', fn (ReadOnlyConnection $c) => $c->insert('insert into "users" ("name", "email") values (?, ?)', ['x', 'x@example.com'])],
            'update' => ['update', fn (ReadOnlyConnection $c) => $c->update('update "users" set "name" = ?', ['x'])],
            'delete' => ['delete', fn (ReadOnlyConnection $c) => $c->delete('delete from "posts"')],
            'statement' => ['statement', fn (ReadOnlyConnection $c) => $c->statement('delete from "posts"')],
            'affectingStatement' => ['affectingStatement', fn (ReadOnlyConnection $c) => $c->affectingStatement('delete from "posts"')],
            'unprepared' => ['unprepared', fn (ReadOnlyConnection $c) => $c->unprepared('delete from "posts"')],
        ];
    }

    #[DataProvider('connectionWriteProvider')]
    public function test_connection_write_method_throws(string $operation, callable $write): void
    {
        $connection = new ReadOnlyConnection(DB::connection());

        $e = $this->assertWriteBlocked(fn () => $write($connection));

        $this->assertStringContainsString("Cannot run [{$operation}]", $e->getMessage());
    }

    public function test_connection_table_builder_is_read_only(): void
    {
        $connection = new ReadOnlyConnection(DB::connection());

        $this->assertSame(2, $connection->table('users')->count());
        $this->assertWriteBlocked(fn () => $connection->table('users')->update(['name' => 'x']));
    }

    // =========================================================================
    // BYPASS PATHS THAT THE OLD BLOCKLISTS MISSED
    // =========================================================================

    /**
     * @return array<string, array{callable(): mixed}>
     */
    public static function bypassPathProvider(): array
    {
        return [
            'toBase()->update()' => [fn () => ImmutableUser::query()->toBase()->update(['name' => 'x'])],
            'getQuery()->delete()' => [fn () => ImmutablePost::query()->getQuery()->delete()],
            'insertOrIgnoreUsing()' => [fn () => ImmutableUser::query()->insertOrIgnoreUsing(
                ['name', 'email'],
                DB::table('users')->selectRaw("'copy', 'copy@example.com'")->limit(1)
            )],
            'relation toBase()->delete()' => [fn () => ImmutableUser::findOrFail(1)->posts()->toBase()->delete()],
            'incrementEach()' => [fn () => ImmutablePost::query()->incrementEach(['user_id' => 1])],
            'builder getConnection()->table()' => [fn () => ImmutableUser::query()->getConnection()->table('users')->update(['name' => 'x'])],
            'transaction callback connection' => [fn () => ImmutableUser::query()->getConnection()->transaction(
                fn ($connection) => $connection->table('users')->update(['name' => 'x'])
            )],
        ];
    }

    #[DataProvider('bypassPathProvider')]
    public function test_bypass_path_is_blocked(callable $write): void
    {
        $this->assertWriteBlocked($write);
    }

    // =========================================================================
    // SOFT DELETE BUILDER MACROS
    // =========================================================================

    public function test_soft_delete_restore_macro_is_blocked(): void
    {
        $this->assertWriteBlocked(fn () => ImmutableSupplier::onlyTrashed()->restore());
    }

    public function test_soft_delete_restore_or_create_macro_is_blocked(): void
    {
        $this->assertWriteBlocked(fn () => ImmutableSupplier::query()->restoreOrCreate(['name' => 'Trashed']));
    }

    public function test_soft_delete_create_or_restore_macro_is_blocked(): void
    {
        $this->assertWriteBlocked(fn () => ImmutableSupplier::query()->createOrRestore(['name' => 'New', 'country_id' => 1]));
    }

    // =========================================================================
    // PIVOT WRITES
    // =========================================================================

    /**
     * @return array<string, array{callable(): mixed}>
     */
    public static function pivotWriteProvider(): array
    {
        return [
            'attach()' => [fn () => ImmutablePost::findOrFail(1)->tags()->attach(2)],
            'detach()' => [fn () => ImmutablePost::findOrFail(1)->tags()->detach()],
            'sync()' => [fn () => ImmutablePost::findOrFail(1)->tags()->sync([2])],
            'toggle()' => [fn () => ImmutablePost::findOrFail(1)->tags()->toggle([1])],
            'updateExistingPivot()' => [fn () => ImmutablePost::findOrFail(1)->tags()->updateExistingPivot(1, ['order' => 5])],
            'morph attach()' => [fn () => ImmutablePost::findOrFail(1)->morphTags()->attach(2)],
            'morph detach()' => [fn () => ImmutablePost::findOrFail(1)->morphTags()->detach()],
            'pivot query delete()' => [fn () => ImmutablePost::findOrFail(1)->tags->first()->pivot->newQuery()->delete()],
            'morph pivot query delete()' => [fn () => ImmutablePost::findOrFail(1)->morphTags->first()->pivot->newQuery()->delete()],
        ];
    }

    #[DataProvider('pivotWriteProvider')]
    public function test_pivot_write_is_blocked(callable $write): void
    {
        $this->assertWriteBlocked($write);
    }

    // =========================================================================
    // READS STILL WORK THROUGH THE WRAPPER
    // =========================================================================

    public function test_cursor_reads(): void
    {
        $names = [];
        foreach (ImmutableUser::orderBy('id')->cursor() as $user) {
            $names[] = $user->name;
        }

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function test_lazy_reads(): void
    {
        $this->assertSame([1, 2, 3], ImmutablePost::lazy(2)->pluck('id')->all());
    }

    public function test_chunk_reads(): void
    {
        $chunks = [];
        ImmutablePost::orderBy('id')->chunk(2, function ($posts) use (&$chunks) {
            $chunks[] = $posts->pluck('id')->all();
        });

        $this->assertSame([[1, 2], [3]], $chunks);
    }

    public function test_paginate_reads(): void
    {
        $page = ImmutablePost::orderBy('id')->paginate(2);

        $this->assertSame(3, $page->total());
        $this->assertSame([1, 2], $page->pluck('id')->all());
    }

    public function test_lock_for_update_reads(): void
    {
        $this->assertCount(2, ImmutableUser::lockForUpdate()->get());
    }

    public function test_reads_inside_transaction(): void
    {
        $count = DB::transaction(fn () => ImmutablePost::where('user_id', 1)->count());

        $this->assertSame(2, $count);
    }

    public function test_eager_loaded_pivots_are_immutable_pivots(): void
    {
        $post = ImmutablePost::with(['tags', 'morphTags'])->findOrFail(1);

        $this->assertInstanceOf(\Brighten\ImmutableModel\Relations\ImmutablePivot::class, $post->tags->first()->pivot);
        $this->assertInstanceOf(\Brighten\ImmutableModel\Relations\ImmutableMorphPivot::class, $post->morphTags->first()->pivot);
        $this->assertSame(1, $post->tags->first()->pivot->order);
    }

    // =========================================================================
    // EXCEPTION MESSAGE
    // =========================================================================

    public function test_message_contains_sql_but_not_binding_values(): void
    {
        $e = $this->assertWriteBlocked(
            fn () => ImmutableUser::query()->toBase()->update(['name' => 'secret-value-123'])
        );

        $this->assertStringContainsString('Cannot run [update]', $e->getMessage());
        $this->assertStringContainsString('update "users" set "name" = ?', $e->getMessage());
        $this->assertStringNotContainsString('secret-value-123', $e->getMessage());
    }
}
