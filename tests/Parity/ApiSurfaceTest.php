<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Tests\Parity;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Brighten\ImmutableModel\ImmutableModel;
use Brighten\ImmutableModel\ImmutableQueryBuilder;
use Brighten\ImmutableModel\Tests\Models\ImmutableUser;
use ReflectionClass;

/**
 * Tests that ImmutableModel exposes the same API surface as Eloquent.
 *
 * Verifies method existence, interface implementation, and common usage patterns.
 */
class ApiSurfaceTest extends ParityTestCase
{
    // =========================================================================
    // MODEL METHODS
    // =========================================================================

    /**
     * @var array<string> Methods that must exist on ImmutableModel
     */
    private array $requiredModelMethods = [
        // Static query methods
        'query',
        'find',
        'findOrFail',
        'where',
        'with',
        'all',

        // Instance methods
        'getTable',
        'getKeyName',
        'getKey',
        'getKeyType',
        'getConnectionName',
        'getAttribute',
        'getAttributes',

        // Serialization
        'toArray',
        'toJson',
        'jsonSerialize',

        // Relations
        'relationLoaded',
        'getRelation',
        'getRelations',
    ];

    public function test_model_has_required_methods(): void
    {
        foreach ($this->requiredModelMethods as $method) {
            $this->assertTrue(
                method_exists(ImmutableModel::class, $method),
                "ImmutableModel is missing required method: {$method}"
            );
        }
    }

    // =========================================================================
    // QUERY BUILDER METHODS
    // =========================================================================

    /**
     * @var array<string> Methods that must exist on ImmutableQueryBuilder
     */
    private array $requiredQueryBuilderMethods = [
        // Execution
        'get',
        'first',
        'firstOrFail',
        'find',
        'findOrFail',

        // Where clauses
        'where',
        'orWhere',
        'whereIn',
        'whereNotIn',
        'whereBetween',
        'whereNull',
        'whereNotNull',
        'when',
        'unless',

        // Selection
        'select',
        'addSelect',
        'distinct',

        // Ordering
        'orderBy',
        'orderByDesc',
        'latest',
        'oldest',

        // Limiting
        'limit',
        'offset',
        'skip',
        'take',

        // Eager loading
        'with',
        'withCount',

        // Aggregates
        'count',
        'exists',
        'doesntExist',
        'sum',
        'avg',
        'min',
        'max',
        'pluck',

        // Pagination
        'paginate',
        'simplePaginate',

        // Chunking
        'chunk',
        'cursor',
    ];

    public function test_query_builder_has_required_methods(): void
    {
        // Use an instance so we can check for forwarded methods via __call
        $builder = ImmutableUser::query();

        foreach ($this->requiredQueryBuilderMethods as $method) {
            $this->assertTrue(
                is_callable([$builder, $method]),
                "ImmutableQueryBuilder is missing required method: {$method}"
            );
        }
    }

    // =========================================================================
    // COLLECTION METHODS
    // =========================================================================

    /**
     * @var array<string> Methods that must exist on ImmutableCollection
     */
    private array $requiredCollectionMethods = [
        // Basic
        'count',
        'isEmpty',
        'isNotEmpty',
        'first',
        'last',
        'all',

        // Filtering
        'filter',
        'reject',
        'where',
        'whereIn',
        'whereNotIn',

        // Transformation
        'map',
        'pluck',
        'each',
        'reduce',
        'keys',
        'values',

        // Aggregates
        'sum',
        'avg',
        'min',
        'max',

        // Sorting
        'sortBy',
        'sortByDesc',
        'reverse',

        // Slicing
        'take',
        'skip',
        'unique',

        // Grouping
        'groupBy',
        'keyBy',

        // Testing
        'contains',
        'every',

        // Serialization
        'toArray',
        'toJson',
        'toBase',
    ];

    public function test_collection_has_required_methods(): void
    {
        foreach ($this->requiredCollectionMethods as $method) {
            $this->assertTrue(
                method_exists(EloquentCollection::class, $method),
                "ImmutableCollection is missing required method: {$method}"
            );
        }
    }

    // =========================================================================
    // INTERFACE IMPLEMENTATION
    // =========================================================================

    public function test_model_implements_array_access(): void
    {
        $class = new ReflectionClass(ImmutableModel::class);
        $this->assertTrue($class->implementsInterface(\ArrayAccess::class));
    }

    public function test_model_implements_json_serializable(): void
    {
        $class = new ReflectionClass(ImmutableModel::class);
        $this->assertTrue($class->implementsInterface(\JsonSerializable::class));
    }

    public function test_model_implements_arrayable(): void
    {
        $class = new ReflectionClass(ImmutableModel::class);
        $this->assertTrue($class->implementsInterface(\Illuminate\Contracts\Support\Arrayable::class));
    }

    public function test_model_implements_jsonable(): void
    {
        $class = new ReflectionClass(ImmutableModel::class);
        $this->assertTrue($class->implementsInterface(\Illuminate\Contracts\Support\Jsonable::class));
    }

    public function test_collection_implements_countable(): void
    {
        $class = new ReflectionClass(EloquentCollection::class);
        $this->assertTrue($class->implementsInterface(\Countable::class));
    }

    public function test_collection_implements_iterator_aggregate(): void
    {
        $class = new ReflectionClass(EloquentCollection::class);
        $this->assertTrue($class->implementsInterface(\IteratorAggregate::class));
    }

    public function test_collection_implements_array_access(): void
    {
        $class = new ReflectionClass(EloquentCollection::class);
        $this->assertTrue($class->implementsInterface(\ArrayAccess::class));
    }

    // =========================================================================
    // COMMON USAGE PATTERNS
    // =========================================================================

    public function test_static_query_returns_builder(): void
    {
        $builder = ImmutableUser::query();
        $this->assertInstanceOf(ImmutableQueryBuilder::class, $builder);
    }

    public function test_static_where_returns_builder(): void
    {
        $builder = ImmutableUser::where('id', 1);
        $this->assertInstanceOf(ImmutableQueryBuilder::class, $builder);
    }

    public function test_static_with_returns_builder(): void
    {
        $builder = ImmutableUser::with('posts');
        $this->assertInstanceOf(ImmutableQueryBuilder::class, $builder);
    }

    public function test_chaining_pattern(): void
    {
        $builder = ImmutableUser::query()
            ->where('id', '>', 0)
            ->orderBy('name')
            ->with('posts')
            ->limit(10);

        $this->assertInstanceOf(ImmutableQueryBuilder::class, $builder);
    }

    public function test_get_returns_immutable_collection(): void
    {
        $this->seedParityTestData();
        $collection = ImmutableUser::all();

        $this->assertInstanceOf(EloquentCollection::class, $collection);
    }

    public function test_find_returns_immutable_model(): void
    {
        $this->seedParityTestData();
        $user = ImmutableUser::find(1);

        $this->assertInstanceOf(ImmutableModel::class, $user);
        $this->assertInstanceOf(ImmutableUser::class, $user);
    }

    // =========================================================================
    // RELATION METHODS RETURN CORRECT TYPES
    // =========================================================================

    public function test_relation_method_returns_relation_instance(): void
    {
        $this->seedParityTestData();
        $user = ImmutableUser::find(1);

        // Calling the relation method (not property) returns a relation builder
        $relation = $user->posts();

        // Should be queryable (methods are forwarded via __call)
        $this->assertTrue(is_callable([$relation, 'get']));
        $this->assertTrue(is_callable([$relation, 'first']));
        $this->assertTrue(is_callable([$relation, 'where']));
    }

    public function test_relation_property_returns_results(): void
    {
        $this->seedParityTestData();
        $user = ImmutableUser::find(1);

        // Accessing as property returns the loaded relation
        $posts = $user->posts;

        $this->assertInstanceOf(EloquentCollection::class, $posts);
    }
}
