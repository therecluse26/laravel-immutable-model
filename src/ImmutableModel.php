<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel;

use ArrayAccess;
use Brighten\ImmutableModel\Casts\CastManager;
use Brighten\ImmutableModel\Exceptions\ImmutableModelConfigurationException;
use Brighten\ImmutableModel\Exceptions\ImmutableModelViolationException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\InvalidCastException;
use Brighten\ImmutableModel\Relations\ImmutableBelongsTo;
use Brighten\ImmutableModel\Relations\ImmutableBelongsToMany;
use Brighten\ImmutableModel\Relations\ImmutableHasMany;
use Brighten\ImmutableModel\Relations\ImmutableHasManyThrough;
use Brighten\ImmutableModel\Relations\ImmutableHasOne;
use Brighten\ImmutableModel\Relations\ImmutableHasOneThrough;
use Brighten\ImmutableModel\Relations\ImmutableMorphMany;
use Brighten\ImmutableModel\Relations\ImmutableMorphOne;
use Brighten\ImmutableModel\Relations\ImmutableMorphTo;
use Brighten\ImmutableModel\Relations\ImmutableMorphToMany;
use Brighten\ImmutableModel\Scopes\ImmutableModelScope;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Str;
use JsonSerializable;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
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
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updated_at';

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
     * The format for date serialization.
     */
    protected ?string $dateFormat = null;

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
     * The built-in, primitive cast types supported by Eloquent.
     *
     * @var string[]
     */
    protected static $primitiveCastTypes = [
        'array',
        'bool',
        'boolean',
        'collection',
        'custom_datetime',
        'date',
        'datetime',
        'decimal',
        'double',
        'encrypted',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'encrypted:object',
        'float',
        'hashed',
        'immutable_date',
        'immutable_datetime',
        'immutable_custom_datetime',
        'int',
        'integer',
        'json',
        'object',
        'real',
        'string',
        'timestamp',
    ];

    /**
     * The cache of the "Attribute" return type marked mutated attributes for each class.
     *
     * @var array
     */
    protected static $attributeMutatorCache = [];

    /**
     * The cache of the "Attribute" return type marked mutated, gettable attributes for each class.
     *
     * @var array
     */
    protected static $getAttributeMutatorCache = [];

    /**
     * The cast manager instance.
     */
    private ?CastManager $castManager = null;

    /**
     * Create a new immutable model instance.
     *
     * The constructor is final and public to allow Laravel's relationship system
     * to create template instances. However, an instance created via `new` has no
     * attributes and is essentially useless except as a template for query building.
     *
     * Immutability is preserved because:
     * - There is no fill() method
     * - __set() throws ImmutableModelViolationException
     * - Attributes can only be set via internal hydration methods
     */
    final public function __construct()
    {
        // Intentionally empty - attributes are set via hydration
    }

    // =========================================================================
    // LARAVEL INTEROP - Methods required for Eloquent relationship compatibility
    // =========================================================================

    /**
     * Create a new instance of the model.
     *
     * This method is used by Laravel's relationship system to create template
     * instances for building queries. The $attributes and $exists parameters
     * are ignored since ImmutableModels cannot be filled or marked as existing.
     *
     * @param array<string, mixed> $attributes Ignored
     * @param bool $exists Ignored
     */
    public function newInstance($attributes = [], $exists = false): static
    {
        $model = new static();

        // Copy connection to new instance if set
        if ($this->connection !== null) {
            $model->connection = $this->connection;
        }

        return $model;
    }

    /**
     * Create a new model instance from database row data.
     *
     * This method is called by Laravel's Eloquent builder during hydration.
     * It delegates to our existing fromRow() method to ensure proper hydration.
     *
     * @param array<string, mixed>|object $attributes
     */
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        $model = static::fromRow((array) $attributes);

        // Set connection if provided
        if ($connection !== null) {
            $model->connection = $connection;
        } elseif ($this->connection !== null) {
            $model->connection = $this->connection;
        }

        return $model;
    }

    /**
     * Set the connection associated with the model.
     *
     * This method is called by Laravel's relationship system when creating
     * template instances. The connection is used for subsequent queries.
     */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Create a new collection instance.
     *
     * This method is called by Laravel's relationship system when wrapping
     * query results in a collection.
     *
     * @param array<int, static> $models
     */
    public function newCollection(array $models = []): EloquentCollection
    {
        return new EloquentCollection($models);
    }

    /**
     * Qualify a column name with the model's table.
     *
     * This method is called by Laravel's relationship system when building
     * queries to ensure column names are properly qualified with table names.
     */
    public function qualifyColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    /**
     * Determine if the model has a given scope.
     *
     * This matches Eloquent's Model::hasNamedScope() implementation.
     *
     * @param string $scope
     * @return bool
     */
    public function hasNamedScope(string $scope): bool
    {
        return method_exists($this, 'scope'.ucfirst($scope));
    }

    /**
     * Apply the given named scope if possible.
     *
     * This matches Eloquent's Model::callNamedScope() implementation.
     * The builder is prepended to parameters by Laravel's Builder::callScope().
     *
     * @param string $scope
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function callNamedScope(string $scope, array $parameters = []): mixed
    {
        return $this->{'scope'.ucfirst($scope)}(...$parameters);
    }

    /**
     * Get the fully qualified key name for the model.
     *
     * This method is used by Laravel's relationship system.
     */
    public function getQualifiedKeyName(): string
    {
        return $this->qualifyColumn($this->getKeyName() ?? 'id');
    }

    /**
     * Get the foreign key for the model.
     *
     * This method is used by Laravel's relationship system.
     */
    public function getForeignKey(): string
    {
        $basename = class_basename($this);

        // Strip "Immutable" prefix if present to match Eloquent conventions
        // e.g., ImmutableTag -> tag_id, not immutable_tag_id
        if (str_starts_with($basename, 'Immutable')) {
            $basename = substr($basename, 9); // length of "Immutable"
        }

        return Str::snake($basename) . '_' . ($this->primaryKey ?? 'id');
    }

    /**
     * Determine if the model has any mutator attributes.
     *
     * ImmutableModels do not support mutators, so this always returns false.
     * This method is required for Laravel's Builder pluck() compatibility.
     */
    public function hasAnyGetMutator(): bool
    {
        return false;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param  string  $key
     * @param  array|string|null  $types
     * @return bool
     */
    public function hasCast($key, $types = null)
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getCastType($key)
    {
        $castType = $this->getCasts()[$key];

        if ($this->isCustomDateTimeCast($castType)) {
            return 'custom_datetime';
        }

        if ($this->isImmutableCustomDateTimeCast($castType)) {
            return 'immutable_custom_datetime';
        }

        if ($this->isDecimalCast($castType)) {
            return 'decimal';
        }

        if (class_exists($castType)) {
            return $castType;
        }

        return trim(strtolower($castType));
    }

    /**
     * Determine if the cast type is a custom date time cast.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isCustomDateTimeCast($cast)
    {
        return str_starts_with($cast, 'date:') ||
                str_starts_with($cast, 'datetime:');
    }

    /**
     * Determine if the cast type is an immutable custom date time cast.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isImmutableCustomDateTimeCast($cast)
    {
        return str_starts_with($cast, 'immutable_date:') ||
                str_starts_with($cast, 'immutable_datetime:');
    }

    /**
     * Determine if the cast type is a decimal cast.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isDecimalCast($cast)
    {
        return str_starts_with($cast, 'decimal:');
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * This property enables automatic date casting for created_at/updated_at columns
     * without requiring explicit casts. Matches Eloquent's behavior.
     */
    public $timestamps = true;

    /**
     * Get the attributes that should be converted to dates.
     *
     * This method matches Eloquent's getDates() behavior: it returns the timestamp
     * columns (created_at, updated_at) if timestamps are enabled. Columns with
     * explicit datetime casts in $casts don't need to be listed here.
     *
     * This method is required for Laravel's Builder pluck() compatibility.
     *
     * @return array<int, string>
     */
    public function getDates(): array
    {
        // Match Eloquent: return timestamp columns only
        if ($this->timestamps) {
            return [
                $this->getCreatedAtColumn(),
                $this->getUpdatedAtColumn(),
            ];
        }

        return [];
    }

    /**
     * Get the name of the "created at" column.
     *
     * This method is required for Eloquent's latest() and oldest() methods.
     */
    /**
     * Get the name of the "created at" column.
     *
     * @return string|null
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string|null
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
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
    public static function fromRows(iterable $rows): EloquentCollection
    {
        $models = [];

        foreach ($rows as $row) {
            $models[] = static::hydrateFromRow($row);
        }

        return new EloquentCollection($models);
    }

    /**
     * Create a collection of models from plain arrays / objects.
     *
     * This method is required for compatibility with Laravel's Eloquent Builder,
     * which calls hydrate() when loading models via getModels().
     *
     * @param iterable<array<string, mixed>|stdClass> $items
     */
    public static function hydrate(iterable $items, ?string $connection = null): EloquentCollection
    {
        $instance = new static();

        if ($connection !== null) {
            $instance->connection = $connection;
        }

        $models = [];
        foreach ($items as $item) {
            $models[] = $instance->newFromBuilder($item, $connection);
        }

        return $instance->newCollection($models);
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
    public static function all(): EloquentCollection
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
     * Handle dynamic static method calls into the model.
     *
     * Forward unknown static calls to a new query builder instance, enabling
     * Eloquent-compatible query building like Model::whereIn(), Model::orderBy(), etc.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static())->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * Forward unknown instance calls to the query builder.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->$method(...$parameters);
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
     * Supports both property-based casts (protected array $casts) and
     * Laravel 11's method-based casts (protected function casts(): array).
     *
     * @return array<string, string|class-string>
     */
    public function getCasts(): array
    {
        $casts = $this->casts;

        // Support Laravel 11's method-based casts
        if (method_exists($this, 'casts') && is_callable([$this, 'casts'])) {
            // Use reflection to check if casts() is a real method defined in a subclass
            // (not this base class method check itself)
            $reflection = new \ReflectionMethod($this, 'casts');
            if ($reflection->getDeclaringClass()->getName() !== self::class) {
                $casts = array_merge($casts, $this->casts());
            }
        }

        return $casts;
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
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (! $key) {
            return;
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if ($this->hasAttribute($key)) {
            return $this->getAttributeValue($key);
        }

        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(self::class, $key)) {
            return $this->throwMissingAttributeExceptionIfApplicable($key);
        }

        return $this->isRelation($key) || $this->relationLoaded($key)
                    ? $this->getRelationValue($key)
                    : $this->throwMissingAttributeExceptionIfApplicable($key);
    }

    /**
     * Either throw a missing attribute exception or return null depending on Eloquent's configuration.
     *
     * ImmutableModel does not track $exists state, so this always returns null.
     *
     * @param  string  $key
     * @return null
     */
    protected function throwMissingAttributeExceptionIfApplicable($key)
    {
        return null;
    }

    /**
     * Get the raw attribute value without casting.
     */
    public function getRawAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get the model's original attribute values.
     *
     * Since ImmutableModel cannot be modified, the original values
     * are always identical to the current values.
     *
     * @param string|null $key If null, returns all original attributes
     * @param mixed $default Default value if key doesn't exist
     */
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            // Return all attributes with casting applied
            $result = [];
            foreach (array_keys($this->attributes) as $attrKey) {
                $result[$attrKey] = $this->getAttributeValue($attrKey);
            }

            return $result;
        }

        return $this->getAttributeValue($key) ?? $default;
    }

    /**
     * Get the model's raw original attribute values (without casting).
     *
     * Since ImmutableModel cannot be modified, the original values
     * are always identical to the current values.
     *
     * @param string|null $key If null, returns all raw original attributes
     * @param mixed $default Default value if key doesn't exist
     */
    public function getRawOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->attributes;
        }

        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        return $this->transformModelValue($key, $this->getAttributeFromArray($key));
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        return $this->getAttributes()[$key] ?? null;
    }

    /**
     * Transform a raw model value using mutators, casts, etc.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transformModelValue($key, $value)
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        } elseif ($this->hasAttributeGetMutator($key)) {
            return $this->mutateAttributeMarkedAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($value !== null
            && \in_array($key, $this->getDates(), false)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        return $this->{'get'.Str::studly($key).'Attribute'}($value);
    }

    /**
     * Get the value of an "Attribute" return type marked attribute using its mutator.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttributeMarkedAttribute($key, $value)
    {
        $attribute = $this->{Str::camel($key)}();

        $value = call_user_func($attribute->get ?: function ($value) {
            return $value;
        }, $value, $this->attributes);

        return $value;
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        return $this->getCastManager()->cast($key, $value, $this->getCasts()[$key]);
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon
     */
    protected function asDateTime($value)
    {
        return $this->getCastManager()->castDate($value);
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
     * Determine whether an attribute exists on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasAttribute($key)
    {
        if (! $key) {
            return false;
        }

        return array_key_exists($key, $this->attributes) ||
            array_key_exists($key, $this->casts) ||
            $this->hasGetMutator($key) ||
            $this->hasAttributeMutator($key) ||
            $this->isClassCastable($key);
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    /**
     * Determine if a "Attribute" return type marked mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasAttributeMutator($key)
    {
        if (isset(static::$attributeMutatorCache[get_class($this)][$key])) {
            return static::$attributeMutatorCache[get_class($this)][$key];
        }

        if (! method_exists($this, $method = Str::camel($key))) {
            return static::$attributeMutatorCache[get_class($this)][$key] = false;
        }

        $returnType = (new ReflectionMethod($this, $method))->getReturnType();

        return static::$attributeMutatorCache[get_class($this)][$key] =
                    $returnType instanceof ReflectionNamedType &&
                    $returnType->getName() === Attribute::class;
    }

    /**
     * Determine if a "Attribute" return type marked get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasAttributeGetMutator($key)
    {
        if (isset(static::$getAttributeMutatorCache[get_class($this)][$key])) {
            return static::$getAttributeMutatorCache[get_class($this)][$key];
        }

        if (! $this->hasAttributeMutator($key)) {
            return static::$getAttributeMutatorCache[get_class($this)][$key] = false;
        }

        return static::$getAttributeMutatorCache[get_class($this)][$key] = is_callable($this->{Str::camel($key)}()->get);
    }

    /**
     * Determine if the given key is cast using a custom class.
     *
     * @param  string  $key
     * @return bool
     *
     * @throws \Illuminate\Database\Eloquent\InvalidCastException
     */
    protected function isClassCastable($key)
    {
        $casts = $this->getCasts();

        if (! array_key_exists($key, $casts)) {
            return false;
        }

        $castType = $this->parseCasterClass($casts[$key]);

        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        if (class_exists($castType)) {
            return true;
        }

        throw new InvalidCastException($this, $key, $castType);
    }

    /**
     * Parse the given caster class, removing any arguments.
     *
     * @param  string  $class
     * @return string
     */
    protected function parseCasterClass($class)
    {
        return ! str_contains($class, ':')
            ? $class
            : explode(':', $class, 2)[0];
    }

    /**
     * Determine if the given key is a relationship method on the model.
     *
     * @param  string  $key
     * @return bool
     */
    public function isRelation($key)
    {
        if ($this->hasAttributeMutator($key)) {
            return false;
        }

        return method_exists($this, $key) ||
               $this->relationResolver(static::class, $key);
    }

    /**
     * Get the dynamic relation resolver if defined or inherited, or return null.
     *
     * @param  string  $class
     * @param  string  $key
     * @return mixed
     */
    public function relationResolver($class, $key)
    {
        // ImmutableModel does not support dynamic relation resolvers
        return null;
    }

    /**
     * Check if the model has an accessor for an attribute.
     *
     * @deprecated Use hasGetMutator() instead
     */
    protected function hasAccessor(string $key): bool
    {
        return $this->hasGetMutator($key);
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
    /**
     * Get a loaded relation.
     *
     * Matches Eloquent's behavior: throws if relation isn't loaded.
     * Use relationLoaded() first to check if a relation is available.
     */
    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation];
    }

    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (! $this->isRelation($key)) {
            return;
        }

        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's value on the "relationships" array.
        return $this->getRelationshipFromMethod($key);
    }

    /**
     * Get a relationship value from a method.
     *
     * @param  string  $method
     * @return mixed
     *
     * @throws \LogicException
     */
    protected function getRelationshipFromMethod($method)
    {
        $relation = $this->$method();

        if (! $relation instanceof ImmutableBelongsTo &&
            ! $relation instanceof ImmutableHasOne &&
            ! $relation instanceof ImmutableHasMany &&
            ! $relation instanceof ImmutableBelongsToMany &&
            ! $relation instanceof ImmutableHasOneThrough &&
            ! $relation instanceof ImmutableHasManyThrough &&
            ! $relation instanceof ImmutableMorphOne &&
            ! $relation instanceof ImmutableMorphMany &&
            ! $relation instanceof ImmutableMorphTo &&
            ! $relation instanceof ImmutableMorphToMany) {
            if (is_null($relation)) {
                throw new LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method
                ));
            }

            throw new LogicException(sprintf(
                '%s::%s must return a relationship instance.', static::class, $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Set a relation on the model.
     *
     * While ImmutableModel prevents database persistence, in-memory relation
     * setting is allowed for common patterns like eager loading and API responses.
     */
    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Internal method to set relation during hydration.
     *
     * @internal
     * @deprecated Use setRelation() instead
     */
    public function setRelationInternal(string $relation, mixed $value): static
    {
        return $this->setRelation($relation, $value);
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

    // =========================================================================
    // Relationship Helper Methods
    // =========================================================================

    /**
     * Get the joining table name for a many-to-many relation.
     *
     * Follows Eloquent's convention: snake_cased model names sorted alphabetically.
     * e.g., Post + Tag = "post_tag" (not "tag_post")
     *
     * @param  class-string  $related
     */
    public function joiningTable(string $related, mixed $instance = null): string
    {
        $segments = [
            $instance instanceof self
                ? $instance->joiningTableSegment()
                : Str::snake(class_basename($related)),
            $this->joiningTableSegment(),
        ];

        sort($segments);

        return strtolower(implode('_', $segments));
    }

    /**
     * Get this model's half of the intermediate table name for belongsToMany relationships.
     */
    public function joiningTableSegment(): string
    {
        $basename = class_basename($this);

        // Strip "Immutable" prefix if present to match Eloquent conventions
        if (str_starts_with($basename, 'Immutable')) {
            $basename = substr($basename, 9);
        }

        return Str::snake($basename);
    }

    /**
     * Get the polymorphic relationship columns.
     *
     * @return array{0: string, 1: string} [type column, id column]
     */
    protected function getMorphs(string $name, ?string $type, ?string $id): array
    {
        return [$type ?: $name . '_type', $id ?: $name . '_id'];
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * Uses Laravel's morph map if available, otherwise returns FQCN.
     */
    public function getMorphClass(): string
    {
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();

        if (! empty($morphMap) && in_array(static::class, $morphMap, true)) {
            return array_search(static::class, $morphMap, true);
        }

        return static::class;
    }

    /**
     * Guess the "belongs to" relationship name.
     */
    protected function guessBelongsToRelation(): string
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Get the relationship name of the belongsToMany relationship.
     */
    protected function guessBelongsToManyRelation(): ?string
    {
        $caller = \Illuminate\Support\Arr::first(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            fn ($trace) => ! in_array(
                $trace['function'],
                ['belongsToMany', 'morphToMany', 'morphedByMany', 'guessBelongsToManyRelation']
            )
        );

        return $caller['function'] ?? null;
    }

    // =========================================================================
    // Relationship Factory Methods
    // =========================================================================

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
     * Define a many-to-many relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     */
    protected function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): ImmutableBelongsToMany {
        $relation = $this->guessBelongsToManyRelation();

        // Create instance to get default keys
        $instance = new $related();

        // Default pivot keys
        $foreignPivotKey ??= $this->getForeignKey();
        $relatedPivotKey ??= $instance->getForeignKey();

        // Default table: alphabetical snake_case of model names
        $table ??= $this->joiningTable($related, $instance);

        // Default model keys
        $parentKey ??= $this->getKeyName() ?? 'id';
        $relatedKey ??= $instance->getKeyName() ?? 'id';

        return new ImmutableBelongsToMany(
            $this,
            $related,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation ?? ''
        );
    }

    /**
     * Define a has-one-through relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $through
     */
    protected function hasOneThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): ImmutableHasOneThrough {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        $throughInstance = new $through();

        // Default keys following Eloquent's conventions
        $firstKey ??= $this->getForeignKey();
        $secondKey ??= $throughInstance->getForeignKey();
        $localKey ??= $this->getKeyName() ?? 'id';
        $secondLocalKey ??= $throughInstance->getKeyName() ?? 'id';

        return new ImmutableHasOneThrough(
            $this,
            $through,
            $related,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey,
            $relation
        );
    }

    /**
     * Define a has-many-through relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $through
     */
    protected function hasManyThrough(
        string $related,
        string $through,
        ?string $firstKey = null,
        ?string $secondKey = null,
        ?string $localKey = null,
        ?string $secondLocalKey = null
    ): ImmutableHasManyThrough {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        $throughInstance = new $through();

        // Default keys following Eloquent's conventions
        $firstKey ??= $this->getForeignKey();
        $secondKey ??= $throughInstance->getForeignKey();
        $localKey ??= $this->getKeyName() ?? 'id';
        $secondLocalKey ??= $throughInstance->getKeyName() ?? 'id';

        return new ImmutableHasManyThrough(
            $this,
            $through,
            $related,
            $firstKey,
            $secondKey,
            $localKey,
            $secondLocalKey,
            $relation
        );
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     */
    protected function morphOne(
        string $related,
        string $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null
    ): ImmutableMorphOne {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        [$type, $id] = $this->getMorphs($name, $type, $id);
        $localKey ??= $this->getKeyName() ?? 'id';

        return new ImmutableMorphOne(
            $this,
            $related,
            $type,
            $id,
            $localKey,
            $relation
        );
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     */
    protected function morphMany(
        string $related,
        string $name,
        ?string $type = null,
        ?string $id = null,
        ?string $localKey = null
    ): ImmutableMorphMany {
        $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        [$type, $id] = $this->getMorphs($name, $type, $id);
        $localKey ??= $this->getKeyName() ?? 'id';

        return new ImmutableMorphMany(
            $this,
            $related,
            $type,
            $id,
            $localKey,
            $relation
        );
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     */
    protected function morphTo(
        ?string $name = null,
        ?string $type = null,
        ?string $id = null,
        ?string $ownerKey = null
    ): ImmutableMorphTo {
        // Get the name from the calling method if not provided
        $name ??= $this->guessBelongsToRelation();

        [$type, $id] = $this->getMorphs(Str::snake($name), $type, $id);

        return new ImmutableMorphTo(
            $this,
            $id,
            $ownerKey,
            $type,
            $name
        );
    }

    /**
     * Define a polymorphic many-to-many relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     */
    protected function morphToMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        bool $inverse = false
    ): ImmutableMorphToMany {
        $relation = $this->guessBelongsToManyRelation();

        $instance = new $related();

        // Default keys
        $foreignPivotKey ??= $name . '_id';
        $relatedPivotKey ??= $instance->getForeignKey();
        $parentKey ??= $this->getKeyName() ?? 'id';
        $relatedKey ??= $instance->getKeyName() ?? 'id';

        // Default table: pluralized morph name (e.g., 'taggable' -> 'taggables')
        if ($table === null) {
            $words = preg_split('/(_)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
            $lastWord = array_pop($words);
            $table = implode('', $words) . Str::plural($lastWord);
        }

        return new ImmutableMorphToMany(
            $this,
            $related,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation ?? '',
            $inverse
        );
    }

    /**
     * Define a polymorphic, inverse many-to-many relationship.
     *
     * @param  class-string<ImmutableModel|\Illuminate\Database\Eloquent\Model>  $related
     */
    protected function morphedByMany(
        string $related,
        string $name,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null
    ): ImmutableMorphToMany {
        // For inverse, swap the pivot key defaults
        $foreignPivotKey ??= $this->getForeignKey();
        $relatedPivotKey ??= $name . '_id';

        return $this->morphToMany(
            $related,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            inverse: true
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
    /**
     * Get the model's attributes as an array.
     *
     * Includes cast attributes, accessors (from $appends), respects $visible/$hidden.
     * Does NOT include relations.
     */
    public function attributesToArray(): array
    {
        $attributes = [];

        // Get visible/hidden filtered keys
        $keys = $this->getArrayableAttributes();

        foreach ($keys as $key) {
            $value = $this->getAttributeValue($key);
            $attributes[$key] = $this->serializeValue($value);
        }

        // Add appends
        foreach ($this->appends as $key) {
            if ($this->hasAccessor($key)) {
                $value = $this->callAccessor($key);
                $attributes[$key] = $this->serializeValue($value);
            }
        }

        return $attributes;
    }

    /**
     * Serialize a value for array/JSON output.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->serializeDate($value);
        }

        if ($value instanceof Relations\ImmutablePivot) {
            return $value->toArray();
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Prepare a date for array/JSON serialization.
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Get the format for date serialization.
     *
     * @return string
     */
    protected function getDateFormat(): string
    {
        return $this->dateFormat ?? 'Y-m-d\TH:i:s.u\Z';
    }

    /**
     * Get the arrayable attribute keys.
     *
     * @return array<int, string>
     */
    protected function getArrayableAttributes(): array
    {
        $keys = array_keys($this->attributes);

        // Filter out pivot_* attributes (they're handled via the pivot relation)
        $keys = array_filter($keys, fn($key) => ! str_starts_with($key, 'pivot_'));

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
    /**
     * Get the model's relations as an array.
     *
     * Returns all loaded relations serialized to arrays.
     */
    public function relationsToArray(): array
    {
        $relations = [];

        foreach ($this->relations as $key => $value) {
            if (in_array($key, $this->hidden, true)) {
                continue;
            }

            if (count($this->visible) > 0 && ! in_array($key, $this->visible, true)) {
                continue;
            }

            if ($value instanceof EloquentCollection || $value instanceof ImmutableModel) {
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
     * Dynamically set attributes on the model.
     *
     * While ImmutableModel prevents database persistence, in-memory attribute
     * mutation is allowed for common patterns like adding computed properties
     * for API responses.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
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
     * Unset an attribute on the model.
     *
     * While ImmutableModel prevents database persistence, in-memory attribute
     * mutation is allowed for common patterns.
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
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
     * Set the value for a given offset.
     *
     * While ImmutableModel prevents database persistence, in-memory attribute
     * mutation is allowed for common patterns.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * While ImmutableModel prevents database persistence, in-memory attribute
     * mutation is allowed for common patterns.
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
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
     *
     * Falls back to Eloquent's connection resolver if none has been explicitly set,
     * allowing ImmutableModel to work alongside Eloquent without additional configuration.
     */
    public static function resolveConnection(?string $connection = null): \Illuminate\Database\Connection
    {
        $resolver = static::$resolver ?? EloquentModel::getConnectionResolver();

        if ($resolver === null) {
            throw ImmutableModelConfigurationException::missingConnectionResolver();
        }

        return $resolver->connection($connection);
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
