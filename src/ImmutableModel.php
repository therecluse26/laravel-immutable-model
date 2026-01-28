<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use ArrayAccess;
use Brighten\ImmutableModel\Casts\CastManager;
use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableHasOne;
use Brighten\ImmutableModel\Scopes\ImmutableModelScope;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use JsonSerializable;
use stdClass;

/**
 * Abstract base class for immutable, read-only models.
 *
 * ImmutableModel provides Eloquent-like read semantics while enforcing strict
 * immutability. Models are hydrated from database rows and cannot be modified
 * after creation.
 *
 * @implements ArrayAccess<string, mixed>
 */
abstract class ImmutableModel implements ArrayAccess, JsonSerializable, Arrayable, Jsonable
{
    /**
     * The database table associated with the model.
     */
    protected string $table;

    /**
     * The primary key for the model. Null means the model is non-identifiable.
     */
    protected ?string $primaryKey = 'id';

    /**
     * The database connection to use.
     */
    protected ?string $connection = null;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    protected bool $incrementing = false;

    /**
     * The type of the primary key.
     */
    protected string $keyType = 'int';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string|class-string>
     */
    protected array $casts = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array<int, string>
     */
    protected array $with = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected array $appends = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     *
     * @var array<int, string>
     */
    protected array $visible = [];

    /**
     * The model's raw attributes from the database.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * The loaded relationships.
     *
     * @var array<string, mixed>
     */
    private array $relations = [];

    /**
     * The connection resolver instance.
     */
    protected static ?ConnectionResolverInterface $resolver = null;

    /**
     * Global scopes registered for this model.
     *
     * @var array<class-string, class-string<ImmutableModelScope>>
     */
    protected static array $globalScopes = [];

    /**
     * The cast manager instance.
     */
    private ?CastManager $castManager = null;

    /**
     * Create a new immutable model instance.
     *
     * The constructor is final and protected to prevent direct instantiation.
     * Models must be hydrated through query results or factory methods.
     */
    final protected function __construct()
    {
        // Intentionally empty - attributes are set via hydration
    }

    /**
     * Hydrate a model instance from a database row.
     *
     * @param array<string, mixed>|stdClass $row
     */
    final protected static function hydrateFromRow(array|stdClass $row): static
    {
        $instance = new static();
        $instance->attributes = (array) $row;

        return $instance;
    }

    /**
     * Create an instance from a raw database row.
     *
     * @param array<string, mixed>|stdClass $row
     */
    public static function fromRow(array|stdClass $row): static
    {
        return static::hydrateFromRow($row);
    }

    /**
     * Create a collection of instances from raw database rows.
     *
     * @param iterable<array<string, mixed>|stdClass> $rows
     */
    public static function fromRows(iterable $rows): ImmutableCollection
    {
        $models = [];

        foreach ($rows as $row) {
            $models[] = static::hydrateFromRow($row);
        }

        return new ImmutableCollection($models);
    }

    /**
     * Begin querying the model.
     */
    public static function query(): ImmutableQueryBuilder
    {
        return (new static())->newQuery();
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): ImmutableQueryBuilder
    {
        return (new ImmutableQueryBuilder($this))
            ->applyGlobalScopes();
    }

    /**
     * Get a new query builder without global scopes.
     */
    public static function withoutGlobalScopes(): ImmutableQueryBuilder
    {
        $instance = new static();
        $builder = new ImmutableQueryBuilder($instance);

        return $builder->withoutGlobalScopes();
    }

    /**
     * Get a new query builder without a specific global scope.
     *
     * @param class-string<ImmutableModelScope> $scope
     */
    public static function withoutGlobalScope(string $scope): ImmutableQueryBuilder
    {
        $instance = new static();
        $builder = new ImmutableQueryBuilder($instance);

        return $builder->withoutGlobalScope($scope)->applyGlobalScopes();
    }

    /**
     * Find a model by its primary key.
     */
    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findOrFail(mixed $id): static
    {
        return static::query()->findOrFail($id);
    }

    /**
     * Execute the query and get the first result.
     */
    public static function first(): ?static
    {
        return static::query()->first();
    }

    /**
     * Get all models from the database.
     */
    public static function all(): ImmutableCollection
    {
        return static::query()->get();
    }

    /**
     * Add a basic where clause to the query.
     */
    public static function where(mixed ...$args): ImmutableQueryBuilder
    {
        return static::query()->where(...$args);
    }

    /**
     * Begin querying a model with eager loading.
     *
     * @param string|array<int, string> $relations
     */
    public static function with(string|array $relations): ImmutableQueryBuilder
    {
        return static::query()->with($relations);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): ?string
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): mixed
    {
        if ($this->primaryKey === null) {
            throw ImmutableModelConfigurationException::missingPrimaryKey('getKey');
        }

        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Get the database connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    /**
     * Get the relations to eager load by default.
     *
     * @return array<int, string>
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * Get the casts array.
     *
     * @return array<string, string|class-string>
     */
    public function getCasts(): array
    {
        return $this->casts;
    }

    /**
     * Get the global scopes for this model.
     *
     * @return array<class-string, class-string<ImmutableModelScope>>
     */
    public static function getGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        // Check for accessor method
        if ($this->hasAccessor($key)) {
            return $this->callAccessor($key);
        }

        // Check if it's a relation
        if ($this->relationLoaded($key)) {
            return $this->getRelation($key);
        }

        // Check if it's a relation method
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        // Return the raw or cast attribute
        return $this->getAttributeValue($key);
    }

    /**
     * Get the raw attribute value without casting.
     */
    public function getRawAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get the attribute value with casting applied.
     */
    protected function getAttributeValue(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        // Apply cast if defined
        $casts = $this->getCasts();
        if (isset($casts[$key])) {
            return $this->getCastManager()->cast($key, $value, $casts[$key]);
        }

        return $value;
    }

    /**
     * Get all raw attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check if the model has an accessor for an attribute.
     */
    protected function hasAccessor(string $key): bool
    {
        return method_exists($this, 'get' . Str::studly($key) . 'Attribute');
    }

    /**
     * Call the accessor for an attribute.
     */
    protected function callAccessor(string $key): mixed
    {
        $method = 'get' . Str::studly($key) . 'Attribute';

        return $this->{$method}($this->attributes[$key] ?? null);
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get a specified relationship.
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationValue(string $key): mixed
    {
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // Load the relation
        $relation = $this->{$key}();

        if ($relation instanceof ImmutableBelongsTo ||
            $relation instanceof ImmutableHasOne ||
            $relation instanceof ImmutableHasMany) {
            $result = $relation->getResults();
            $this->setRelationInternal($key, $result);

            return $result;
        }

        return null;
    }

    /**
     * Set a relation internally during hydration (not exposed publicly).
     *
     * @internal
     */
    final public function setRelation(string $relation, mixed $value): never
    {
        throw ImmutableModelViolationException::relationMutation($relation);
    }

    /**
     * Internal method to set relation during hydration.
     *
     * @internal
     */
    public function setRelationInternal(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Get all loaded relations.
     *
     * @return array<string, mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get the cast manager instance.
     */
    protected function getCastManager(): CastManager
    {
        if ($this->castManager === null) {
            $this->castManager = new CastManager();
        }

        return $this->castManager;
    }

    /**
     * Define a belongs-to relationship.
     *
     * @param class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model> $related
     */
    protected function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): ImmutableBelongsTo {
        // Get the name of the calling method as the relation name
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        // Default foreign key: {relation}_id
        $foreignKey ??= Str::snake($relation) . '_id';

        // Default owner key: typically 'id' for most models
        $ownerKey ??= 'id';

        return new ImmutableBelongsTo(
            $this,
            $related,
            $foreignKey,
            $ownerKey,
            $relation
        );
    }

    /**
     * Define a has-one relationship.
     *
     * @param class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model> $related
     */
    protected function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): ImmutableHasOne {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        // Default foreign key: {model}_id
        $foreignKey ??= Str::snake(class_basename($this)) . '_id';

        // Default local key: primary key
        $localKey ??= $this->primaryKey ?? 'id';

        return new ImmutableHasOne(
            $this,
            $related,
            $foreignKey,
            $localKey,
            $relation
        );
    }

    /**
     * Define a has-many relationship.
     *
     * @param class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model> $related
     */
    protected function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): ImmutableHasMany {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        // Default foreign key: {model}_id
        $foreignKey ??= Str::snake(class_basename($this)) . '_id';

        // Default local key: primary key
        $localKey ??= $this->primaryKey ?? 'id';

        return new ImmutableHasMany(
            $this,
            $related,
            $foreignKey,
            $localKey,
            $relation
        );
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = $this->attributesToArray();
        $relations = $this->relationsToArray();

        return array_merge($attributes, $relations);
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array<string, mixed>
     */
    protected function attributesToArray(): array
    {
        $attributes = [];

        // Get visible/hidden filtered keys
        $keys = $this->getArrayableAttributes();

        foreach ($keys as $key) {
            $attributes[$key] = $this->getAttributeValue($key);
        }

        // Add appends
        foreach ($this->appends as $key) {
            if ($this->hasAccessor($key)) {
                $attributes[$key] = $this->callAccessor($key);
            }
        }

        return $attributes;
    }

    /**
     * Get the arrayable attribute keys.
     *
     * @return array<int, string>
     */
    protected function getArrayableAttributes(): array
    {
        $keys = array_keys($this->attributes);

        if (count($this->visible) > 0) {
            $keys = array_intersect($keys, $this->visible);
        }

        if (count($this->hidden) > 0) {
            $keys = array_diff($keys, $this->hidden);
        }

        return array_values($keys);
    }

    /**
     * Convert the model's relations to an array.
     *
     * @return array<string, mixed>
     */
    protected function relationsToArray(): array
    {
        $relations = [];

        foreach ($this->relations as $key => $value) {
            if (in_array($key, $this->hidden, true)) {
                continue;
            }

            if (count($this->visible) > 0 && ! in_array($key, $this->visible, true)) {
                continue;
            }

            if ($value instanceof ImmutableCollection || $value instanceof ImmutableModel) {
                $relations[$key] = $value->toArray();
            } elseif ($value instanceof Arrayable) {
                $relations[$key] = $value->toArray();
            } elseif ($value === null) {
                $relations[$key] = null;
            } else {
                $relations[$key] = $value;
            }
        }

        return $relations;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param int $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model (throws).
     *
     * @throws ImmutableModelViolationException
     */
    public function __set(string $key, mixed $value): never
    {
        throw ImmutableModelViolationException::attributeMutation($key);
    }

    /**
     * Determine if an attribute exists on the model.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key])
            || $this->relationLoaded($key)
            || $this->hasAccessor($key);
    }

    /**
     * Unset an attribute on the model (throws).
     *
     * @throws ImmutableModelViolationException
     */
    public function __unset(string $key): never
    {
        throw ImmutableModelViolationException::attributeMutation($key);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param mixed $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param mixed $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset (throws).
     *
     * @param mixed $offset
     * @param mixed $value
     * @throws ImmutableModelViolationException
     */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw ImmutableModelViolationException::attributeMutation($offset);
    }

    /**
     * Unset the value for a given offset (throws).
     *
     * @param mixed $offset
     * @throws ImmutableModelViolationException
     */
    public function offsetUnset(mixed $offset): never
    {
        throw ImmutableModelViolationException::attributeMutation($offset);
    }

    /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver(): ?ConnectionResolverInterface
    {
        return static::$resolver;
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): \Illuminate\Database\Connection
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): \Illuminate\Database\Connection
    {
        return static::$resolver->connection($connection);
    }

    // =========================================================================
    // FORBIDDEN METHODS - These throw to prevent any mutation attempts
    // =========================================================================

    /**
     * @throws ImmutableModelViolationException
     */
    public function save(array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('save');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function update(array $attributes = [], array $options = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('update');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function delete(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('delete');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public static function create(array $attributes = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('create');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function fill(array $attributes): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('fill');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function push(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('push');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function touch(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('touch');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function increment(string $column, float|int $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('increment');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function decrement(string $column, float|int $amount = 1, array $extra = []): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('decrement');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function forceDelete(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('forceDelete');
    }

    /**
     * @throws ImmutableModelViolationException
     */
    public function restore(): never
    {
        throw ImmutableModelViolationException::persistenceAttempt('restore');
    }
}
