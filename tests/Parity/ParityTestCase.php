<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Brighten\ImmutableModel\ImmutableCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;

/**
 * Base test case for Eloquent parity testing.
 *
 * Provides helper methods for comparing Eloquent and ImmutableModel behavior
 * to ensure they produce identical results for all read operations.
 */
abstract class ParityTestCase extends TestCase
{
    /**
     * Assert that two values are equivalent for parity purposes.
     * Handles type differences (ImmutableCollection vs EloquentCollection, etc).
     */
    protected function assertParityEqual(
        mixed $eloquent,
        mixed $immutable,
        string $message = ''
    ): void {
        // Handle null
        if ($eloquent === null) {
            $this->assertNull($immutable, $message ?: 'Expected null parity');
            return;
        }

        // Handle collections
        if ($eloquent instanceof EloquentCollection) {
            $this->assertInstanceOf(
                ImmutableCollection::class,
                $immutable,
                $message ?: 'Expected ImmutableCollection for Eloquent Collection'
            );
            $this->assertCollectionParity($eloquent, $immutable, $message);
            return;
        }

        // Handle models
        if ($eloquent instanceof EloquentModel) {
            $this->assertInstanceOf(
                ImmutableModel::class,
                $immutable,
                $message ?: 'Expected ImmutableModel for Eloquent Model'
            );
            $this->assertModelParity($eloquent, $immutable, $message);
            return;
        }

        // Handle base Collection (from pluck, etc)
        if ($eloquent instanceof Collection) {
            $this->assertInstanceOf(
                Collection::class,
                $immutable,
                $message ?: 'Expected Collection type parity'
            );
            $this->assertEquals(
                $eloquent->toArray(),
                $immutable->toArray(),
                $message ?: 'Collection contents differ'
            );
            return;
        }

        // Handle scalar values and arrays
        $this->assertEquals($eloquent, $immutable, $message ?: 'Values differ');
    }

    /**
     * Assert two collections have equivalent contents.
     */
    protected function assertCollectionParity(
        EloquentCollection $eloquent,
        ImmutableCollection $immutable,
        string $message = ''
    ): void {
        $this->assertEquals(
            $eloquent->count(),
            $immutable->count(),
            $message ?: 'Collection count mismatch'
        );

        // Compare each model in the collection
        $eloquentItems = $eloquent->values()->all();
        $immutableItems = $immutable->values()->all();

        for ($i = 0; $i < count($eloquentItems); $i++) {
            if ($eloquentItems[$i] instanceof EloquentModel) {
                $this->assertModelParity(
                    $eloquentItems[$i],
                    $immutableItems[$i],
                    $message ?: "Model at index {$i} differs"
                );
            } else {
                $this->assertEquals(
                    $eloquentItems[$i],
                    $immutableItems[$i],
                    $message ?: "Item at index {$i} differs"
                );
            }
        }
    }

    /**
     * Assert two models have equivalent attribute values.
     */
    protected function assertModelParity(
        EloquentModel $eloquent,
        ImmutableModel $immutable,
        string $message = ''
    ): void {
        $eloquentArray = $eloquent->toArray();
        $immutableArray = $immutable->toArray();

        // Sort keys for consistent comparison
        ksort($eloquentArray);
        ksort($immutableArray);

        $this->assertEquals(
            $eloquentArray,
            $immutableArray,
            $message ?: 'Model attribute parity failed'
        );
    }

    /**
     * Assert that model attributes match (without relations).
     */
    protected function assertModelAttributesParity(
        EloquentModel $eloquent,
        ImmutableModel $immutable,
        string $message = ''
    ): void {
        // Compare raw attributes (before casting/appends)
        $eloquentAttrs = $eloquent->getAttributes();
        $immutableAttrs = $immutable->getAttributes();

        ksort($eloquentAttrs);
        ksort($immutableAttrs);

        $this->assertEquals(
            $eloquentAttrs,
            $immutableAttrs,
            $message ?: 'Model raw attributes differ'
        );
    }

    /**
     * Run the same query on both Eloquent and Immutable, assert parity.
     *
     * @param callable $eloquentQuery Closure that runs query on Eloquent model
     * @param callable $immutableQuery Closure that runs query on Immutable model
     * @param string $message Custom message on failure
     */
    protected function assertQueryParity(
        callable $eloquentQuery,
        callable $immutableQuery,
        string $message = ''
    ): void {
        $eloquentResult = $eloquentQuery();
        $immutableResult = $immutableQuery();

        $this->assertParityEqual($eloquentResult, $immutableResult, $message);
    }

    /**
     * Assert that a method exists on ImmutableModel with matching signature.
     */
    protected function assertMethodExists(
        string $className,
        string $methodName,
        ?string $returnType = null
    ): void {
        $this->assertTrue(
            method_exists($className, $methodName),
            "Method {$className}::{$methodName} does not exist"
        );

        if ($returnType !== null) {
            $reflection = new \ReflectionMethod($className, $methodName);
            $actualReturnType = $reflection->getReturnType();

            $this->assertNotNull(
                $actualReturnType,
                "Missing return type for {$methodName}"
            );

            // Handle union types and nullable types
            $typeName = $actualReturnType instanceof \ReflectionNamedType
                ? $actualReturnType->getName()
                : (string) $actualReturnType;

            $this->assertEquals(
                $returnType,
                $typeName,
                "Return type mismatch for {$methodName}"
            );
        }
    }

    /**
     * Assert arrays have equivalent content (order-insensitive for keys).
     */
    protected function assertArrayParity(
        array $eloquent,
        array $immutable,
        string $message = ''
    ): void {
        ksort($eloquent);
        ksort($immutable);
        $this->assertEquals($eloquent, $immutable, $message ?: 'Array contents differ');
    }

    /**
     * Assert pagination results match.
     */
    protected function assertPaginationParity(
        mixed $eloquent,
        mixed $immutable,
        string $message = ''
    ): void {
        $this->assertEquals(
            $eloquent->total(),
            $immutable->total(),
            $message ?: 'Pagination total differs'
        );
        $this->assertEquals(
            $eloquent->perPage(),
            $immutable->perPage(),
            $message ?: 'Pagination perPage differs'
        );
        $this->assertEquals(
            $eloquent->currentPage(),
            $immutable->currentPage(),
            $message ?: 'Pagination currentPage differs'
        );
        $this->assertEquals(
            $eloquent->lastPage(),
            $immutable->lastPage(),
            $message ?: 'Pagination lastPage differs'
        );

        // Compare items
        $this->assertEquals(
            $eloquent->items()[0]?->toArray(),
            $immutable->items()[0]?->toArray(),
            $message ?: 'Pagination first item differs'
        );
    }

    /**
     * Seed standard test data for parity tests.
     */
    protected function seedParityTestData(): void
    {
        // Users
        $this->app['db']->table('users')->insert([
            [
                'id' => 1,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'settings' => json_encode(['theme' => 'dark', 'notifications' => true]),
                'email_verified_at' => '2024-01-01 12:00:00',
                'supplier_id' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'settings' => null,
                'email_verified_at' => null,
                'supplier_id' => null,
                'created_at' => '2024-01-02 00:00:00',
                'updated_at' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Charlie',
                'email' => 'charlie@example.com',
                'settings' => json_encode(['theme' => 'light']),
                'email_verified_at' => '2024-01-03 00:00:00',
                'supplier_id' => null,
                'created_at' => '2024-01-03 00:00:00',
                'updated_at' => '2024-01-03 00:00:00',
            ],
        ]);

        // Profiles (only for user 1)
        $this->app['db']->table('profiles')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'bio' => 'Software developer',
                'birthday' => '1990-05-15',
            ],
        ]);

        // Posts
        $this->app['db']->table('posts')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'First Post',
                'body' => 'This is the first post content.',
                'published' => true,
                'created_at' => '2024-01-01 10:00:00',
                'updated_at' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'category_id' => null,
                'title' => 'Second Post',
                'body' => 'This is the second post content.',
                'published' => false,
                'created_at' => '2024-01-02 10:00:00',
                'updated_at' => '2024-01-02 10:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'category_id' => null,
                'title' => 'Bob Post',
                'body' => 'This is Bob\'s post.',
                'published' => true,
                'created_at' => '2024-01-03 10:00:00',
                'updated_at' => '2024-01-03 10:00:00',
            ],
        ]);

        // Comments
        $this->app['db']->table('comments')->insert([
            [
                'id' => 1,
                'post_id' => 1,
                'user_id' => 2,
                'body' => 'Great post!',
                'created_at' => '2024-01-01 11:00:00',
                'updated_at' => '2024-01-01 11:00:00',
            ],
            [
                'id' => 2,
                'post_id' => 1,
                'user_id' => 3,
                'body' => 'I agree!',
                'created_at' => '2024-01-01 12:00:00',
                'updated_at' => '2024-01-01 12:00:00',
            ],
        ]);

        // Tags
        $this->app['db']->table('tags')->insert([
            ['id' => 1, 'name' => 'PHP', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Laravel', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Post-Tag pivot
        $this->app['db']->table('post_tag')->insert([
            ['post_id' => 1, 'tag_id' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['post_id' => 1, 'tag_id' => 2, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
